<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Url;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class LinkUsagesCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.link.usages';
    }

    public function summary(): string
    {
        return 'List the contents that use an external link.';
    }

    public function description(): string
    {
        return 'Give the url_id from url.link.list. Returns the contents that contain the link: content_id and name.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['url_id'], 'properties' => ['url_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['url', 'view']];
    }

    public function examples(): array
    {
        return [['description' => 'Where a link is used', 'arguments' => ['url_id' => 12]]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getURLService();
        $usages = $service->findUsages($service->loadById((int) $arguments['url_id']), 0, 50);
        $rows = [];
        foreach ($usages->items as $info) {
            $rows[] = ['content_id' => $info->id, 'name' => $info->name];
        }

        return CapabilityResult::ok(sprintf('Used by %d content(s)', $usages->totalCount), ['contents' => $rows, 'total' => $usages->totalCount]);
    }
}
