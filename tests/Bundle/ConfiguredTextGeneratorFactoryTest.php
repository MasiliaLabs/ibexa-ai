<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle;

use Masilia\Bundle\IbexaAi\Llm\ConfiguredTextGeneratorFactory;
use Masilia\Bundle\IbexaAi\Llm\NeuronTextGenerator;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredTextGeneratorFactory::class)]
final class ConfiguredTextGeneratorFactoryTest extends TestCase
{
    public function testNoProviderConfiguredIsAConfigurationError(): void
    {
        $this->expectException(ProviderConfigurationException::class);
        (new ConfiguredTextGeneratorFactory([]))->create();
    }

    public function testUnknownProviderIsAConfigurationError(): void
    {
        $this->expectException(ProviderConfigurationException::class);
        (new ConfiguredTextGeneratorFactory(['provider' => 'nope']))->create();
    }

    public function testConfiguredProviderBuildsANeuronGenerator(): void
    {
        $generator = (new ConfiguredTextGeneratorFactory([
            'provider' => 'openai',
            'model' => 'gpt-test',
            'api_key' => 'test-key',
            'temperature' => 0.2,
            'max_tokens' => 512,
        ]))->create();

        self::assertInstanceOf(NeuronTextGenerator::class, $generator);
    }

    public function testMissingKeyForAHostedProviderFailsEarly(): void
    {
        $this->expectException(ProviderConfigurationException::class);
        (new ConfiguredTextGeneratorFactory(['provider' => 'openai', 'model' => 'gpt-test']))->create();
    }
}
