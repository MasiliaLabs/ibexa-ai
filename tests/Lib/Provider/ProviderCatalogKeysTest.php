<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\IbexaAi\Provider\ProviderCatalog;
use Masilia\IbexaAi\Persistence\Entity\AiProvider;
use Masilia\IbexaAi\Persistence\Repository\AiModelRepository;
use Masilia\IbexaAi\Persistence\Repository\AiProviderRepository;
use Masilia\IbexaAi\Security\ProviderKeys;
use Masilia\IbexaAi\Security\SecretCipher;
use PHPUnit\Framework\TestCase;

/**
 * What the catalog does with API keys: stores them encrypted, never sends them back.
 */
final class ProviderCatalogKeysTest extends TestCase
{
    private ProviderKeys $keys;

    protected function setUp(): void
    {
        $this->keys = new ProviderKeys(SecretCipher::fromConfiguration(null, 'secret'));
    }

    /**
     * @param list<AiProvider> $existing
     */
    private function catalog(array $existing = []): ProviderCatalog
    {
        $providers = $this->createMock(AiProviderRepository::class);
        $providers->method('find')->willReturnCallback(static fn (int $id) => $existing[$id - 1] ?? null);
        $providers->method('findBy')->willReturn($existing);

        return new ProviderCatalog($this->createMock(EntityManagerInterface::class), $providers, $this->createMock(AiModelRepository::class), $this->keys);
    }

    public function testANewKeyIsStoredEncrypted(): void
    {
        $provider = $this->catalog()->saveProvider(['name' => 'Kimi', 'identifier' => 'kimi', 'apiKey' => 'sk-new']);

        self::assertTrue(SecretCipher::isEncrypted((string) $provider->getApiKey()));
        self::assertStringNotContainsString('sk-new', (string) $provider->getApiKey());
        self::assertSame('sk-new', $this->keys->reveal($provider));
    }

    public function testAnEmptyOrMaskedKeyKeepsTheStoredOne(): void
    {
        $existing = new AiProvider();
        $this->keys->store($existing, 'sk-kept');
        $stored = $existing->getApiKey();
        $catalog = $this->catalog([$existing]);

        $catalog->saveProvider(['id' => 1, 'name' => 'P', 'identifier' => 'openai', 'apiKey' => '']);
        self::assertSame($stored, $existing->getApiKey());

        $catalog->saveProvider(['id' => 1, 'name' => 'P', 'identifier' => 'openai', 'apiKey' => ProviderCatalog::KEY_MASK]);
        self::assertSame($stored, $existing->getApiKey());
    }

    public function testANewKeyReplacesTheStoredOne(): void
    {
        $existing = new AiProvider();
        $this->keys->store($existing, 'sk-old');

        $this->catalog([$existing])->saveProvider(['id' => 1, 'name' => 'P', 'identifier' => 'openai', 'apiKey' => 'sk-new']);

        self::assertSame('sk-new', $this->keys->reveal($existing));
    }

    public function testTheSnapshotNeverContainsAKeyNotEvenEncrypted(): void
    {
        $withKey = new AiProvider();
        $withKey->setName('A');
        $this->keys->store($withKey, 'sk-secret');
        $withoutKey = new AiProvider();
        $withoutKey->setName('B');

        $snapshot = $this->catalog([$withKey, $withoutKey])->snapshot();
        $json = (string) json_encode($snapshot);

        self::assertSame(ProviderCatalog::KEY_MASK, $snapshot['providers'][0]['apiKey']);
        self::assertNull($snapshot['providers'][1]['apiKey']);
        self::assertStringNotContainsString('sk-secret', $json);
        self::assertStringNotContainsString('enc:v1:', $json);
    }
}
