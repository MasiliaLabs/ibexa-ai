<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Llm;

use Masilia\Bundle\IbexaAi\Llm\NeuronTextGenerator;
use Masilia\IbexaAi\Suggestion\SuggestionAction;
use Masilia\IbexaAi\Suggestion\SuggestionRequest;
use Masilia\IbexaAi\Suggestion\SuggestionService;
use Masilia\IbexaAi\Suggestion\SupportedField;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NeuronTextGenerator::class)]
final class NeuronTextGeneratorTest extends TestCase
{
    public function testGenerateSendsSystemPromptAndReturnsAnswer(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Result text'));
        $generator = new NeuronTextGenerator($provider);

        $answer = $generator->generate('SYSTEM RULES', 'USER TEXT');

        self::assertSame('Result text', $answer);
        $provider->assertCallCount(1);
        $provider->assertSystemPrompt('SYSTEM RULES');
    }

    public function testStreamConcatenatesTextChunksInOrder(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Streamed answer with several chunks'));
        $generator = new NeuronTextGenerator($provider);

        $text = '';
        foreach ($generator->stream('SYSTEM', 'USER') as $chunk) {
            $text .= $chunk;
        }

        self::assertSame('Streamed answer with several chunks', $text);
    }

    public function testSuggestionServiceWorksEndToEndOverANeuronProvider(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage("```html\n<p>Better</p>\n```"));
        $service = new SuggestionService(new NeuronTextGenerator($provider));

        $result = $service->suggest(new SuggestionRequest(
            field: SupportedField::RichText,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            currentValue: '<p>Plain</p>',
        ));

        self::assertSame('<p>Better</p>', $result);
        $provider->assertCallCount(1);
        $provider->assertSent(static fn ($record): bool => str_contains($record->systemPrompt?->getContent() ?? '', 'Do NOT use <h1>'));
    }
}
