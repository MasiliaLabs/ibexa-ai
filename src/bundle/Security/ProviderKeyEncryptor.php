<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Security;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\Bundle\IbexaAi\Repository\AiProviderRepository;

/**
 * Encrypts the provider keys that are still stored as plain text. Safe to run again: a key already encrypted
 * is left as it is.
 */
final readonly class ProviderKeyEncryptor
{
    public function __construct(
        private AiProviderRepository $providers,
        private ProviderKeys $keys,
        private EntityManagerInterface $entities,
    ) {
    }

    /**
     * @return array{encrypted: int, alreadyEncrypted: int, withoutKey: int}
     */
    public function encryptAll(bool $dryRun = false): array
    {
        $result = ['encrypted' => 0, 'alreadyEncrypted' => 0, 'withoutKey' => 0];

        foreach ($this->providers->findAll() as $provider) {
            if (!$this->keys->has($provider)) {
                ++$result['withoutKey'];
                continue;
            }
            if (!$this->keys->isPlainText($provider)) {
                ++$result['alreadyEncrypted'];
                continue;
            }

            ++$result['encrypted'];
            if (!$dryRun) {
                $this->keys->store($provider, (string) $provider->getApiKey());
            }
        }

        if (!$dryRun && $result['encrypted'] > 0) {
            $this->entities->flush();
        }

        return $result;
    }
}
