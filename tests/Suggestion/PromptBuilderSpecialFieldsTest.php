<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Suggestion;

use Masilia\IbexaAi\Suggestion\MatrixContext;
use Masilia\IbexaAi\Suggestion\PromptBuilder;
use Masilia\IbexaAi\Suggestion\SuggestionAction;
use Masilia\IbexaAi\Suggestion\SuggestionRequest;
use Masilia\IbexaAi\Suggestion\SupportedField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptBuilder::class)]
final class PromptBuilderSpecialFieldsTest extends TestCase
{
    public function testMatrixRulesListColumnIdentifiersAndRowCount(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::Matrix,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            matrix: new MatrixContext(['text' => 'Button label', 'url' => 'Link'], 3),
        );

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('key: "text" (human label: Button label)', $system);
        self::assertStringContainsString('key: "url" (human label: Link)', $system);
        self::assertStringContainsString('must contain 3 row(s)', $system);
    }

    public function testMatrixWithoutColumnsFallsBackToValueKey(): void
    {
        $request = new SuggestionRequest(SupportedField::Matrix, SuggestionAction::Improve, 'en-GB');

        self::assertStringContainsString('use a single "value" key', (new PromptBuilder())->systemPrompt($request));
    }

    public function testMatrixTranslationAsksForRowsJson(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::Matrix,
            action: SuggestionAction::Translate,
            language: 'fre-FR',
            sourceLanguage: 'eng-GB',
            sourceValue: '{"rows":[]}',
        );

        $user = (new PromptBuilder())->userPrompt($request);

        self::assertStringStartsWith('Translate each cell of the following matrix from eng-GB to fre-FR.', $user);
        self::assertStringContainsString('{"rows":[]}', $user);
    }

    public function testWholeBlockSeoSchemaIsRestrictedToFormKeys(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::SeoMetas,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            metaKeys: ['title', 'description'],
        );

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('"title": "Title (compelling, click-worthy SEO Meta Title, under 60 characters)"', $system);
        self::assertStringContainsString('"description": "Description', $system);
        self::assertStringNotContainsString('"keywords"', $system);
        self::assertStringContainsString('Output MUST be a valid JSON object', $system);
    }

    public function testSeoSubFieldAsksForPlainTextWithItsLimit(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::SeoMetas,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            subFieldKey: 'description',
        );

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('Keep it under 160 characters.', $system);
        self::assertStringContainsString('NO JSON', $system);
    }
}
