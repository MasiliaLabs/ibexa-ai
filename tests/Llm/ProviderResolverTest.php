<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Llm;

use Masilia\Bundle\IbexaAi\Entity\AiModel;
use Masilia\Bundle\IbexaAi\Entity\AiProvider;
use Masilia\Bundle\IbexaAi\Llm\ProviderResolver;
use Masilia\Bundle\IbexaAi\Repository\AiProviderRepository;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Provider\ProviderId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderResolver::class)]
final class ProviderResolverTest extends TestCase
{
    public function testTheAdminProviderOfTheSiteaccessWins(): void
    {
        $provider = new AiProvider();
        $provider->setIdentifier('minimax');
        $provider->setApiKey('db-key');
        $provider->setApiUrl('https://api.minimax.io');
        $model = new AiModel($provider);
        $model->setIdentifier('MiniMax-M3');
        $model->setTemperature(0.5);
        $model->setMaxTokens(65536);
        $provider->setActiveChatModel($model);

        $connection = $this->resolver($provider, ['provider' => 'kimi', 'model' => 'kimi-k2'])->resolveChat('admin');

        self::assertSame(ProviderId::MiniMax, $connection->provider);
        self::assertSame('MiniMax-M3', $connection->model);
        self::assertSame('db-key', $connection->apiKey);
        self::assertSame('https://api.minimax.io', $connection->apiUrl);
        self::assertSame(0.5, $connection->temperature);
        self::assertSame(65536, $connection->maxTokens);
    }

    public function testWithoutAnAdminProviderTheYamlConnectionIsUsed(): void
    {
        $connection = $this->resolver(null, ['provider' => 'mimo', 'model' => 'mimo-v2.5', 'api_key' => 'env-key', 'api_url' => ''])
            ->resolveChat('admin');

        self::assertSame(ProviderId::Mimo, $connection->provider);
        self::assertSame('env-key', $connection->apiKey);
        self::assertNull($connection->apiUrl, 'an empty URL means the provider default');
    }

    public function testNothingConfiguredIsAConfigurationError(): void
    {
        $this->expectException(ProviderConfigurationException::class);
        $this->resolver(null, [])->resolveChat('admin');
    }

    public function testAnUnknownProviderIdentifierIsRejected(): void
    {
        $provider = new AiProvider();
        $provider->setIdentifier('acme-ai');
        $model = new AiModel($provider);
        $model->setIdentifier('m');
        $provider->setActiveChatModel($model);

        $this->expectException(ProviderConfigurationException::class);
        $this->resolver($provider, [])->resolveChat('admin');
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolver(?AiProvider $adminProvider, array $settings): ProviderResolver
    {
        $repository = $this->createMock(AiProviderRepository::class);
        $repository->method('findActiveChatForSiteaccess')->willReturn($adminProvider);

        return new ProviderResolver($repository, $settings);
    }
}
