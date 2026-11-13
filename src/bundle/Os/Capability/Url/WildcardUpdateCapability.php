<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Url;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class WildcardUpdateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.wildcard.update';
    }

    public function summary(): string
    {
        return 'Change a URL wildcard.';
    }

    public function description(): string
    {
        return 'Changes the pattern, the destination and/or the redirect flag of a wildcard; what you leave out stays as it is.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['wildcard_id'], 'properties' => ['wildcard_id' => ['type' => 'integer', 'minimum' => 1], 'source_url' => ['type' => 'string', 'minLength' => 1, 'description' => 'Pattern, e.g. /old/*'], 'destination_url' => ['type' => 'string', 'minLength' => 1, 'description' => 'Where it goes, e.g. /new/{1}'], 'forward' => ['type' => 'boolean']]];
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
        return [['description' => 'Change where a rule goes', 'arguments' => ['wildcard_id' => 3, 'destination_url' => '/news/{1}']]];
    }

    public function validate(array $arguments): ?string
    {
        if (!isset($arguments['source_url']) && !isset($arguments['destination_url']) && !isset($arguments['forward'])) {
            return 'Give something to change: source_url, destination_url or forward.';
        }
        try {
            $this->repository->getURLWildcardService()->load((int) $arguments['wildcard_id']);
        } catch (\Throwable) {
            return sprintf('There is no wildcard %d: see url.wildcard.list.', (int) $arguments['wildcard_id']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Update a URL wildcard', array_values(array_filter([['Wildcard', (string) (int) $arguments['wildcard_id']], isset($arguments['source_url']) ? ['Pattern', (string) $arguments['source_url']] : null, isset($arguments['destination_url']) ? ['Goes to', (string) $arguments['destination_url']] : null, isset($arguments['forward']) ? ['Redirect', $arguments['forward'] ? 'yes' : 'no (rewrite)'] : null])));
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getURLWildcardService();
        $wildcard = $service->load((int) $arguments['wildcard_id']);
        $struct = new \Ibexa\Contracts\Core\Repository\Values\Content\URLWildcardUpdateStruct();
        $struct->sourceUrl = (string) ($arguments['source_url'] ?? $wildcard->sourceUrl);
        $struct->destinationUrl = (string) ($arguments['destination_url'] ?? $wildcard->destinationUrl);
        $struct->forward = (bool) ($arguments['forward'] ?? $wildcard->forward);
        $service->update($wildcard, $struct);

        return CapabilityResult::ok(sprintf('The wildcard %d was updated', $wildcard->id), ['wildcard_id' => $wildcard->id]);
    }
}
