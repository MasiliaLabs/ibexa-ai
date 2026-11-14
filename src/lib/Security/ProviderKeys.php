<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Security;

use Masilia\IbexaAi\Persistence\Entity\AiProvider;
use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use Masilia\IbexaAi\Security\SecretCipher;
use Masilia\IbexaAi\Security\SecretDecryptionException;

/**
 * The only way in and out for provider API keys: they are encrypted when stored and decrypted when used,
 * so no other class handles a stored key.
 *
 * A key written before the encryption existed is plain text; it keeps working until
 * `masilia:ai:encrypt-keys` (or the next save of its provider) encrypts it.
 */
final readonly class ProviderKeys
{
    public function __construct(
        private SecretCipher $cipher,
    ) {
    }

    public function store(AiProvider $provider, string $plain): void
    {
        $provider->setApiKey($this->cipher->encrypt($plain));
    }

    public function has(AiProvider $provider): bool
    {
        return ($provider->getApiKey() ?? '') !== '';
    }

    /**
     * True when the provider has a key that is still stored as plain text.
     */
    public function isPlainText(AiProvider $provider): bool
    {
        return $this->has($provider) && !SecretCipher::isEncrypted((string) $provider->getApiKey());
    }

    /**
     * The key to send to the provider, or null when none is set.
     *
     * @throws ProviderConfigurationException when the stored key cannot be decrypted (the encryption key changed)
     */
    public function reveal(AiProvider $provider): ?string
    {
        $stored = $provider->getApiKey();
        if ($stored === null || $stored === '') {
            return null;
        }

        if (!SecretCipher::isEncrypted($stored)) {
            return $stored;
        }

        try {
            return $this->cipher->decrypt($stored);
        } catch (SecretDecryptionException) {
            throw new ProviderConfigurationException(sprintf(
                'The API key of "%s" cannot be decrypted: the encryption key changed. Enter the key again in the provider settings.',
                $provider->getName(),
            ));
        }
    }
}
