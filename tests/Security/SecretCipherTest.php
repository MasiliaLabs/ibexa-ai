<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Security;

use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Security\SecretCipher;
use Masilia\IbexaAi\Security\SecretDecryptionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecretCipherTest extends TestCase
{
    private function cipher(string $secret = 'app-secret-one'): SecretCipher
    {
        return SecretCipher::fromConfiguration(null, $secret);
    }

    public function testRoundTrip(): void
    {
        $cipher = $this->cipher();

        foreach (['sk-abc123', 'clé-éàü-日本語', str_repeat('k', 4000), '0'] as $plain) {
            self::assertSame($plain, $cipher->decrypt($cipher->encrypt($plain)));
        }
    }

    public function testTheStoredValueHidesTheKeyAndIsVersioned(): void
    {
        $stored = $this->cipher()->encrypt('sk-very-secret');

        self::assertStringStartsWith('enc:v1:', $stored);
        self::assertStringNotContainsString('sk-very-secret', $stored);
        self::assertTrue(SecretCipher::isEncrypted($stored));
        self::assertFalse(SecretCipher::isEncrypted('sk-very-secret'));
    }

    public function testTheSameKeyEncryptsToDifferentValues(): void
    {
        $cipher = $this->cipher();

        self::assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same'));
    }

    public function testAnotherKeyCannotDecrypt(): void
    {
        $stored = $this->cipher('secret-A')->encrypt('sk-1');

        $this->expectException(SecretDecryptionException::class);
        $this->cipher('secret-B')->decrypt($stored);
    }

    public function testAnAlteredValueIsDetected(): void
    {
        $cipher = $this->cipher();
        $stored = $cipher->encrypt('sk-1');
        $raw = base64_decode(substr($stored, strlen(SecretCipher::PREFIX)), true);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";

        $this->expectException(SecretDecryptionException::class);
        $cipher->decrypt(SecretCipher::PREFIX . base64_encode($raw));
    }

    #[DataProvider('damagedValues')]
    public function testDamagedValuesAreRefused(string $value): void
    {
        $this->expectException(SecretDecryptionException::class);
        $this->cipher()->decrypt($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function damagedValues(): iterable
    {
        yield 'plain text' => ['sk-plain'];
        yield 'prefix only' => ['enc:v1:'];
        yield 'not base64' => ['enc:v1:!!!'];
        yield 'too short' => ['enc:v1:' . base64_encode('short')];
    }

    public function testTheKeyDerivedFromTheSecretIsStable(): void
    {
        $stored = $this->cipher('stable')->encrypt('sk-1');

        self::assertSame('sk-1', $this->cipher('stable')->decrypt($stored));
    }

    public function testAConfiguredKeyIsUsedInsteadOfTheSecret(): void
    {
        $configured = base64_encode(random_bytes(32));
        $stored = SecretCipher::fromConfiguration($configured, 'secret-A')->encrypt('sk-1');

        self::assertSame('sk-1', SecretCipher::fromConfiguration($configured, 'another secret')->decrypt($stored));
        $this->expectException(SecretDecryptionException::class);
        $this->cipher('secret-A')->decrypt($stored);
    }

    #[DataProvider('invalidKeys')]
    public function testAnInvalidConfiguredKeyIsRefusedWithoutEchoingIt(string $key): void
    {
        try {
            SecretCipher::fromConfiguration($key, 'secret');
            self::fail('An invalid key must be refused.');
        } catch (ProviderConfigurationException $e) {
            self::assertStringContainsString('base64 of 32 bytes', $e->getMessage());
            self::assertStringNotContainsString($key, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'not base64' => ['@@@not-base64@@@'];
        yield 'too short' => [base64_encode('short')];
        yield 'too long' => [base64_encode(str_repeat('x', 33))];
    }

    public function testAnEmptyConfiguredKeyFallsBackToTheSecret(): void
    {
        $stored = SecretCipher::fromConfiguration('  ', 'secret')->encrypt('sk-1');

        self::assertSame('sk-1', $this->cipher('secret')->decrypt($stored));
    }

    public function testNothingToDeriveFromIsRefused(): void
    {
        $this->expectException(ProviderConfigurationException::class);
        SecretCipher::fromConfiguration(null, '');
    }

    public function testARawKeyMustBe32Bytes(): void
    {
        self::assertSame('x', SecretCipher::fromKey(str_repeat('k', 32))->decrypt(SecretCipher::fromKey(str_repeat('k', 32))->encrypt('x')));

        $this->expectException(ProviderConfigurationException::class);
        SecretCipher::fromKey('short');
    }
}
