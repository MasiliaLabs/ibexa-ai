<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Content\Write;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Chat\Attachment\AttachmentStore;
use Masilia\Bundle\IbexaAi\Agent\Tool\SiteLanguages;
use Masilia\Contracts\IbexaAi\Content\ContentTypeSchemas;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\IbexaAi\Suggestion\LanguageCode;
use Throwable;

/**
 * What must be true before the assistant writes something, checked before the user is asked to approve it: the content
 * type exists and its values fit it, the parent location exists and is in the site of the siteaccess, the language is one
 * the site is served in, the image is the user's own. Each check answers the problem to show the model, or null.
 *
 * The capabilities that write call these in their validate(), in the order the problems are best explained in.
 */
readonly class WriteChecks
{
    public function __construct(
        private ContentTypeSchemas $schemas,
        private Repository $repository,
        private SiteLocations $locations,
        private SiteLanguages $siteLanguages,
        private Languages $languages,
        private AttachmentStore $attachments,
        private PermissionResolver $permissions,
    ) {
    }

    /**
     * The content type exists, its matrix fields have the columns it has, and its required fields are filled.
     *
     * @param array<string, mixed> $fields
     */
    public function contentType(string $identifier, array $fields): ?string
    {
        $schema = $this->schemas->schema($identifier);
        if ($schema === null) {
            $known = $this->schemas->knownTypes();

            return sprintf(
                'Content type "%s" does not exist.%s',
                $identifier,
                $known === [] ? '' : ' Available types: ' . implode(', ', array_slice($known, 0, 40)) . '.',
            );
        }

        foreach ($schema['fields'] as $fieldId => $fieldInfo) {
            if (($fieldInfo['type'] ?? '') !== 'ibexa_matrix' || ($fieldInfo['columns'] ?? []) === [] || !is_array($fields[$fieldId] ?? null)) {
                continue;
            }
            $columns = array_column($fieldInfo['columns'], 'identifier');
            foreach ($fields[$fieldId] as $rowIndex => $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach (array_keys($row) as $column) {
                    if (!in_array($column, $columns, true)) {
                        return sprintf('Content type "%s": matrix field "%s" row %d has unknown column "%s". Valid columns: %s', $identifier, $fieldId, $rowIndex, $column, implode(', ', $columns));
                    }
                }
            }
        }

        foreach ($schema['fields'] as $fieldId => $fieldInfo) {
            if (($fieldInfo['required'] ?? false) && self::isEmpty($fields[$fieldId] ?? null)) {
                return sprintf('Content type "%s": required field "%s" is empty. Provide a non-empty value.', $identifier, $fieldId);
            }
        }

        return null;
    }

    /**
     * The parent location exists, and is not in another site than the siteaccess's. Locations outside the sites (the
     * media library, the users) belong to no site and are accepted.
     */
    public function parent(int $parentLocationId, ?string $siteaccess = null): ?string
    {
        try {
            $parent = $this->repository->getLocationService()->loadLocation($parentLocationId);
        } catch (NotFoundException) {
            return sprintf('Parent location %d does not exist. Find the right location with tree.browse (or site.explore for a site).', $parentLocationId);
        } catch (UnauthorizedException) {
            return sprintf('You are not allowed to create content under location %d.', $parentLocationId);
        }

        return $this->sameSite($parentLocationId, $parent->getPathString(), $siteaccess);
    }

    /**
     * The language a write is made in, as one the site's siteaccess is served in. Without one, the site's own language;
     * without a siteaccess, the one named (if the repository has it).
     *
     * @return array{0: string|null, 1: string|null} The language (null for the repository default) and the problem
     */
    public function language(?string $requested, ?string $siteaccess = null): array
    {
        $site = $siteaccess !== null && $siteaccess !== '' ? $this->siteLanguages->forSiteaccess($siteaccess) : [];
        if ($requested === null || $requested === '') {
            return [$site[0] ?? null, null];
        }

        $inSite = LanguageCode::resolve($requested, $site);
        if ($inSite !== null) {
            return [$inSite, null];
        }

        $known = $this->languages->resolve($requested);
        if ($known === null) {
            return [null, sprintf('Language "%s" does not exist. Use a language code such as "eng-GB" or "fre-FR".', $requested)];
        }
        if ($site === []) {
            return [$known, null];
        }

        return [null, sprintf(
            'The site of siteaccess "%s" has no "%s" language: it is served in %s. Write in one of these languages, or use the siteaccess of the site\'s %s version if it has one.',
            $siteaccess,
            $requested,
            implode(', ', $site),
            $requested,
        )];
    }

    /** The content exists. */
    public function content(int $contentId): ?string
    {
        try {
            $this->repository->getContentService()->loadContentInfo($contentId);
        } catch (NotFoundException) {
            return sprintf('The content %d does not exist.', $contentId);
        }

        return null;
    }

    /** The attachment is one of the user's, and still kept. */
    public function attachment(string $attachmentId): ?string
    {
        $userId = (int) $this->permissions->getCurrentUserReference()->getUserId();
        if (!AttachmentStore::isValidId($attachmentId) || $this->attachments->find($attachmentId, $userId) === null) {
            return 'That image is not available (it is kept for a week). Ask the user to attach it again.';
        }

        return null;
    }

    private function sameSite(int $parentLocationId, string $parentPath, ?string $siteaccess): ?string
    {
        if ($siteaccess === null || $siteaccess === '') {
            return null;
        }
        $root = $this->locations->rootLocationId($siteaccess);
        if ($root === null || $root <= 0) {
            return null;
        }
        try {
            $rootPath = $this->repository->getLocationService()->loadLocation($root)->getPathString();
        } catch (Throwable) {
            return null;
        }

        $rootParts = array_values(array_filter(explode('/', $rootPath), static fn (string $part): bool => $part !== ''));
        $parentParts = array_values(array_filter(explode('/', $parentPath), static fn (string $part): bool => $part !== ''));

        // The same container of sites (/1/2/...), but another site below it.
        if (isset($rootParts[2], $parentParts[2]) && $rootParts[1] === $parentParts[1] && $rootParts[2] !== $parentParts[2]) {
            return sprintf(
                'Parent location %d is under the site at location %s, but siteaccess "%s" belongs to the site at location %s: they are in different sites. Use a parent under the siteaccess\'s site, or the siteaccess of the parent\'s site.',
                $parentLocationId,
                $parentParts[2],
                $siteaccess,
                $rootParts[2],
            );
        }

        return null;
    }

    /** Null, an empty string or an empty list, at any depth: nothing to save. */
    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            foreach ($value as $entry) {
                if (!self::isEmpty($entry)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
