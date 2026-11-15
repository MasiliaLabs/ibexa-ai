<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Masilia\IbexaAi\Persistence\Entity\AiProvider;
use Masilia\IbexaAi\Persistence\Entity\AiProviderSiteaccess;
use Masilia\IbexaAi\Persistence\Repository\AiModelRepository;
use Masilia\IbexaAi\Persistence\Repository\AiProviderRepository;
use Masilia\IbexaAi\Provider\ProviderCatalog;
use Masilia\IbexaAi\Provider\ProviderId;
use Masilia\IbexaAi\Security\ProviderKeys;
use Masilia\IbexaAi\Security\SecretCipher;
use PHPUnit\Framework\TestCase;

final class ProviderCatalogTest extends TestCase
{
    public function testProviderTypesListEverySupportedProvider(): void
    {
        $types = ProviderCatalog::providerTypes();

        self::assertSame(
            array_map(static fn (ProviderId $id): string => $id->value, ProviderId::cases()),
            array_column($types, 'value'),
        );
    }

    public function testProviderTypesCarryWhatTheFormNeeds(): void
    {
        $byValue = array_column(ProviderCatalog::providerTypes(), null, 'value');

        self::assertSame('Kimi (Moonshot)', $byValue['kimi']['label']);
        self::assertSame('https://api.moonshot.ai/v1', $byValue['kimi']['defaultApiUrl']);
        self::assertTrue($byValue['openai']['requiresApiKey']);
        self::assertNull($byValue['openai']['defaultApiUrl']);
        self::assertFalse($byValue['ollama']['requiresApiKey']);
    }

    public function testASiteaccessIsTakenAwayFromTheOtherProviders(): void
    {
        $provider = new AiProvider();
        $providers = $this->createMock(AiProviderRepository::class);
        $providers->method('find')->with(4)->willReturn($provider);

        $query = $this->createMock(Query::class);
        $query->expects(self::exactly(2))->method('setParameter')->willReturnSelf();
        $query->expects(self::once())->method('execute');

        $entities = $this->createMock(EntityManagerInterface::class);
        $entities->expects(self::once())->method('createQuery')
            ->with(self::stringContains('a.provider <> :provider'))
            ->willReturn($query);
        $persisted = [];
        $entities->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity instanceof AiProviderSiteaccess ? $entity->getSiteaccess() : '';
        });

        $catalog = new ProviderCatalog($entities, $providers, $this->createMock(AiModelRepository::class), new ProviderKeys(SecretCipher::fromConfiguration(null, 'secret')));
        $catalog->setSiteaccesses(4, [' admin_fossil_exit ', 'admin', 'admin', '', 7]);

        self::assertSame(['admin_fossil_exit', 'admin'], $persisted);
    }

    public function testNoSiteaccessTakesNothingFromTheOthers(): void
    {
        $providers = $this->createMock(AiProviderRepository::class);
        $providers->method('find')->willReturn(new AiProvider());
        $entities = $this->createMock(EntityManagerInterface::class);
        $entities->expects(self::never())->method('createQuery');

        (new ProviderCatalog($entities, $providers, $this->createMock(AiModelRepository::class), new ProviderKeys(SecretCipher::fromConfiguration(null, 'secret'))))->setSiteaccesses(4, []);
    }
}
