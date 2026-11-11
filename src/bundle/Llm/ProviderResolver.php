<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Llm;

use Masilia\Bundle\IbexaAi\Repository\AiProviderRepository;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Provider\ProviderConnection;
use Masilia\IbexaAi\Provider\ProviderId;

/**
 * Chooses the chat connection for a siteaccess, in the same order as the previous package:
 *  1. the provider assigned to the siteaccess in the admin, with its active chat model;
 *  2. the YAML connection (masilia_ibexa_ai.connection), which reads the AI_* environment variables.
 */
final readonly class ProviderResolver
{
    /**
     * @param array<string, mixed> $settings the YAML connection block
     */
    public function __construct(
        private AiProviderRepository $providers,
        private array $settings,
    ) {
    }

    /**
     * @throws ProviderConfigurationException when neither the admin nor the YAML configure a usable provider
     */
    public function resolveChat(string $siteaccess): ProviderConnection
    {
        $provider = $this->providers->findActiveChatForSiteaccess($siteaccess);
        $model = $provider?->getActiveChatModel();

        if ($provider !== null && $model !== null) {
            return new ProviderConnection(
                provider: $this->providerId($provider->getIdentifier()),
                model: $model->getIdentifier(),
                apiKey: $provider->getApiKey(),
                apiUrl: $provider->getApiUrl(),
                temperature: $model->getTemperature(),
                maxTokens: $model->getMaxTokens(),
            );
        }

        return $this->fromSettings();
    }

    private function fromSettings(): ProviderConnection
    {
        $name = $this->settings['provider'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new ProviderConfigurationException('No AI provider is configured for this siteaccess.');
        }

        return new ProviderConnection(
            provider: $this->providerId($name),
            model: (string) ($this->settings['model'] ?? ''),
            apiKey: isset($this->settings['api_key']) && $this->settings['api_key'] !== '' ? (string) $this->settings['api_key'] : null,
            apiUrl: isset($this->settings['api_url']) && $this->settings['api_url'] !== '' ? (string) $this->settings['api_url'] : null,
            temperature: (float) ($this->settings['temperature'] ?? 0.7),
            maxTokens: (int) ($this->settings['max_tokens'] ?? 4096),
        );
    }

    private function providerId(string $identifier): ProviderId
    {
        return ProviderId::tryFrom($identifier)
            ?? throw new ProviderConfigurationException(sprintf('Provider "%s" is not supported.', $identifier));
    }
}
