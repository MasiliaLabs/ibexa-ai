<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Suggestion;

use Generator;
use Masilia\IbexaAi\Llm\TextGenerator;

/**
 * Entry point for field suggestions: builds prompts, calls the model,
 * and cleans the answer.
 */
final readonly class SuggestionService
{
    public function __construct(
        private TextGenerator $generator,
        private PromptBuilder $prompts = new PromptBuilder(),
        private ResponseSanitizer $sanitizer = new ResponseSanitizer(),
    ) {
    }

    /**
     * Full answer, cleaned for the field format.
     */
    public function suggest(SuggestionRequest $request): string
    {
        $raw = $this->generator->generate(
            $this->prompts->systemPrompt($request),
            $this->prompts->userPrompt($request),
        );

        return $this->sanitizer->clean($raw, $request->field->format());
    }

    /**
     * Raw chunks as the model produces them. The model is instructed to avoid
     * wrappers, so chunks are not sanitised; the editor applies the final
     * result from the concatenated text when the stream ends.
     *
     * @return Generator<int, string>
     */
    public function stream(SuggestionRequest $request): Generator
    {
        foreach ($this->generator->stream(
            $this->prompts->systemPrompt($request),
            $this->prompts->userPrompt($request),
        ) as $chunk) {
            yield $chunk;
        }
    }
}
