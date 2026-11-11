<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Suggestion;

use Masilia\IbexaAi\Suggestion\InvalidSuggestionRequestException;
use Masilia\IbexaAi\Suggestion\SuggestionAction;
use Masilia\IbexaAi\Suggestion\SuggestionRequest;
use Masilia\IbexaAi\Suggestion\SupportedField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuggestionRequest::class)]
#[CoversClass(SupportedField::class)]
final class SuggestionRequestTest extends TestCase
{
    public function testTargetLanguageIsRequired(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        new SuggestionRequest(SupportedField::Text, SuggestionAction::Improve, '');
    }

    public function testCustomActionNeedsAPrompt(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        new SuggestionRequest(SupportedField::Text, SuggestionAction::Custom, 'en-GB', prompt: '   ');
    }

    public function testTranslationNeedsSourceLanguageAndValue(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        new SuggestionRequest(SupportedField::Text, SuggestionAction::Translate, 'fr-FR', sourceLanguage: 'en-GB');
    }

    #[DataProvider('unsupportedIdentifiers')]
    public function testUnsupportedFieldTypesAreRejected(string $identifier): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        SupportedField::fromIdentifier($identifier);
    }

    public static function unsupportedIdentifiers(): iterable
    {
        yield 'matrix (not yet supported)' => ['ibexa_matrix'];
        yield 'seo metas (not yet supported)' => ['novaseometas'];
        yield 'legacy ezstring identifier' => ['ezstring'];
    }

    public function testSupportedFieldMapsToItsOutputFormat(): void
    {
        self::assertSame('ibexa_richtext', SupportedField::fromIdentifier('ibexa_richtext')->value);
        self::assertSame('html', SupportedField::RichText->format()->value);
        self::assertSame('plain_text', SupportedField::Image->format()->value);
    }
}
