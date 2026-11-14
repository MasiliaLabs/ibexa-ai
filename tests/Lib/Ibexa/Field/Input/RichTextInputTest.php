<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Field\Input;

use DOMDocument;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Contracts\FieldTypeRichText\RichText\InputHandlerInterface;
use Ibexa\Contracts\GraphQL\Mutation\InputHandler\FieldType\RichText\RichTextInputConverter;
use Ibexa\FieldTypeRichText\FieldType\RichText\Value;
use Masilia\IbexaAi\Ibexa\Field\Input\RichTextInput;
use PHPUnit\Framework\TestCase;

final class RichTextInputTest extends TestCase
{
    private function docbook(string $text): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->loadXML(sprintf('<section xmlns="http://docbook.org/ns/docbook"><para>%s</para></section>', $text));

        return $dom;
    }

    public function testTextThatIsNotHtmlIsReadAsMarkdownByIbexasOwnConverter(): void
    {
        $markdown = $this->createMock(RichTextInputConverter::class);
        $markdown->expects(self::once())->method('convertToXml')->with("# Title\n\nSome **text**")->willReturn($this->docbook('md'));
        $handler = $this->createMock(InputHandlerInterface::class);
        $handler->expects(self::never())->method('fromString');

        $value = (new RichTextInput($handler, $markdown))->transform($this->createMock(FieldDefinition::class), "# Title\n\nSome **text**");

        self::assertInstanceOf(Value::class, $value);
        self::assertStringContainsString('md', (string) $value->xml->saveXML());
    }

    public function testHtmlStillGoesThroughTheValidatingInputHandler(): void
    {
        $markdown = $this->createMock(RichTextInputConverter::class);
        $markdown->expects(self::never())->method('convertToXml');
        $handler = $this->createMock(InputHandlerInterface::class);
        $handler->expects(self::once())->method('fromString')->with(self::stringContains('<p>Hi</p>'))->willReturn($this->docbook('html'));

        $value = (new RichTextInput($handler, $markdown))->transform($this->createMock(FieldDefinition::class), '<p>Hi</p>');

        self::assertInstanceOf(Value::class, $value);
    }

    public function testMarkdownWithAStrayTagGoesThroughTheValidatingHandlerToo(): void
    {
        $markdown = $this->createMock(RichTextInputConverter::class);
        $markdown->expects(self::never())->method('convertToXml');
        $handler = $this->createMock(InputHandlerInterface::class);
        $handler->expects(self::once())->method('fromString')->willReturn($this->docbook('mixed'));

        self::assertInstanceOf(Value::class, (new RichTextInput($handler, $markdown))->transform($this->createMock(FieldDefinition::class), "first<br>second"));
    }

    public function testWithoutTheMarkdownConverterPlainTextIsAParagraph(): void
    {
        $handler = $this->createMock(InputHandlerInterface::class);
        $handler->expects(self::once())->method('fromString')->with(self::stringContains('<p>a &amp; b</p>'))->willReturn($this->docbook('plain'));

        self::assertInstanceOf(Value::class, (new RichTextInput($handler))->transform($this->createMock(FieldDefinition::class), 'a & b'));
    }
}
