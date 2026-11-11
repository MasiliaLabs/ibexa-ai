<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Llm;

use Masilia\IbexaAi\Llm\TextGenerator;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Provider\ProviderConnection;
use Masilia\IbexaAi\Provider\ProviderFactory;
use Masilia\IbexaAi\Provider\ProviderId;

/**
 * Builds the text generator from the bundle's connection settings.
 *
 * Settings come from the container parameter `ibexa_ai.connection`
 * (keys: provider, model, api_key, api_url, temperature, max_tokens).
 * Per-siteaccess resolution from the admin and the database is the next
 * step of the provider phase; this class is the single place that will change.
 */
final readonly class ConfiguredTextGeneratorFactory
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private array $settings,
        private ProviderFactory $providers = new ProviderFactory(),
    ) {
    }

    /**
     * @throws ProviderConfigurationException when no usable connection is configured
     */
    public function create(): TextGenerator
    {
        $providerName = $this->settings['provider'] ?? null;
        if (!is_string($providerName) || ProviderId::tryFrom($providerName) === null) {
            throw new ProviderConfigurationException('No AI provider is configured for this siteaccess.');
        }

        $connection = new ProviderConnection(
            provider: ProviderId::from($providerName),
            model: (string) ($this->settings['model'] ?? ''),
            apiKey: isset($this->settings['api_key']) ? (string) $this->settings['api_key'] : null,
            apiUrl: isset($this->settings['api_url']) ? (string) $this->settings['api_url'] : null,
            temperature: (float) ($this->settings['temperature'] ?? 0.7),
            maxTokens: (int) ($this->settings['max_tokens'] ?? 4096),
        );

        return new NeuronTextGenerator($this->providers->create($connection));
    }
}
