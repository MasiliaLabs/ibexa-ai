<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Url;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class WildcardListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.wildcard.list';
    }

    public function summary(): string
    {
        return 'List the URL wildcards (redirect and rewrite rules).';
    }

    public function description(): string
    {
        return 'A wildcard maps a pattern like /old/* to a destination like /new/{1}; forward=true makes it a redirect, otherwise the URL is rewritten silently.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200]]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'urltranslator']];
    }

    public function examples(): array
    {
        return [['description' => 'All wildcards', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getURLWildcardService();
        $rows = [];
        foreach ($service->loadAll(0, (int) ($arguments['limit'] ?? 100)) as $wildcard) {
            $rows[] = ['wildcard_id' => $wildcard->id, 'source_url' => $wildcard->sourceUrl, 'destination_url' => $wildcard->destinationUrl, 'forward' => $wildcard->forward];
        }

        return CapabilityResult::ok(sprintf('%d wildcard(s)', $service->countAll()), ['wildcards' => $rows, 'total' => $service->countAll()]);
    }
}
