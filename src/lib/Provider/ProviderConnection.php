<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Provider;

use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;

/**
 * Framework-agnostic description of how to reach one chat model.
 * Built from the admin configuration (DB, then YAML, then env) by the bundle.
 */
final readonly class ProviderConnection
{
    public function __construct(
        public ProviderId $provider,
        public string $model,
        public ?string $apiKey = null,
        public ?string $apiUrl = null,
        public float $temperature = 0.7,
        public int $maxTokens = 4096,
        /** Whether the model can read images sent in a message. */
        public bool $supportsVision = false,
    ) {
        if ($model === '') {
            throw new ProviderConfigurationException('A model identifier is required.');
        }
        if ($temperature < 0.0 || $temperature > 2.0) {
            throw new ProviderConfigurationException(sprintf('Temperature %.2f is outside 0.0–2.0.', $temperature));
        }
        if ($maxTokens < 1) {
            throw new ProviderConfigurationException('max_tokens must be at least 1.');
        }
    }
}
