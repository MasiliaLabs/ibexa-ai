<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Workspace;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class DraftsDeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.drafts.delete';
    }

    public function summary(): string
    {
        return 'Discard a draft: the edits in it are lost.';
    }

    public function description(): string
    {
        return 'Deletes a draft version for good. The published version is not touched; a content that has only this draft is deleted with it.';
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
        return [['description' => 'Discard a draft', 'arguments' => ['content_id' => 1353, 'version_no' => 3]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $version = $this->repository->getContentService()->loadVersionInfoById((int) $arguments['content_id'], (int) $arguments['version_no']);
        } catch (\Throwable) {
            return sprintf('The content %d has no version %d.', (int) $arguments['content_id'], (int) $arguments['version_no']);
        }

        return $version->isDraft() ? null : 'Only a draft can be discarded: that version is not a draft.';
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Discard a draft', [['Content', (string) (int) $arguments['content_id']], ['Version', (string) (int) $arguments['version_no']]], ['The edits in the draft are lost. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentService();
        $service->deleteVersion($service->loadVersionInfoById((int) $arguments['content_id'], (int) $arguments['version_no']));

        return CapabilityResult::ok(sprintf('Draft %d of content %d was discarded', (int) $arguments['version_no'], (int) $arguments['content_id']), []);
    }
}
