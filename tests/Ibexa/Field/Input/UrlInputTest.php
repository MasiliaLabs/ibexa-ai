<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Ibexa\Field\Input;

use Ibexa\Core\FieldType\Url\Value as UrlValue;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\UrlInput;
use Masilia\IbexaAi\Tests\Ibexa\Schema\FakeFieldDefinition;
use PHPUnit\Framework\TestCase;

final class UrlInputTest extends TestCase
{
    private UrlInput $transformer;

    protected function setUp(): void
    {
        $this->transformer = new UrlInput();
    }

    public function testGetFieldTypeIdentifier(): void
    {
        self::assertSame('ibexa_url', $this->transformer->getFieldTypeIdentifier());
    }

    public function testTransformsStringUrl(): void
    {
        $fieldDef = new FakeFieldDefinition('link', 'ibexa_url');

        $result = $this->transformer->transform($fieldDef, 'https://example.com');

        self::assertInstanceOf(UrlValue::class, $result);
        self::assertSame('https://example.com', $result->link);
        self::assertSame('https://example.com', $result->text);
    }

    public function testTransformsArrayWithLinkAndText(): void
    {
        $fieldDef = new FakeFieldDefinition('link', 'ibexa_url');

        $result = $this->transformer->transform($fieldDef, [
            'link' => 'https://example.com',
            'text' => 'Example Site',
        ]);

        self::assertInstanceOf(UrlValue::class, $result);
        self::assertSame('https://example.com', $result->link);
        self::assertSame('Example Site', $result->text);
    }

    public function testTransformsArrayWithLinkOnly(): void
    {
        $fieldDef = new FakeFieldDefinition('link', 'ibexa_url');

        $result = $this->transformer->transform($fieldDef, [
            'link' => 'https://example.com',
        ]);

        self::assertInstanceOf(UrlValue::class, $result);
        self::assertSame('https://example.com', $result->link);
        self::assertSame('https://example.com', $result->text);
    }

    public function testPassesThroughUrlValue(): void
    {
        $fieldDef = new FakeFieldDefinition('link', 'ibexa_url');
        $original = new UrlValue('https://example.com', 'Example');

        $result = $this->transformer->transform($fieldDef, $original);

        self::assertSame($original, $result);
    }

    public function testPassesThroughUnsupportedType(): void
    {
        $fieldDef = new FakeFieldDefinition('link', 'ibexa_url');

        $result = $this->transformer->transform($fieldDef, 42);

        self::assertSame(42, $result);
    }
}
