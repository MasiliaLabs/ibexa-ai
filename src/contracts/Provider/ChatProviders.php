<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Provider;

/**
 * The AI provider of each siteaccess: the one assigned to it in the admin (with its active chat model), or the connection
 * of the bundle configuration. What other packages type-hint to talk to a model without knowing where it is configured.
 */
interface ChatProviders
{
    /**
     * @throws ProviderConfigurationException When nothing usable is configured for the siteaccess
     */
    public function forSiteaccess(string $siteaccess): ChatConnection;
}
