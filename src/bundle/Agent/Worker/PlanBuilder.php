<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Worker;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use InvalidArgumentException;
use Masilia\Bundle\IbexaAi\Agent\Block\BlockCatalog;
use Masilia\Bundle\IbexaAi\Agent\Block\ContentCatalog;
use Masilia\Bundle\IbexaAi\Agent\Tool\SiteaccessLocationResolver;
use Masilia\Bundle\IbexaAi\Support\FieldType;

/**
 * Validates and constructs a Plan from orchestrator tool arguments.
 *
 * The orchestrator LLM calls `propose_plan(...)` with raw arguments.
 * This worker validates the structure and returns a typed `Plan`.
 *
 * Three-layer validation:
 *   1. Structural validation (Plan::validate()) — intent, title,
 *      siteaccess, parent_location_id, etc.
 *   2. Schema validation (validateBlockSchemas()) — block fields match
 *      the actual content type schema, especially matrix column names.
 *   3. Required-field validation (validateRequiredFields()) — required
 *      fields are not empty.
 *
 * No default-block suggestion: the LLM is the source of truth for which
 * blocks to use. create_content plans with empty `blocks` are accepted and
 * produce content with no blocks — the LLM should always propose its own
 * layout based on the explore_site output.
 */
final readonly class PlanBuilder
{
    public function __construct(
        private ?BlockCatalog   $blockCatalog = null,
        private ?ContentCatalog $contentCatalog = null,
        private ?Repository $repository = null,
        private ?SiteaccessLocationResolver $siteaccessResolver = null,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments Raw arguments from the LLM's `propose_plan` call
     */
    public function build(array $arguments): Plan
    {
        $plan = Plan::fromArray($arguments);

        $error = $plan->validate();
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        // Checked before the schemas and before any approval: the user is never asked to approve a plan
        // that cannot run (unknown content type, missing parent, parent in another site).
        $targetError = $this->validateTargets($plan);
        if ($targetError !== null) {
            throw new InvalidArgumentException($targetError);
        }

        $schemaError = $this->validateBlockSchemas($plan);
        if ($schemaError !== null) {
            throw new InvalidArgumentException($schemaError);
        }

        $requiredError = $this->validateRequiredFields($plan);
        if ($requiredError !== null) {
            throw new InvalidArgumentException($requiredError);
        }

        return $plan;
    }

    /**
     * Validate what the plan points at: the content type exists, the parent location exists, and the parent
     * is not in a different site than the siteaccess. Without a repository (unit tests) nothing is checked.
     */
    private function validateTargets(Plan $plan): ?string
    {
        if ($this->repository === null) {
            return null;
        }

        if ($plan->language !== null) {
            if ($plan->intent !== Plan::INTENT_UPDATE_CONTENT) {
                return 'A language can only be set on an update_content plan (to write a translation). Other plans use the default language.';
            }
            try {
                $this->repository->getContentLanguageService()->loadLanguage($plan->language);
            } catch (NotFoundException) {
                return sprintf('Language "%s" does not exist. Use a language code such as "eng-GB" or "fre-FR".', $plan->language);
            }
        }

        if ($plan->intent === Plan::INTENT_CREATE_CONTENT && $plan->contentType !== null && $plan->contentType !== '') {
            try {
                $this->repository->getContentTypeService()->loadContentTypeByIdentifier($plan->contentType);
            } catch (NotFoundException) {
                return $this->unknownContentType($plan->contentType);
            } catch (UnauthorizedException) {
                return sprintf('You are not allowed to read the content type "%s".', $plan->contentType);
            }
        }

        $createsUnderParent = in_array($plan->intent, [Plan::INTENT_CREATE_CONTENT, Plan::INTENT_CREATE_FOLDER], true);
        if (!$createsUnderParent || $plan->parentLocationId === null) {
            return null;
        }

        try {
            $parent = $this->repository->getLocationService()->loadLocation($plan->parentLocationId);
        } catch (NotFoundException) {
            return sprintf('Parent location %d does not exist. Find the right location with explore_site or browse_location.', $plan->parentLocationId);
        } catch (UnauthorizedException) {
            return sprintf('You are not allowed to create content under location %d.', $plan->parentLocationId);
        }

        return $this->validateSameSite($plan, $parent->pathString);
    }

    /**
     * A parent under another site than the siteaccess's is almost always a mistake (wrong language and wrong
     * layout). Locations outside the sites tree (media, users) are not part of any site and are accepted.
     */
    private function validateSameSite(Plan $plan, string $parentPath): ?string
    {
        if ($this->siteaccessResolver === null || $plan->siteaccess === null || $plan->siteaccess === '') {
            return null;
        }

        $root = $this->siteaccessResolver->resolve($plan->siteaccess);
        if ($root === null || $root <= 0) {
            return null;
        }

        try {
            $rootPath = $this->repository?->getLocationService()->loadLocation($root)->pathString;
        } catch (\Throwable) {
            return null;
        }
        if ($rootPath === null) {
            return null;
        }

        $rootParts = array_values(array_filter(explode('/', $rootPath), static fn (string $part): bool => $part !== ''));
        $parentParts = array_values(array_filter(explode('/', $parentPath), static fn (string $part): bool => $part !== ''));

        // Same container of sites (e.g. /1/2/...), but a different site below it.
        if (isset($rootParts[2], $parentParts[2]) && $rootParts[1] === $parentParts[1] && $rootParts[2] !== $parentParts[2]) {
            return sprintf(
                'Parent location %d is under the site at location %s, but siteaccess "%s" belongs to the site at location %s: they are in different sites. Use a parent under the siteaccess\'s site, or the siteaccess of the parent\'s site.',
                $plan->parentLocationId,
                $parentParts[2],
                $plan->siteaccess,
                $rootParts[2],
            );
        }

        return null;
    }

    private function unknownContentType(string $identifier): string
    {
        $known = array_keys($this->contentCatalog?->getAvailableContentTypes() ?? []);
        sort($known);

        return sprintf(
            'Content type "%s" does not exist.%s',
            $identifier,
            $known === [] ? '' : ' Available types: ' . implode(', ', array_slice($known, 0, 40)) . '.',
        );
    }

    /**
     * Validate field structure against actual content type schemas.
     *
     * Currently checks matrix field column identifiers (the most common
     * LLM mistake — guessing column names that don't exist on the
     * content type). Unknown content types are skipped (lenient) so the
     * LLM can still propose plans for types the catalog doesn't know.
     */
    private function validateBlockSchemas(Plan $plan): ?string
    {
        if ($plan->intent !== Plan::INTENT_CREATE_CONTENT) {
            return null;
        }
        if ($plan->contentType === null || $plan->contentType === '') {
            return null;
        }

        $schema = $this->resolveContentTypeSchema($plan->contentType);
        if ($schema === null) {
            return null;
        }

        foreach ($schema['fields'] as $fieldId => $fieldInfo) {
            $fieldType = (string) ($fieldInfo['type'] ?? '');
            if ($fieldType !== FieldType::EZMATRIX) {
                continue;
            }

            $validColumns = array_column($fieldInfo['columns'] ?? [], 'identifier');
            if ($validColumns === []) {
                continue;
            }

            $value = $plan->fields[$fieldId] ?? null;
            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $rowIdx => $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach (array_keys($row) as $colKey) {
                    if (!in_array($colKey, $validColumns, true)) {
                        return sprintf(
                            'Content type "%s": matrix field "%s" row %d has unknown column "%s". Valid columns: %s',
                            $plan->contentType,
                            $fieldId,
                            $rowIdx,
                            $colKey,
                            implode(', ', $validColumns),
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * Validate that required fields are not empty.
     *
     * Catches the most common LLM mistake: submitting a plan where
     * a required field is `""`, `null`, or missing entirely. Without
     * this check, Ibexa's `ContentService::createContent` throws
     * `ContentFieldValidationException` AFTER user approval — which
     * is exactly the failure mode we want to avoid.
     *
     * Empty values considered invalid:
     *   - null
     *   - empty string `""` (also whitespace-only)
     *   - empty array `[]`
     *   - array whose entries are all empty (recursively)
     */
    private function validateRequiredFields(Plan $plan): ?string
    {
        if ($plan->intent !== Plan::INTENT_CREATE_CONTENT) {
            return null;
        }
        if ($plan->contentType === null || $plan->contentType === '') {
            return null;
        }

        $schema = $this->resolveContentTypeSchema($plan->contentType);
        if ($schema === null) {
            return null;
        }

        foreach ($schema['fields'] as $fieldId => $fieldInfo) {
            if (!($fieldInfo['required'] ?? false)) {
                continue;
            }

            $value = $plan->fields[$fieldId] ?? null;
            if (!$this->isValueEmpty($value)) {
                continue;
            }

            return sprintf(
                'Content type "%s": required field "%s" is empty. Provide a non-empty value.',
                $plan->contentType,
                $fieldId,
            );
        }

        return null;
    }

    private function isValueEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            if ($value === []) {
                return true;
            }
            foreach ($value as $entry) {
                if (!$this->isValueEmpty($entry)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Resolve a content type schema from BlockCatalog (block types) or
     * ContentCatalog (standard types like page, article, folder, etc.).
     */
    private function resolveContentTypeSchema(string $identifier): ?array
    {
        return $this->blockCatalog?->getBlockSchema($identifier)
            ?? $this->contentCatalog?->getContentTypeSchema($identifier);
    }
}
