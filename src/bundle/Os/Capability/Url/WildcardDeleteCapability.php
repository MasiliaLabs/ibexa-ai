<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Url;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class WildcardDeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.wildcard.delete';
    }

    public function summary(): string
    {
        return 'Delete a URL wildcard.';
    }

    public function description(): string
    {
        return 'Deletes the rule: the URLs it handled answer "not found" again.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['wildcard_id'], 'properties' => ['wildcard_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['content', 'urltranslator']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete a redirect rule', 'arguments' => ['wildcard_id' => 3]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->repository->getURLWildcardService()->load((int) $arguments['wildcard_id']);
        } catch (\Throwable) {
            return sprintf('There is no wildcard %d: see url.wildcard.list.', (int) $arguments['wildcard_id']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a URL wildcard', [['Wildcard', (string) (int) $arguments['wildcard_id']]], ['The URLs it handled stop working. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getURLWildcardService();
        $wildcard = $service->load((int) $arguments['wildcard_id']);
        $service->remove($wildcard);

        return CapabilityResult::ok(sprintf('The wildcard "%s" was deleted', $wildcard->sourceUrl), []);
    }
}
