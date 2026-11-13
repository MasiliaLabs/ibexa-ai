<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Workspace;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class DraftsListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.drafts.list';
    }

    public function summary(): string
    {
        return 'List the drafts of the user (unpublished edits in progress).';
    }

    public function description(): string
    {
        return 'Returns each draft with its content_id, version_no, name, language and last change. Use workspace.drafts.delete to discard one.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'My drafts', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $list = $this->repository->getContentService()->loadContentDraftList(null, 0, (int) ($arguments['limit'] ?? 25));
        $rows = [];
        foreach ($list->items as $item) {
            $version = $item->getVersionInfo();
            if ($version === null) {
                continue;
            }
            $rows[] = ['content_id' => $version->contentInfo->id, 'version_no' => $version->versionNo, 'name' => $version->getName(), 'language' => $version->initialLanguageCode, 'modified' => $version->modificationDate->format('Y-m-d H:i')];
        }

        return CapabilityResult::ok(sprintf('%d draft(s)', $list->totalCount), ['drafts' => $rows, 'total' => $list->totalCount]);
    }
}
