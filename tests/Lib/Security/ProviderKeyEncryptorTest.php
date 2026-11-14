<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Security;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\IbexaAi\Persistence\Entity\AiProvider;
use Masilia\IbexaAi\Persistence\Repository\AiProviderRepository;
use Masilia\IbexaAi\Security\ProviderKeyEncryptor;
use Masilia\IbexaAi\Security\ProviderKeys;
use Masilia\IbexaAi\Security\SecretCipher;
use PHPUnit\Framework\TestCase;

final class ProviderKeyEncryptorTest extends TestCase
{
    private ProviderKeys $keys;

    protected function setUp(): void
    {
        $this->keys = new ProviderKeys(SecretCipher::fromConfiguration(null, 'secret'));
    }

    private function provider(?string $key): AiProvider
    {
        $provider = new AiProvider();
        $provider->setApiKey($key);

        return $provider;
    }

    /**
     * @param list<AiProvider> $providers
     */
    private function encryptor(array $providers, int $expectedFlushes): ProviderKeyEncryptor
    {
        $repository = $this->createMock(AiProviderRepository::class);
        $repository->method('findAll')->willReturn($providers);
        $entities = $this->createMock(EntityManagerInterface::class);
        $entities->expects(self::exactly($expectedFlushes))->method('flush');

        return new ProviderKeyEncryptor($repository, $this->keys, $entities);
    }

    public function testPlainTextKeysAreEncryptedAndTheOthersLeftAlone(): void
    {
        $plain = $this->provider('sk-plain');
        $already = $this->provider(null);
        $this->keys->store($already, 'sk-already');
        $alreadyStored = $already->getApiKey();
        $none = $this->provider(null);

        $result = $this->encryptor([$plain, $already, $none], 1)->encryptAll();

        self::assertSame(['encrypted' => 1, 'alreadyEncrypted' => 1, 'withoutKey' => 1], $result);
        self::assertTrue(SecretCipher::isEncrypted((string) $plain->getApiKey()));
        self::assertSame('sk-plain', $this->keys->reveal($plain));
        self::assertSame($alreadyStored, $already->getApiKey(), 'an encrypted key is not encrypted twice');
        self::assertNull($none->getApiKey());
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $plain = $this->provider('sk-plain');
        $this->encryptor([$plain], 1)->encryptAll();
        $stored = $plain->getApiKey();

        $second = $this->encryptor([$plain], 0)->encryptAll();

        self::assertSame(['encrypted' => 0, 'alreadyEncrypted' => 1, 'withoutKey' => 0], $second);
        self::assertSame($stored, $plain->getApiKey());
    }

    public function testADryRunCountsWithoutChangingAnything(): void
    {
        $plain = $this->provider('sk-plain');

        $result = $this->encryptor([$plain], 0)->encryptAll(true);

        self::assertSame(1, $result['encrypted']);
        self::assertSame('sk-plain', $plain->getApiKey());
    }
}
