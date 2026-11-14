<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

/** What "Publish" does in the editing form of the admin: a draft becomes the published version. */
final class PublishCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'content.publish';
    }

    public function summary(): string
    {
        return 'Publish a draft of a content.';
    }

    public function description(): string
    {
        return 'Publishes the draft version_no of the content (by default its latest draft, as started by content.draft.create): it becomes the published version and is shown on the site.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1], 'version_no' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'publish']];
    }

    public function examples(): array
    {
        return [['description' => 'Publish the draft of a page', 'arguments' => ['content_id' => 1353]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->draft($arguments);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        try {
            $draft = $this->draft($arguments);

            return new Preview('Publish a draft', [['Content', sprintf('%s (content %d)', $draft->getContentInfo()->name, $draft->getContentInfo()->id)], ['Draft', (string) $draft->versionNo]], ['The draft replaces the published version on the site.']);
        } catch (Throwable) {
            return new Preview('Publish a draft', [['Content', (string) (int) $arguments['content_id']]]);
        }
    }

    public function execute(array $arguments): CapabilityResult
    {
        $draft = $this->draft($arguments);
        $published = $this->repository->getContentService()->publishVersion($draft);

        return CapabilityResult::ok(sprintf('"%s" was published (version %d)', $published->getContentInfo()->name, $published->getVersionInfo()->getVersionNo()), ['content_id' => $published->getContentInfo()->id, 'version_no' => $published->getVersionInfo()->getVersionNo()]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function draft(array $arguments): VersionInfo
    {
        $service = $this->repository->getContentService();
        try {
            $info = $service->loadContentInfo((int) $arguments['content_id']);
        } catch (Throwable) {
            throw new \RuntimeException(sprintf('The content %d does not exist.', (int) $arguments['content_id']));
        }
        $drafts = $service->loadVersions($info, VersionInfo::STATUS_DRAFT);
        if ($drafts === []) {
            throw new \RuntimeException(sprintf('"%s" has no draft to publish. Start one with content.draft.create.', $info->name));
        }
        if (isset($arguments['version_no'])) {
            foreach ($drafts as $draft) {
                if ($draft->versionNo === (int) $arguments['version_no']) {
                    return $draft;
                }
            }

            throw new \RuntimeException(sprintf('"%s" has no draft %d.', $info->name, (int) $arguments['version_no']));
        }
        usort($drafts, static fn (VersionInfo $a, VersionInfo $b): int => $b->versionNo <=> $a->versionNo);

        return $drafts[0];
    }
}
