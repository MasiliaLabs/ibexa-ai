<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class VersionDeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'content.version.delete';
    }

    public function summary(): string
    {
        return 'Delete an old (archived) version of a content.';
    }

    public function description(): string
    {
        return 'Deletes an archived version for good. The published version and drafts are not touched by this: discard drafts with workspace.drafts.delete.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'version_no'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1], 'version_no' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['content', 'versionremove']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete an old version', 'arguments' => ['content_id' => 1353, 'version_no' => 2]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $version = $this->repository->getContentService()->loadVersionInfoById((int) $arguments['content_id'], (int) $arguments['version_no']);
        } catch (\Throwable) {
            return sprintf('The content %d has no version %d.', (int) $arguments['content_id'], (int) $arguments['version_no']);
        }

        return $version->isArchived() ? null : 'Only an archived version can be deleted here: that one is ' . ($version->isPublished() ? 'the published version.' : 'a draft (use workspace.drafts.delete).');
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a version', [['Content', (string) (int) $arguments['content_id']], ['Version', (string) (int) $arguments['version_no']]], ['The version is deleted for good. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentService();
        $service->deleteVersion($service->loadVersionInfoById((int) $arguments['content_id'], (int) $arguments['version_no']));

        return CapabilityResult::ok(sprintf('Version %d of content %d was deleted', (int) $arguments['version_no'], (int) $arguments['content_id']), []);
    }
}
