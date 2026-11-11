<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Tool\FieldValueTransformer;

use Masilia\Bundle\IbexaAi\Agent\Tool\FieldValueTransformer\SelectionTransformer;
use Masilia\IbexaAi\Tests\Agent\Block\FakeFieldDefinition;
use PHPUnit\Framework\TestCase;

final class SelectionTransformerTest extends TestCase
{
    private SelectionTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new SelectionTransformer();
    }

    public function testGetFieldTypeIdentifier(): void
    {
        self::assertSame('ibexa_selection', $this->transformer->getFieldTypeIdentifier());
    }

    public function testResolvesLabelToIndex(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ibexa_selection', ['options' => ['Dark', 'Light', 'Auto']]);

        $result = $this->transformer->transform($fieldDef, 'Light');

        self::assertSame([1], $result);
    }

    public function testPassesThroughIntegerIndex(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ibexa_selection', ['options' => ['Dark', 'Light']]);

        $result = $this->transformer->transform($fieldDef, 0);

        self::assertSame([0], $result);
    }

    public function testPassesThroughArrayIndices(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ibexa_selection', ['options' => ['Dark', 'Light']]);

        $result = $this->transformer->transform($fieldDef, [0, 1]);

        self::assertSame([0, 1], $result);
    }

    public function testReturnsEmptyArrayForUnknownLabel(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ibexa_selection', ['options' => ['Dark', 'Light']]);

        $result = $this->transformer->transform($fieldDef, 'NonExistent');

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayForNullValue(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ibexa_selection', ['options' => ['Dark']]);

        $result = $this->transformer->transform($fieldDef, null);

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayForEmptyOptions(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ibexa_selection');

        $result = $this->transformer->transform($fieldDef, 'Dark');

        self::assertSame([], $result);
    }
}
