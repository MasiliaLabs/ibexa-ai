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
     * @throws InvalidModelOutputException when a JSON field gets a non-object answer
     */
    public function suggest(SuggestionRequest $request): Suggestion
    {
        $format = $request->outputFormat();

        $raw = $this->generator->generate(
            $this->prompts->systemPrompt($request),
            $this->prompts->userPrompt($request),
        );

        return new Suggestion($this->sanitizer->clean($raw, $format), $format);
    }

    /**
     * Raw chunks as the model produces them. Chunks are not sanitised: the
     * prompt already forbids wrappers, and the editor applies the full text.
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
