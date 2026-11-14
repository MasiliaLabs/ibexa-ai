<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Security;

use Masilia\Bundle\IbexaAi\Entity\AiProvider;
use Masilia\Bundle\IbexaAi\Security\ProviderKeys;
use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Security\SecretCipher;
use PHPUnit\Framework\TestCase;

final class ProviderKeysTest extends TestCase
{
    private function keys(string $secret = 'secret'): ProviderKeys
    {
        return new ProviderKeys(SecretCipher::fromConfiguration(null, $secret));
    }

    public function testAStoredKeyIsEncryptedAndRevealedBack(): void
    {
        $provider = new AiProvider();
        $this->keys()->store($provider, 'sk-123');

        self::assertTrue(SecretCipher::isEncrypted((string) $provider->getApiKey()));
        self::assertStringNotContainsString('sk-123', (string) $provider->getApiKey());
        self::assertSame('sk-123', $this->keys()->reveal($provider));
        self::assertTrue($this->keys()->has($provider));
        self::assertFalse($this->keys()->isPlainText($provider));
    }

    public function testNoKeyIsNull(): void
    {
        $provider = new AiProvider();

        self::assertNull($this->keys()->reveal($provider));
        self::assertFalse($this->keys()->has($provider));

        $provider->setApiKey('');
        self::assertNull($this->keys()->reveal($provider));
        self::assertFalse($this->keys()->has($provider));
    }

    public function testALegacyPlainTextKeyStillWorks(): void
    {
        $provider = new AiProvider();
        $provider->setApiKey('sk-legacy');

        self::assertSame('sk-legacy', $this->keys()->reveal($provider));
        self::assertTrue($this->keys()->isPlainText($provider));
    }

    public function testAKeyWrittenWithAnotherSecretNamesTheProviderAndLeaksNothing(): void
    {
        $provider = new AiProvider();
        $provider->setName('Kimi');
        $this->keys('old')->store($provider, 'sk-123');

        try {
            $this->keys('new')->reveal($provider);
            self::fail('Must not be readable.');
        } catch (ProviderConfigurationException $e) {
            self::assertStringContainsString('"Kimi"', $e->getMessage());
            self::assertStringNotContainsString('sk-123', $e->getMessage());
        }
    }
}
