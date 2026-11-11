<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Llm;

use Masilia\Bundle\IbexaAi\Observability\DoctrineRequestLogger;
use Masilia\IbexaAi\Llm\TextGenerator;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Provider\ProviderFactory;

/**
 * Builds the text generator for the current siteaccess. One instance per request, so the
 * system prompt set on the provider never leaks between requests.
 */
final readonly class TextGeneratorFactory
{
    public function __construct(
        private ProviderResolver $resolver,
        private DoctrineRequestLogger $logger,
        private ProviderFactory $providers = new ProviderFactory(),
    ) {
    }

    /**
     * @throws ProviderConfigurationException
     */
    public function create(string $siteaccess): TextGenerator
    {
        $connection = $this->resolver->resolveChat($siteaccess);

        return new NeuronTextGenerator(
            provider: $this->providers->create($connection),
            logger: $this->logger,
            providerIdentifier: $connection->provider->value,
            modelIdentifier: $connection->model,
            siteaccess: $siteaccess,
        );
    }
}
