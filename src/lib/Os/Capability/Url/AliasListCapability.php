<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Url;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\IbexaAi\Ibexa\Site\SiteUrls;
use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class AliasListCapability extends RepositoryCapability
{
    public function __construct(Repository $repository, private readonly SiteUrls $urls)
    {
        parent::__construct($repository);
    }

    public function name(): string
    {
        return 'url.alias.list';
    }

    public function summary(): string
    {
        return 'List the URL aliases of a location, and its real address on each site.';
    }

    public function description(): string
    {
        return 'Returns the system aliases (generated from the names) and the custom ones (added by hand) of a location, with their language. Only custom ones can be removed. \'urls\' holds the address a visitor uses, on each site that shows the location (or on the siteaccess asked for): give that one when asked for the link of a page, and the admin address (admin: true, \'admin_urls\') only when the user asks for the admin link; an alias \'path\' starts at the root of the whole tree and is not a link.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1], 'siteaccess' => ['type' => 'string', 'description' => 'Only the address on this siteaccess'], 'admin' => ['type' => 'boolean', 'description' => 'Also give the address in the admin. Only when the user asks for it.']]];
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
        $location = $this->repository->getLocationService()->loadLocation((int) $arguments['location_id']);
        foreach ($aliases->listLocationAliases($location, false) as $alias) {
            $rows[] = ['alias_id' => $alias->id, 'path' => $alias->path, 'custom' => $alias->isCustom, 'languages' => $alias->languageCodes, 'forward' => $alias->forward];
        }
        foreach ($aliases->listLocationAliases($location, true) as $alias) {
            if (!in_array($alias->id, array_column($rows, 'alias_id'), true)) {
                $rows[] = ['alias_id' => $alias->id, 'path' => $alias->path, 'custom' => $alias->isCustom, 'languages' => $alias->languageCodes, 'forward' => $alias->forward];
            }
        }

        $urls = $this->urls->forLocation($location, isset($arguments['siteaccess']) ? (string) $arguments['siteaccess'] : null);
        $data = ['aliases' => $rows, 'urls' => $urls];
        if (!empty($arguments['admin'])) {
            $admins = [];
            foreach ($urls !== [] ? array_column($urls, 'siteaccess') : [null] as $front) {
                $admin = $this->urls->adminUrl($location, $front);
                if ($admin !== null) {
                    $admins[$admin['url']] = $admin;
                }
            }
            $data['admin_urls'] = array_values($admins);
        }

        return CapabilityResult::ok(sprintf('%d alias(es), %d address(es)', count($rows), count($urls)), $data);
    }
}
