<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Security;

use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use SensitiveParameter;

/**
 * Encrypts the secrets the admin stores (provider API keys) so a database dump does not expose them.
 *
 * Uses libsodium's secretbox (XSalsa20-Poly1305): authenticated, so a wrong key or an altered value is
 * detected instead of decrypting to garbage. A stored value looks like `enc:v1:` + base64(nonce + box); the
 * version prefix allows a later change of algorithm, and tells encrypted values from legacy plain text.
 *
 * The key is either configured (32 random bytes, base64) or derived from the application secret.
 */
final readonly class SecretCipher
{
    public const PREFIX = 'enc:v1:';

    /** Context mixed into the derivation, so this key differs from any other derived from the same secret. */
    private const DERIVATION_CONTEXT = 'masilia/ibexa-ai:provider-api-key:v1';

    private function __construct(
        #[SensitiveParameter]
        private string $key,
    ) {
    }

    /**
     * @throws ProviderConfigurationException when the key is not exactly 32 bytes
     */
    public static function fromKey(#[SensitiveParameter] string $rawKey): self
    {
        if (strlen($rawKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new ProviderConfigurationException(sprintf('The encryption key must be %d bytes.', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        return new self($rawKey);
    }

    /**
     * @param string|null $configuredKey base64 of 32 random bytes, or null/empty to derive the key from the secret
     * @param string      $appSecret     the application secret (kernel.secret)
     *
     * @throws ProviderConfigurationException when the configured key is not valid base64 of 32 bytes, or there is nothing to derive from
     */
    public static function fromConfiguration(
        #[SensitiveParameter]
        ?string $configuredKey,
        #[SensitiveParameter]
        string $appSecret,
    ): self {
        if ($configuredKey !== null && trim($configuredKey) !== '') {
            $decoded = base64_decode(trim($configuredKey), true);
            if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new ProviderConfigurationException(
                    'masilia_ibexa_ai.encryption_key must be base64 of 32 bytes. Generate one with: php -r \'echo base64_encode(random_bytes(32));\'',
                );
            }

            return new self($decoded);
        }

        if ($appSecret === '') {
            throw new ProviderConfigurationException('No encryption key: set masilia_ibexa_ai.encryption_key, or a non-empty application secret.');
        }

        return new self(hash_hkdf('sha256', $appSecret, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, self::DERIVATION_CONTEXT));
    }

    /**
     * True for a value written by {@see encrypt()}; false for plain text from before the encryption existed.
     */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    public function encrypt(#[SensitiveParameter] string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    /**
     * @throws SecretDecryptionException when the value is not encrypted, was written with another key, or was altered
     */
    public function decrypt(string $stored): string
    {
        if (!self::isEncrypted($stored)) {
            throw new SecretDecryptionException('The value is not encrypted.');
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new SecretDecryptionException('The stored value is damaged.');
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );
        if ($plain === false) {
            throw new SecretDecryptionException('The stored value cannot be decrypted: the encryption key changed, or the value was altered.');
        }

        return $plain;
    }
}
