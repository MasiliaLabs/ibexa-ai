<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Field\Input;

use Ibexa\Core\FieldType\Integer\Value as IntegerValue;
use Masilia\IbexaAi\Ibexa\Field\Input\IntegerInput;
use PHPUnit\Framework\TestCase;

class IntegerInputTest extends TestCase
{
    private IntegerInput $transformer;

    protected function setUp(): void
    {
        $this->transformer = new IntegerInput();
    }

    public function testGetFieldTypeIdentifier(): void
    {
        self::assertSame('ibexa_integer', $this->transformer->getFieldTypeIdentifier());
    }

    public function testTransformInt(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 42);
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertSame(42, $result->value);
    }

    public function testTransformFloat(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 3.7);
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertSame(3, $result->value);
    }

    public function testTransformNumericString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), '100');
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertSame(100, $result->value);
    }

    public function testTransformEmptyString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), '');
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertNull($result->value);
    }

    public function testTransformNull(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), null);
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertNull($result->value);
    }

    public function testTransformNonNumericString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 'hello');
        self::assertSame('hello', $result);
    }

    private function fieldDef(): \Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition
    {
        return $this->createMock(\Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition::class);
    }
}
