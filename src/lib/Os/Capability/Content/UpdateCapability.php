<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\IbexaAi\Ibexa\Content\Write\WriteChecks;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Ibexa\AdminUi\Specification\Content\ContentDraftHasConflict;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

final class UpdateCapability extends AbstractCapability
{
    public function __construct(
        private readonly WriteChecks $checks,
        private readonly ContentWriter $writer,
        private readonly Languages $languages,
        private readonly Repository $repository,
    ) {
    }

    public function name(): string
    {
        return 'content.update';
    }

    public function summary(): string
    {
        return 'Change the fields of a content, in one language, and publish it.';
    }

    public function description(): string
    {
        return 'Sets the given fields of an existing content in one language and publishes the new version. Only the fields given change. '
            . 'Without a language, the content is changed in its own main language. To write a translation that does not exist yet, '
            . 'give the language: it is created, and the other languages are left as they are. Field values follow the formats of the '
            . 'content type (read the content first to see them).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_id', 'fields'],
            'properties' => [
                'content_id' => ['type' => 'integer', 'minimum' => 1],
                'fields' => ['type' => 'object', 'description' => 'Field identifier => new value'],
                'language' => ['type' => 'string', 'description' => 'Language code, e.g. "fre-FR"'],
                'publish' => ['type' => 'boolean', 'description' => 'false to leave the changes in a new draft ("Save draft"); publish it later with content.publish. Default true.'],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'edit'], ['content', 'publish']];
    }

    public function examples(): array
    {
        return [['description' => 'Retitle a page', 'arguments' => ['content_id' => 1353, 'fields' => ['title' => 'New title']]]];
    }

    public function preview(array $arguments): Preview
    {
        $id = (int) $arguments['content_id'];
        try {
            $name = (string) $this->repository->getContentService()->loadContentInfo($id)->getName();
        } catch (Throwable) {
            $name = '';
        }

        $notes = [(bool) ($arguments['publish'] ?? true) ? 'A new version is published.' : 'The changes are kept in a draft: nothing is shown until it is published.'];
        if ($this->hasNewerDraft($id, isset($arguments['language']) ? (string) $arguments['language'] : null)) {
            // The admin asks before it lets two drafts of one language go on at the same time: the one published last wins.
            $notes[] = 'Another draft of this content exists in this language (see workspace.drafts.list): publishing this change leaves it out of date.';
        }

        return new Preview('Update content', [
            ['Content', $name !== '' ? sprintf('%s (%d)', $name, $id) : (string) $id],
            ['Language', isset($arguments['language']) ? (string) $arguments['language'] : 'its own language'],
            ['Fields', implode(', ', array_map('strval', array_keys((array) $arguments['fields'])))],
        ], $notes);
    }

    public function validate(array $arguments): ?string
    {
        $id = (int) $arguments['content_id'];
        if (($problem = $this->checks->content($id)) !== null) {
            return $problem;
        }
        if (($problem = $this->checks->notAUser($id, 'access.user.update')) !== null) {
            return $problem;
        }
        if (isset($arguments['language']) && $this->languages->resolve((string) $arguments['language']) === null) {
            return sprintf('Language "%s" does not exist. Use a language code such as "eng-GB" or "fre-FR".', $arguments['language']);
        }

        return null;
    }

    public function execute(array $arguments): CapabilityResult
    {
        $id = (int) $arguments['content_id'];
        // Without a language the update stays in the language of the content, not in the repository default.
        $language = isset($arguments['language'])
            ? $this->languages->resolve((string) $arguments['language'])
            : $this->repository->getContentService()->loadContentInfo($id)->getMainLanguageCode();
        try {
            $published = $this->writer->update($id, (array) $arguments['fields'], (string) $language, (bool) ($arguments['publish'] ?? true));
        } catch (Throwable $e) {
            return CapabilityResult::error(sprintf('Update of content %d failed: %s', $id, $e->getMessage()));
        }

        return CapabilityResult::ok(sprintf('%s content %d', ($arguments['publish'] ?? true) ? 'Updated' : 'Saved a draft of', $id) . (($arguments['publish'] ?? true) ? '' : ': publish it with content.publish'), [
            'content_id' => $published->getId(),
            'version_no' => $published->getVersionInfo()->getVersionNo(),
        ]);
    }

    /** The admin's ContentDraftHasConflict: a draft newer than the published version, started in the same language. */
    private function hasNewerDraft(int $contentId, ?string $language): bool
    {
        try {
            $contents = $this->repository->getContentService();
            $info = $contents->loadContentInfo($contentId);
            $code = $language !== null ? ($this->languages->resolve($language) ?? $language) : $info->getMainLanguageCode();

            return (new ContentDraftHasConflict($contents, $code))->isSatisfiedBy($info);
        } catch (Throwable) {
            return false;
        }
    }
}
