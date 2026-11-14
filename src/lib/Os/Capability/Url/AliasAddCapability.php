<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Url;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class AliasAddCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.alias.add';
    }

    public function summary(): string
    {
        return 'Add a custom URL to a location.';
    }

    public function description(): string
    {
        return 'The location then also answers at that path (forwarding=true makes the other URLs of the location redirect to it). The path must not be taken.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id', 'path', 'language_code'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1], 'path' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'description' => 'The URL path, e.g. /press/kit'], 'language_code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'description' => 'Language code, e.g. fre-FR'], 'forwarding' => ['type' => 'boolean']]];
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
        return [['description' => 'A short URL for a section', 'arguments' => ['location_id' => 1364, 'path' => '/press', 'language_code' => 'eng-GB']]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->repository->getLocationService()->loadLocation((int) $arguments['location_id']);
        } catch (\Throwable) {
            return sprintf('The location %d does not exist.', (int) $arguments['location_id']);
        }
        try {
            $existing = $this->repository->getURLAliasService()->lookup((string) $arguments['path'], (string) $arguments['language_code']);

            return sprintf('The path "%s" is already taken (alias %s).', $arguments['path'], $existing->id);
        } catch (\Throwable) {
            return null;
        }
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Add a URL', [['Path', (string) $arguments['path']], ['Location', (string) (int) $arguments['location_id']], ['Language', (string) $arguments['language_code']], ['Redirects the others', ($arguments['forwarding'] ?? false) ? 'yes' : 'no']]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $alias = $this->repository->getURLAliasService()->createUrlAlias(
            $this->repository->getLocationService()->loadLocation((int) $arguments['location_id']),
            (string) $arguments['path'],
            (string) $arguments['language_code'],
            (bool) ($arguments['forwarding'] ?? false),
        );

        return CapabilityResult::ok(sprintf('The URL "%s" was added', $alias->path), ['alias_id' => $alias->id]);
    }
}
