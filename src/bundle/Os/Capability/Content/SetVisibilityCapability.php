<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

/** What "Hide" and "Reveal" do in the admin on a content: it is hidden or shown in every one of its locations at once. */
final class SetVisibilityCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'content.set_visibility';
    }

    public function summary(): string
    {
        return 'Hide a content from the site, or show it again, in all its locations.';
    }

    public function description(): string
    {
        return 'Hides the content (visible = false) or reveals it (visible = true) everywhere it is placed. To hide it in a single location only, use location.set_visibility.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'visible'], 'properties' => [
            'content_id' => ['type' => 'integer', 'minimum' => 1],
            'visible' => ['type' => 'boolean', 'description' => 'true to show it, false to hide it'],
        ]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'hide']];
    }

    public function examples(): array
    {
        return [['description' => 'Hide a page', 'arguments' => ['content_id' => 1353, 'visible' => false]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $info = $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id']);
        } catch (Throwable) {
            return sprintf('The content %d does not exist.', (int) $arguments['content_id']);
        }
        $visible = (bool) $arguments['visible'];
        if ($visible && !$info->isHidden) {
            return sprintf('"%s" is already visible.', $info->name);
        }
        if (!$visible && $info->isHidden) {
            return sprintf('"%s" is already hidden.', $info->name);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        $visible = (bool) $arguments['visible'];

        return new Preview($visible ? 'Show a content' : 'Hide a content', [['Content', $this->label((int) $arguments['content_id'])]], [
            $visible ? 'It is shown again in all its locations.' : 'It disappears from the site in all its locations until it is shown again.',
        ]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentService();
        $info = $service->loadContentInfo((int) $arguments['content_id']);
        if ((bool) $arguments['visible']) {
            $service->revealContent($info);
        } else {
            $service->hideContent($info);
        }

        return CapabilityResult::ok(sprintf('"%s" is now %s', $info->name, (bool) $arguments['visible'] ? 'visible' : 'hidden'), ['content_id' => $info->id, 'visible' => (bool) $arguments['visible']]);
    }

    private function label(int $id): string
    {
        try {
            return sprintf('%s (content %d)', $this->repository->getContentService()->loadContentInfo($id)->name, $id);
        } catch (Throwable) {
            return sprintf('content %d', $id);
        }
    }
}
