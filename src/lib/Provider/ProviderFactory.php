<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Provider;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Alibaba\DashScopeOpenAI;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;

/**
 * Turns a {@see ProviderConnection} into the matching Neuron provider.
 *
 * Dedicated Neuron classes are used where they exist (OpenAI, Anthropic,
 * Gemini, Mistral, Ollama, Qwen via DashScope). Kimi and MiniMax expose
 * OpenAI-compatible chat-completions, so they go through OpenAILike with
 * their own base URI.
 *
 * Request shaping differs per API and is handled here, so callers only deal
 * with temperature / max tokens:
 * - OpenAI-style bodies take `temperature` and `max_tokens` at top level.
 * - Anthropic takes `max_tokens` as a constructor argument and accepts
 *   temperature only in 0.0–1.0.
 * - Gemini nests both values under `generationConfig`.
 */
final class ProviderFactory
{
    public function create(ProviderConnection $connection): AIProviderInterface
    {
        $this->assertCredentials($connection);

        return match ($connection->provider) {
            ProviderId::OpenAi => $connection->apiUrl === null
                // Custom endpoint on the OpenAI protocol (e.g. an OpenAI-compatible gateway).
                ? new OpenAI(key: $connection->apiKey, model: $connection->model, parameters: $this->openAiParameters($connection))
                : $this->openAiCompatible($connection),
            ProviderId::Anthropic => new Anthropic(
                key: $connection->apiKey,
                model: $connection->model,
                max_tokens: $connection->maxTokens,
                parameters: ['temperature' => min(1.0, $connection->temperature)],
            ),
            // Gemini and Anthropic keep Neuron's endpoints: their base URIs include
            // API paths that a stored host-only URL would break.
            ProviderId::Gemini => new Gemini(
                key: $connection->apiKey,
                model: $connection->model,
                parameters: [
                    'generationConfig' => [
                        'temperature' => $connection->temperature,
                        'maxOutputTokens' => $connection->maxTokens,
                    ],
                ],
            ),
            ProviderId::Mistral => new Mistral(
                key: $connection->apiKey,
                model: $connection->model,
                parameters: $this->openAiParameters($connection),
            ),
            ProviderId::Ollama => new Ollama(
                url: $connection->apiUrl ?? throw new ProviderConfigurationException('Ollama needs an endpoint URL.'),
                model: $connection->model,
                parameters: $this->openAiParameters($connection),
            ),
            ProviderId::Qwen => $this->qwen($connection),
            ProviderId::Kimi, ProviderId::MiniMax, ProviderId::Mimo => $this->openAiCompatible($connection),
        };
    }

    /**
     * OpenAI-protocol endpoint at the configured base URI, or the provider's default.
     */
    private function openAiCompatible(ProviderConnection $connection): OpenAILike
    {
        $baseUri = $connection->apiUrl ?? $connection->provider->defaultOpenAiCompatibleBaseUri()
            ?? throw new ProviderConfigurationException(sprintf('Provider "%s" needs an endpoint URL.', $connection->provider->label()));

        return new OpenAILike(
            baseUri: self::normalizeBaseUri($baseUri),
            key: $connection->apiKey,
            model: $connection->model,
            parameters: $this->openAiParameters($connection),
        );
    }

    /**
     * A host-only URL ("https://api.minimax.io") gets the "/v1" version path that OpenAI-protocol APIs use.
     */
    public static function normalizeBaseUri(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $path === '' ? $url . '/v1' : $url;
    }

    private function qwen(ProviderConnection $connection): DashScopeOpenAI
    {
        $parameters = $this->openAiParameters($connection);

        // Keep Neuron's DashScope default (international compatible-mode endpoint)
        // unless the admin configured a custom URL.
        return $connection->apiUrl === null
            ? new DashScopeOpenAI(key: $connection->apiKey, model: $connection->model, parameters: $parameters)
            : new DashScopeOpenAI(key: $connection->apiKey, model: $connection->model, parameters: $parameters, baseUri: self::normalizeBaseUri($connection->apiUrl));
    }

    /**
     * @return array<string, float|int>
     */
    private function openAiParameters(ProviderConnection $connection): array
    {
        return [
            'temperature' => $connection->temperature,
            'max_tokens' => $connection->maxTokens,
        ];
    }

    private function assertCredentials(ProviderConnection $connection): void
    {
        if ($connection->provider->requiresApiKey() && ($connection->apiKey === null || $connection->apiKey === '')) {
            throw new ProviderConfigurationException(sprintf(
                'Provider "%s" has no API key configured.',
                $connection->provider->label(),
            ));
        }
    }
}
