<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Url;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class LinkUpdateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.link.update';
    }

    public function summary(): string
    {
        return 'Change an external link everywhere it is used.';
    }

    public function description(): string
    {
        return 'Replaces the URL in every content that uses it, without a new version of those contents.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['url_id', 'url'], 'properties' => ['url_id' => ['type' => 'integer', 'minimum' => 1], 'url' => ['type' => 'string', 'minLength' => 1, 'description' => 'The new address']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['url', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Fix a moved link', 'arguments' => ['url_id' => 12, 'url' => 'https://example.org/new']]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->repository->getURLService()->loadById((int) $arguments['url_id']);
        } catch (\Throwable) {
            return sprintf('There is no link %d: see url.link.list.', (int) $arguments['url_id']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Change a link', [['Link', (string) (int) $arguments['url_id']], ['New address', (string) $arguments['url']]], ['It changes in every content that uses it.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getURLService();
        $struct = $service->createUpdateStruct();
        $struct->url = (string) $arguments['url'];
        $url = $service->updateUrl($service->loadById((int) $arguments['url_id']), $struct);

        return CapabilityResult::ok(sprintf('The link is now "%s"', $url->url), ['url_id' => $url->id]);
    }
}
