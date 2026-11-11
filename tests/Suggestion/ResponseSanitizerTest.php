<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Suggestion;

use Masilia\IbexaAi\Suggestion\FieldFormat;
use Masilia\IbexaAi\Suggestion\ResponseSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseSanitizer::class)]
final class ResponseSanitizerTest extends TestCase
{
    public function testStripsHtmlCodeFence(): void
    {
        $raw = "```html\n<p>Hello</p>\n```";

        self::assertSame('<p>Hello</p>', (new ResponseSanitizer())->clean($raw, FieldFormat::Html));
    }

    public function testKeepsHtmlWithoutFenceUntouched(): void
    {
        $raw = '<p>Hello <strong>you</strong></p>';

        self::assertSame($raw, (new ResponseSanitizer())->clean($raw, FieldFormat::Html));
    }

    public function testPlainTextIsCollapsedToOneLineAndUnquoted(): void
    {
        $raw = "\"A title\nspanning lines\"";

        self::assertSame('A title spanning lines', (new ResponseSanitizer())->clean($raw, FieldFormat::PlainText));
    }

    public function testTextBlockKeepsParagraphBreaks(): void
    {
        $raw = "First paragraph.\n\nSecond paragraph.";

        self::assertSame($raw, (new ResponseSanitizer())->clean($raw, FieldFormat::TextBlock));
    }

    #[DataProvider('quotePairs')]
    public function testMatchingQuotesAreRemovedOnlyForPlainText(string $open, string $close): void
    {
        $raw = $open . 'quoted' . $close;

        self::assertSame('quoted', (new ResponseSanitizer())->clean($raw, FieldFormat::PlainText));
        self::assertSame($raw, (new ResponseSanitizer())->clean($raw, FieldFormat::TextBlock));
    }

    public static function quotePairs(): iterable
    {
        yield 'double' => ['"', '"'];
        yield 'single' => ["'", "'"];
        yield 'french' => ['«', '»'];
        yield 'curly' => ['“', '”'];
    }

    public function testUnmatchedQuotesAreKept(): void
    {
        self::assertSame('"half', (new ResponseSanitizer())->clean('"half', FieldFormat::PlainText));
    }
}
