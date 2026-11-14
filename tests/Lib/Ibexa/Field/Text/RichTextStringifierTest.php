<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Field\Text;

use DOMDocument;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\FieldTypeRichText\FieldType\RichText\Value;
use Masilia\IbexaAi\Ibexa\Field\Text\RichTextStringifier;
use PHPUnit\Framework\TestCase;

final class RichTextStringifierTest extends TestCase
{
    public function testTheTextOfEveryBlockIsReadWithIbexasOwnExtractor(): void
    {
        $xml = new DOMDocument();
        $xml->loadXML('<section xmlns="http://docbook.org/ns/docbook"><title>A title</title><para>First <emphasis>word</emphasis>.</para><para>Second.</para></section>');
        $field = new Field(['value' => new Value($xml)]);

        $text = (new RichTextStringifier())->toString($field, $this->createMock(FieldDefinition::class));

        self::assertSame('A title First word . Second.', $text);
    }

    public function testAnEmptyValueIsEmptyText(): void
    {
        self::assertSame('', (new RichTextStringifier())->toString(new Field(['value' => null]), $this->createMock(FieldDefinition::class)));
    }
}
