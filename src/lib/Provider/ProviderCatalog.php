<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\IbexaAi\Persistence\Entity\AiModel;
use Masilia\IbexaAi\Persistence\Entity\AiProvider;
use Masilia\IbexaAi\Persistence\Entity\AiProviderSiteaccess;
use Masilia\IbexaAi\Persistence\Repository\AiModelRepository;
use Masilia\IbexaAi\Persistence\Repository\AiProviderRepository;
use Masilia\IbexaAi\Provider\ProviderId;
use Masilia\IbexaAi\Security\ProviderKeys;

/**
 * Create, update and delete providers and models, and choose the active ones.
 *
 * Validation messages are returned to the admin as they are (they are user-facing).
 *
 * @throws \InvalidArgumentException with a message when the input cannot be applied
 */
final readonly class ProviderCatalog
{
    public const KEY_MASK = '••••••••';

    public function __construct(
        private EntityManagerInterface $entities,
        private AiProviderRepository $providers,
        private AiModelRepository $models,
        private ProviderKeys $keys,
    ) {
    }

    /**
     * @return array{providers: list<array<string, mixed>>, models: list<array<string, mixed>>}
     */
    public function snapshot(): array
    {
        $providers = $this->providers->findBy([], ['name' => 'ASC']);
        $models = [];
        foreach ($providers as $provider) {
            foreach ($provider->getModels() as $model) {
                $row = $this->modelArray($model);
                $row['providerName'] = $provider->getName();
                $models[] = $row;
            }
        }

        return [
            'providers' => array_map($this->providerArray(...), $providers),
            'models' => $models,
            'providerTypes' => self::providerTypes(),
        ];
    }

    /**
     * The providers an administrator can set up, straight from ProviderId so the form cannot drift from what the
     * server supports.
     *
     * @return list<array{value: string, label: string, requiresApiKey: bool, defaultApiUrl: ?string}>
     */
    public static function providerTypes(): array
    {
        return array_map(
            static fn (ProviderId $id): array => [
                'value' => $id->value,
                'label' => $id->label(),
                'requiresApiKey' => $id->requiresApiKey(),
                'defaultApiUrl' => $id->defaultOpenAiCompatibleBaseUri(),
            ],
            ProviderId::cases(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveProvider(array $data): AiProvider
    {
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $provider = $id !== null
            ? ($this->providers->find($id) ?? throw new \InvalidArgumentException('Provider not found.'))
            : new AiProvider();

        $name = trim((string) ($data['name'] ?? ''));
        $identifier = trim((string) ($data['identifier'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Name is required.');
        }
        if ($identifier === '') {
            throw new \InvalidArgumentException('Identifier is required.');
        }

        $provider->setName($name);
        $provider->setIdentifier($identifier);

        $apiKey = (string) ($data['apiKey'] ?? '');
        if ($apiKey !== '' && $apiKey !== self::KEY_MASK) {
            $this->keys->store($provider, $apiKey);
        }

        $apiUrl = trim((string) ($data['apiUrl'] ?? ''));
        $provider->setApiUrl($apiUrl === '' ? null : $apiUrl);

        $this->entities->persist($provider);
        $this->entities->flush();

        if (isset($data['siteaccesses']) && is_array($data['siteaccesses'])) {
            $this->setSiteaccesses((int) $provider->getId(), $data['siteaccesses']);
        }

        return $provider;
    }

    public function deleteProvider(int $id): void
    {
        $provider = $this->providers->find($id) ?? throw new \InvalidArgumentException('Provider not found.');

        $this->entities->remove($provider);
        $this->entities->flush();
    }

    /**
     * Replaces all siteaccess assignments of a provider.
     *
     * @param list<mixed> $siteaccesses
     */
    public function setSiteaccesses(int $providerId, array $siteaccesses): void
    {
        $provider = $this->providers->find($providerId) ?? throw new \InvalidArgumentException('Provider not found.');

        foreach ($provider->getSiteaccesses()->toArray() as $assignment) {
            $this->entities->remove($assignment);
        }
        $this->entities->flush();

        $seen = [];
        foreach ($siteaccesses as $name) {
            $name = is_string($name) ? trim($name) : '';
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $this->entities->persist(new AiProviderSiteaccess($provider, $name));
        }
        $this->entities->flush();
    }

    public function setActiveChatModel(int $providerId, ?int $modelId): void
    {
        $provider = $this->providers->find($providerId) ?? throw new \InvalidArgumentException('Provider not found.');
        $provider->setActiveChatModel($this->modelOf($provider, $modelId));
        $this->entities->flush();
    }

    public function setActiveImageModel(int $providerId, ?int $modelId): void
    {
        $provider = $this->providers->find($providerId) ?? throw new \InvalidArgumentException('Provider not found.');
        $provider->setActiveImageModel($this->modelOf($provider, $modelId));
        $this->entities->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveModel(array $data): AiModel
    {
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $providerId = (int) ($data['providerId'] ?? 0);
        $provider = $this->providers->find($providerId)
            ?? throw new \InvalidArgumentException('Selected provider does not exist.');

        $name = trim((string) ($data['name'] ?? ''));
        $identifier = trim((string) ($data['identifier'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Name is required.');
        }
        if ($identifier === '') {
            throw new \InvalidArgumentException('Identifier is required.');
        }

        $model = $id !== null
            ? ($this->models->find($id) ?? throw new \InvalidArgumentException('Model not found.'))
            : new AiModel($provider);

        if ($id !== null && $model->getProvider()->getId() !== $provider->getId()) {
            throw new \InvalidArgumentException('Model does not belong to this provider.');
        }

        $temperature = (float) ($data['temperature'] ?? 0.7);
        $maxTokens = (int) ($data['maxTokens'] ?? 4096);
        if ($temperature < 0.0 || $temperature > 2.0) {
            throw new \InvalidArgumentException('Temperature must be between 0 and 2.');
        }
        if ($maxTokens < 1) {
            throw new \InvalidArgumentException('Max tokens must be at least 1.');
        }

        $model->setName($name);
        $model->setIdentifier($identifier);
        $model->setTemperature($temperature);
        $model->setMaxTokens($maxTokens);
        $model->setSupportsImage((bool) ($data['supportsImage'] ?? false));
        $model->setSupportsVision((bool) ($data['supportsVision'] ?? false));

        $this->entities->persist($model);
        $this->entities->flush();

        return $model;
    }

    public function deleteModel(int $id): void
    {
        $model = $this->models->find($id) ?? throw new \InvalidArgumentException('Model not found.');
        $provider = $model->getProvider();

        if ($provider->getActiveChatModel() === $model) {
            $provider->setActiveChatModel(null);
        }
        if ($provider->getActiveImageModel() === $model) {
            $provider->setActiveImageModel(null);
        }

        $this->entities->remove($model);
        $this->entities->flush();
    }

    private function modelOf(AiProvider $provider, ?int $modelId): ?AiModel
    {
        if ($modelId === null) {
            return null;
        }

        $model = $this->models->find($modelId) ?? throw new \InvalidArgumentException('Model not found.');
        if ($model->getProvider()->getId() !== $provider->getId()) {
            throw new \InvalidArgumentException('Model does not belong to this provider.');
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function providerArray(AiProvider $provider): array
    {
        return [
            'id' => $provider->getId(),
            'name' => $provider->getName(),
            'identifier' => $provider->getIdentifier(),
            'siteaccesses' => array_values(array_map(
                static fn (AiProviderSiteaccess $assignment): string => $assignment->getSiteaccess(),
                $provider->getSiteaccesses()->toArray(),
            )),
            'apiKey' => $this->keys->has($provider) ? self::KEY_MASK : null,
            'apiUrl' => $provider->getApiUrl(),
            'activeChatModelId' => $provider->getActiveChatModel()?->getId(),
            'activeImageModelId' => $provider->getActiveImageModel()?->getId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function modelArray(AiModel $model): array
    {
        return [
            'id' => $model->getId(),
            'providerId' => $model->getProvider()->getId(),
            'name' => $model->getName(),
            'identifier' => $model->getIdentifier(),
            'temperature' => $model->getTemperature(),
            'maxTokens' => $model->getMaxTokens(),
            'supportsImage' => $model->supportsImage(),
            'supportsVision' => $model->supportsVision(),
        ];
    }
}
