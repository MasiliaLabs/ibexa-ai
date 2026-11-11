<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Provider;

use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Provider\ProviderConnection;
use Masilia\IbexaAi\Provider\ProviderFactory;
use Masilia\IbexaAi\Provider\ProviderId;
use NeuronAI\Providers\Alibaba\DashScopeOpenAI;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderFactory::class)]
final class ProviderFactoryTest extends TestCase
{
    #[DataProvider('dedicatedProviders')]
    public function testBuildsDedicatedNeuronProvider(ProviderId $id, string $expectedClass): void
    {
        $provider = (new ProviderFactory())->create(new ProviderConnection(
            provider: $id,
            model: 'test-model',
            apiKey: 'test-key',
        ));

        self::assertInstanceOf($expectedClass, $provider);
    }

    public static function dedicatedProviders(): iterable
    {
        yield 'openai' => [ProviderId::OpenAi, OpenAI::class];
        yield 'anthropic' => [ProviderId::Anthropic, Anthropic::class];
        yield 'gemini' => [ProviderId::Gemini, Gemini::class];
        yield 'mistral' => [ProviderId::Mistral, Mistral::class];
        yield 'qwen' => [ProviderId::Qwen, DashScopeOpenAI::class];
    }

    public function testOllamaNeedsNoKeyButNeedsAnEndpoint(): void
    {
        $factory = new ProviderFactory();

        self::assertInstanceOf(Ollama::class, $factory->create(new ProviderConnection(
            provider: ProviderId::Ollama,
            model: 'llama3',
            apiUrl: 'http://localhost:11434/api',
        )));

        $this->expectException(ProviderConfigurationException::class);
        $factory->create(new ProviderConnection(provider: ProviderId::Ollama, model: 'llama3'));
    }

    #[DataProvider('openAiCompatibleProviders')]
    public function testKimiAndMiniMaxUseOpenAiCompatibleEndpoint(ProviderId $id, string $expectedBaseUri): void
    {
        $provider = (new ProviderFactory())->create(new ProviderConnection(
            provider: $id,
            model: 'some-model',
            apiKey: 'test-key',
        ));

        self::assertInstanceOf(OpenAILike::class, $provider);
        self::assertSame($expectedBaseUri, $this->readProperty($provider, 'baseUri'));
    }

    public static function openAiCompatibleProviders(): iterable
    {
        yield 'kimi' => [ProviderId::Kimi, 'https://api.moonshot.ai/v1'];
        yield 'minimax' => [ProviderId::MiniMax, 'https://api.minimax.io/v1'];
    }

    public function testCustomApiUrlOverridesDefaultEndpoint(): void
    {
        $provider = (new ProviderFactory())->create(new ProviderConnection(
            provider: ProviderId::Kimi,
            model: 'some-model',
            apiKey: 'test-key',
            apiUrl: 'https://proxy.example.test/v1',
        ));

        self::assertSame('https://proxy.example.test/v1', $this->readProperty($provider, 'baseUri'));
    }

    #[DataProvider('keylessProviderNeedingKey')]
    public function testMissingApiKeyIsRejectedBeforeBuildingProvider(ProviderId $id): void
    {
        $this->expectException(ProviderConfigurationException::class);
        $this->expectExceptionMessage('has no API key configured');

        (new ProviderFactory())->create(new ProviderConnection(provider: $id, model: 'm', apiKey: ''));
    }

    public static function keylessProviderNeedingKey(): iterable
    {
        yield 'openai' => [ProviderId::OpenAi];
        yield 'anthropic' => [ProviderId::Anthropic];
        yield 'kimi' => [ProviderId::Kimi];
        yield 'minimax' => [ProviderId::MiniMax];
        yield 'qwen' => [ProviderId::Qwen];
    }

    public function testAnthropicTemperatureIsClampedToItsRange(): void
    {
        $provider = (new ProviderFactory())->create(new ProviderConnection(
            provider: ProviderId::Anthropic,
            model: 'claude',
            apiKey: 'test-key',
            temperature: 1.8,
        ));

        self::assertSame(1.0, $this->readProperty($provider, 'parameters')['temperature']);
    }

    public function testConnectionRejectsOutOfRangeValues(): void
    {
        $this->expectException(ProviderConfigurationException::class);
        new ProviderConnection(provider: ProviderId::OpenAi, model: 'm', apiKey: 'k', temperature: 2.5);
    }

    private function readProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);

        return $reflection->getValue($object);
    }

    public function testStoredHostOnlyUrlGetsTheVersionPath(): void
    {
        $provider = (new ProviderFactory())->create(new ProviderConnection(
            provider: ProviderId::MiniMax,
            model: 'MiniMax-M3',
            apiKey: 'test-key',
            apiUrl: 'https://api.minimax.io',
        ));

        self::assertSame('https://api.minimax.io/v1', $this->readProperty($provider, 'baseUri'));
    }

    public function testOpenAiWithCustomUrlUsesTheOpenAiProtocolAtThatEndpoint(): void
    {
        // The "openai" identifier is also used for Qwen's DashScope gateway in the admin.
        $provider = (new ProviderFactory())->create(new ProviderConnection(
            provider: ProviderId::OpenAi,
            model: 'qwen-max',
            apiKey: 'test-key',
            apiUrl: 'https://gateway.example.test/compatible-mode/v1',
        ));

        self::assertInstanceOf(OpenAILike::class, $provider);
        self::assertSame('https://gateway.example.test/compatible-mode/v1', $this->readProperty($provider, 'baseUri'));
    }

    public function testMimoUsesItsOpenAiCompatibleEndpoint(): void
    {
        $provider = (new ProviderFactory())->create(new ProviderConnection(
            provider: ProviderId::Mimo,
            model: 'mimo-v2.5',
            apiKey: 'test-key',
        ));

        self::assertSame('https://api.xiaomimimo.com/v1', $this->readProperty($provider, 'baseUri'));
    }

    public function testNormalizeKeepsExistingPaths(): void
    {
        self::assertSame('https://api.moonshot.ai/v1', ProviderFactory::normalizeBaseUri('https://api.moonshot.ai/v1/'));
        self::assertSame('https://api.minimax.io/v1', ProviderFactory::normalizeBaseUri('https://api.minimax.io'));
    }
}
