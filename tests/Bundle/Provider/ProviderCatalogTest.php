<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Provider;

use Masilia\Bundle\IbexaAi\Provider\ProviderCatalog;
use Masilia\IbexaAi\Provider\ProviderId;
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
}
