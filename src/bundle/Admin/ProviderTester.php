<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Admin;

use Masilia\Bundle\IbexaAi\Llm\NeuronTextGenerator;
use Masilia\Bundle\IbexaAi\Repository\AiProviderRepository;
use Masilia\IbexaAi\Provider\ProviderConnection;
use Masilia\IbexaAi\Provider\ProviderFactory;
use Masilia\IbexaAi\Provider\ProviderId;
use Throwable;

/**
 * "Test connection" from the admin: one short completion, optionally one streamed chunk.
 * Test calls are not written to the usage log.
 */
final readonly class ProviderTester
{
    public function __construct(
        private AiProviderRepository $providers,
        private ProviderFactory $factory,
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
                apiKey: $provider->getApiKey(),
                apiUrl: $provider->getApiUrl(),
                temperature: 0.0,
                maxTokens: 32,
            );
            $generator = new NeuronTextGenerator($this->factory->create($connection));
            $answer = $generator->generate('You are a connection test. Answer briefly.', 'Reply with the single word OK.');
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
            foreach ($generator->stream('You are a connection test.', 'Reply with the single word OK.') as $chunk) {
                $chunks++;
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
