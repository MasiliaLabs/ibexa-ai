<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Url;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class WildcardCreateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.wildcard.create';
    }

    public function summary(): string
    {
        return 'Create a URL wildcard (redirect or rewrite).';
    }

    public function description(): string
    {
        return 'Creates the rule. forward=true redirects visitors (use it for moved pages); forward=false rewrites the URL without a redirect.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['source_url', 'destination_url'], 'properties' => ['source_url' => ['type' => 'string', 'minLength' => 1, 'description' => 'Pattern, e.g. /old/*'], 'destination_url' => ['type' => 'string', 'minLength' => 1, 'description' => 'Where it goes, e.g. /new/{1}'], 'forward' => ['type' => 'boolean']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'urltranslator']];
    }

    public function examples(): array
    {
        return [['description' => 'Redirect an old section', 'arguments' => ['source_url' => '/old/*', 'destination_url' => '/new/{1}', 'forward' => true]]];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a URL wildcard', [['Pattern', (string) $arguments['source_url']], ['Goes to', (string) $arguments['destination_url']], ['Redirect', ($arguments['forward'] ?? false) ? 'yes' : 'no (rewrite)']]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $wildcard = $this->repository->getURLWildcardService()->create((string) $arguments['source_url'], (string) $arguments['destination_url'], (bool) ($arguments['forward'] ?? false));

        return CapabilityResult::ok(sprintf('The wildcard "%s" was created', $wildcard->sourceUrl), ['wildcard_id' => $wildcard->id]);
    }
}
