<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Provider;

use Masilia\Contracts\IbexaAi\Provider\ChatConnection;
use Masilia\Contracts\IbexaAi\Provider\ChatProviders;
use Masilia\IbexaAi\Provider\ProviderFactory;

/** {@see ChatProviders} from the admin settings and the bundle configuration. */
final readonly class SiteaccessChatProviders implements ChatProviders
{
    public function __construct(
        private ProviderResolver $resolver,
        private ProviderFactory $factory,
    ) {
    }

    public function forSiteaccess(string $siteaccess): ChatConnection
    {
        $connection = $this->resolver->resolveChat($siteaccess);

        return new ChatConnection(
            $this->factory->create($connection),
            $connection->provider->value,
            $connection->model,
            $connection->supportsVision,
        );
    }
}
