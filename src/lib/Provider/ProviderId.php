<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Provider;

/**
 * Providers supported by the assistant. Values are the identifiers stored in
 * `ibexa_ai_provider.identifier` and used in YAML configuration.
 */
enum ProviderId: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case Qwen = 'qwen';
    case Kimi = 'kimi';
    case MiniMax = 'minimax';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::Gemini => 'Google Gemini',
            self::Mistral => 'Mistral',
            self::Ollama => 'Ollama',
            self::Qwen => 'Qwen (Alibaba DashScope)',
            self::Kimi => 'Kimi (Moonshot)',
            self::MiniMax => 'MiniMax',
        };
    }

    /**
     * Whether the provider needs an API key. Local Ollama does not.
     */
    public function requiresApiKey(): bool
    {
        return $this !== self::Ollama;
    }

    /**
     * OpenAI-compatible endpoint used when no custom URL is configured.
     * Null means the provider has a dedicated Neuron class with its own default.
     */
    public function defaultOpenAiCompatibleBaseUri(): ?string
    {
        return match ($this) {
            self::Kimi => 'https://api.moonshot.ai/v1',
            self::MiniMax => 'https://api.minimax.io/v1',
            default => null,
        };
    }
}
