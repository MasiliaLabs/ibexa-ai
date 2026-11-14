<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class DraftCreateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'content.draft.create';
    }

    public function summary(): string
    {
        return 'Start a new draft of a content from its published version.';
    }

    public function description(): string
    {
        return 'Creates a draft (a copy of the published version, or of version_no) that can then be edited. Nothing is published. The answer has version_no.';
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
        return [['content', 'edit']];
    }

    public function examples(): array
    {
        return [['description' => 'Start editing a page', 'arguments' => ['content_id' => 1353]]];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Start a draft', [['Content', (string) (int) $arguments['content_id']]], ['Nothing is published until the draft is.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentService();
        $info = $service->loadContentInfo((int) $arguments['content_id']);
        $from = isset($arguments['version_no']) ? $service->loadVersionInfoById($info->id, (int) $arguments['version_no']) : null;
        $draft = $service->createContentDraft($info, $from);

        return CapabilityResult::ok(sprintf('Draft %d of "%s" started', $draft->getVersionInfo()->versionNo, $info->name), ['content_id' => $info->id, 'version_no' => $draft->getVersionInfo()->versionNo]);
    }
}
