<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Provider;

use Masilia\Bundle\IbexaAi\Repository\AiProviderRepository;
use Masilia\IbexaAi\Provider\ProviderConnection;
use Masilia\IbexaAi\Provider\ProviderFactory;
use Masilia\IbexaAi\Provider\ProviderId;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;
use Masilia\Bundle\IbexaAi\Security\ProviderKeys;

/**
 * "Test connection" from the admin: one short completion, optionally one streamed chunk.
 * Test calls are not written to the usage log.
 */
final readonly class ProviderTester
{
    public function __construct(
        private AiProviderRepository $providers,
        private ProviderFactory $factory,
        private ProviderKeys $keys,
    ) {
    }

    /**
     * @return array{success: bool, message: string, streamTested: bool, streamOk: ?bool}
     * @throws \InvalidArgumentException when the provider does not exist
     */
    public function test(int $providerId, bool $withStream = false): array
    {
        $provider = $this->providers->find($providerId) ?? throw new \InvalidArgumentException('Provider not found.');
        $model = $provider->getActiveChatModel()
            ?? $provider->getModels()->first()
            ?: null;

        if ($model === false || $model === null) {
            return $this->result(false, 'Select a chat model for this provider first.');
        }

        try {
            $connection = new ProviderConnection(
                provider: ProviderId::tryFrom($provider->getIdentifier())
                    ?? throw new \InvalidArgumentException(sprintf('Provider "%s" is not supported.', $provider->getIdentifier())),
                model: $model->getIdentifier(),
                apiKey: $this->keys->reveal($provider),
                apiUrl: $provider->getApiUrl(),
                temperature: 0.0,
                maxTokens: 32,
            );
            $llm = $this->factory->create($connection);
            $answer = $llm->systemPrompt('You are a connection test. Answer briefly.')
                ->chat(new UserMessage('Reply with the single word OK.'))
                ->message()->getContent() ?? '';
        } catch (Throwable $e) {
            return $this->result(false, 'Connection failed: ' . $e->getMessage());
        }

        if (trim($answer) === '') {
            return $this->result(false, 'The provider answered with an empty message.');
        }

        if (!$withStream) {
            return $this->result(true, 'Connection OK.');
        }

        try {
            $chunks = 0;
            foreach ($llm->systemPrompt('You are a connection test.')->stream(new UserMessage('Reply with the single word OK.')) as $chunk) {
                if ($chunk instanceof TextChunk && $chunk->content !== '') {
                    $chunks++;
                }
            }
            $streamOk = $chunks > 0;
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Streaming failed: ' . $e->getMessage(), 'streamTested' => true, 'streamOk' => false];
        }

        return ['success' => $streamOk, 'message' => $streamOk ? 'Connection and streaming OK.' : 'Streaming returned no text.', 'streamTested' => true, 'streamOk' => $streamOk];
    }

    /**
     * @return array{success: bool, message: string, streamTested: bool, streamOk: ?bool}
     */
    private function result(bool $success, string $message): array
    {
        return ['success' => $success, 'message' => $message, 'streamTested' => false, 'streamOk' => null];
    }
}
