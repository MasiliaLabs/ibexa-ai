<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Suggestion;

use Masilia\IbexaAi\Llm\TextGenerator;

/**
 * In-memory generator that returns canned output and records the prompts it received.
 */
final class FakeTextGenerator implements TextGenerator
{
    /** @var list<array{system: string, user: string}> */
    public array $calls = [];

    /**
     * @param list<string> $chunks
     */
    public function __construct(
        private readonly string $answer = '',
        private readonly array $chunks = [],
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $this->calls[] = ['system' => $systemPrompt, 'user' => $userPrompt];

        return $this->answer;
    }

    public function stream(string $systemPrompt, string $userPrompt): iterable
    {
        $this->calls[] = ['system' => $systemPrompt, 'user' => $userPrompt];

        yield from $this->chunks;
    }
}
