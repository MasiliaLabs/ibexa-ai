<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Content\Write;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\IbexaAi\Chat\Attachment\AttachmentStore;
use Masilia\IbexaAi\Ibexa\Site\SiteLanguages;
use Masilia\Contracts\IbexaAi\Content\ContentTypeSchemas;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\IbexaAi\Content\LanguageCode;
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
        private ConfigResolverInterface $config,
    ) {
    }

    /**
     * The content type exists and its matrix fields have the columns it has. What Ibexa itself refuses (a required field left empty, a
     * value outside the limits of its field type) is found by ContentWriter::validateCreate() and ::validateUpdate().
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

    /**
     * The admin does not create a user with the content form: it has a user form of its own (the access.user.* capabilities, in the
     * "access" domain, which is off until the project turns it on).
     */
    public function notAUserType(string $identifier): ?string
    {
        // Ibexa 5 configures a list of content types for the users (['user']).
        if (!in_array($identifier, array_map('strval', (array) $this->config->getParameter('user_content_type_identifier')), true)) {
            return null;
        }

        return sprintf('"%s" is the content type of the users: they are created with access.user.create (the admin has a user form for it), not as a content.', $identifier);
    }

    /**
     * Nor does it change or remove a user as a content: users are changed with access.user.update and removed with access.user.delete.
     *
     * @param string $instead The capability to use, e.g. "access.user.update"
     */
    public function notAUser(int $contentId, string $instead): ?string
    {
        try {
            $content = $this->repository->getContentService()->loadContent($contentId);
        } catch (NotFoundException) {
            return null;
        }

        return $this->repository->getUserService()->isUser($content)
            ? sprintf('Content %d is a user: use %s (the admin has a user form for it), not a content change.', $contentId, $instead)
            : null;
    }

    /**
     * The user's role lets them create a content of this type under the parent (the limitations of content/create: a subtree, a
     * section, a type). Said before the card is shown, so that the user is not asked to approve what Ibexa will refuse.
     */
    public function mayCreate(string $type, int $parentLocationId): ?string
    {
        try {
            $contentType = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($type);
            $struct = $this->repository->getContentService()->newContentCreateStruct($contentType, $this->repository->getContentLanguageService()->getDefaultLanguageCode());
            $target = $this->repository->getLocationService()->newLocationCreateStruct($parentLocationId);
            $allowed = $this->permissions->canUser('content', 'create', $struct, [$target]);
        } catch (Throwable) {
            return null; // what cannot be asked is left to the repository, which refuses at the write
        }

        return $allowed ? null : sprintf('Your role does not let you create a "%s" under location %d.', $type, $parentLocationId);
    }

    /** The user's role lets them edit the content. */
    public function mayEdit(int $contentId): ?string
    {
        try {
            $allowed = $this->permissions->canUser('content', 'edit', $this->repository->getContentService()->loadContentInfo($contentId));
        } catch (Throwable) {
            return null;
        }

        return $allowed ? null : sprintf('Your role does not let you edit the content %d.', $contentId);
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
}
