<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Url;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class LinkListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'url.link.list';
    }

    public function summary(): string
    {
        return 'List the external links used in contents, and whether they work.';
    }

    public function description(): string
    {
        return 'The link manager keeps every URL used in link fields and rich text. Returns url_id, the URL and whether it was found valid at the last check.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'only_broken' => ['type' => 'boolean']]];
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
        return [['description' => 'Broken links', 'arguments' => ['only_broken' => true]]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $query = new \Ibexa\Contracts\Core\Repository\Values\URL\URLQuery();
        $query->filter = ($arguments['only_broken'] ?? false) ? new \Ibexa\Contracts\Core\Repository\Values\URL\Query\Criterion\Validity(false) : new \Ibexa\Contracts\Core\Repository\Values\URL\Query\Criterion\MatchAll();
        $query->limit = (int) ($arguments['limit'] ?? 50);
        $result = $this->repository->getURLService()->findUrls($query);
        $rows = [];
        foreach ($result->items as $url) {
            $rows[] = ['url_id' => $url->id, 'url' => $url->url, 'valid' => $url->isValid];
        }

        return CapabilityResult::ok(sprintf('%d link(s)', $result->totalCount), ['links' => $rows, 'total' => $result->totalCount]);
    }
}
