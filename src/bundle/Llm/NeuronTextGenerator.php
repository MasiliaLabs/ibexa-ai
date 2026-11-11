<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Llm;

use Generator;
use Masilia\IbexaAi\Llm\TextGenerator;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Adapts a Neuron provider to the {@see TextGenerator} port.
 *
 * One instance wraps one provider built for one request, so setting the
 * system prompt on it does not leak into other requests.
 */
final readonly class NeuronTextGenerator implements TextGenerator
{
    public function __construct(
        private AIProviderInterface $provider,
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $response = $this->provider
            ->systemPrompt($systemPrompt)
            ->chat(new UserMessage($userPrompt));

        return $response->message()->getContent() ?? '';
    }

    /**
     * @return Generator<int, string>
     */
    public function stream(string $systemPrompt, string $userPrompt): Generator
    {
        $chunks = $this->provider
            ->systemPrompt($systemPrompt)
            ->stream(new UserMessage($userPrompt));

        foreach ($chunks as $chunk) {
            if ($chunk instanceof TextChunk && $chunk->content !== '') {
                yield $chunk->content;
            }
        }
    }
}
