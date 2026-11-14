<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Url;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class AliasListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.alias.list';
    }

    public function summary(): string
    {
        return 'List the URL aliases of a location.';
    }

    public function description(): string
    {
        return 'Returns the system aliases (generated from the names) and the custom ones (added by hand) of a location, with their language. Only custom ones can be removed.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1]]];
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
        return [['description' => 'The URLs of a page', 'arguments' => ['location_id' => 1364]]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $aliases = $this->repository->getURLAliasService();
        $rows = [];
        foreach ($aliases->listLocationAliases($this->repository->getLocationService()->loadLocation((int) $arguments['location_id']), false) as $alias) {
            $rows[] = ['alias_id' => $alias->id, 'path' => $alias->path, 'custom' => $alias->isCustom, 'languages' => $alias->languageCodes, 'forward' => $alias->forward];
        }
        foreach ($aliases->listLocationAliases($this->repository->getLocationService()->loadLocation((int) $arguments['location_id']), true) as $alias) {
            if (!in_array($alias->id, array_column($rows, 'alias_id'), true)) {
                $rows[] = ['alias_id' => $alias->id, 'path' => $alias->path, 'custom' => $alias->isCustom, 'languages' => $alias->languageCodes, 'forward' => $alias->forward];
            }
        }

        return CapabilityResult::ok(sprintf('%d alias(es)', count($rows)), ['aliases' => $rows]);
    }
}
