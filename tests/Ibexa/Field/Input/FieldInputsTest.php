<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\FieldType;
use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\FieldInput;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\FieldInputs;
use PHPUnit\Framework\TestCase;

final class FieldInputsTest extends TestCase
{
    private function definition(string $type): FieldDefinition
    {
        $definition = $this->createMock(FieldDefinition::class);
        $definition->method('getFieldTypeIdentifier')->willReturn($type);

        return $definition;
    }

    public function testTheAdapterOfTheFieldTypeComesFirst(): void
    {
        $adapter = $this->createMock(FieldInput::class);
        $adapter->method('getFieldTypeIdentifier')->willReturn('ibexa_date');
        $adapter->method('transform')->willReturn('adapted');
        $fieldTypes = $this->createMock(FieldTypeService::class);
        $fieldTypes->expects(self::never())->method('getFieldType');

        self::assertSame('adapted', (new FieldInputs([$adapter], $fieldTypes))->transform($this->definition('ibexa_date'), '2026-03-04'));
    }

    public function testAnotherFieldTypeReadsItsOwnHash(): void
    {
        $type = $this->createMock(FieldType::class);
        $type->expects(self::once())->method('fromHash')->with(['latitude' => 1.5])->willReturn('the value');
        $fieldTypes = $this->createMock(FieldTypeService::class);
        $fieldTypes->method('getFieldType')->with('acme_point')->willReturn($type);

        self::assertSame('the value', (new FieldInputs([], $fieldTypes))->transform($this->definition('acme_point'), ['latitude' => 1.5]));
    }

    public function testAHashTheFieldTypeRefusesIsHandedOverAsItIs(): void
    {
        $type = $this->createMock(FieldType::class);
        $type->method('fromHash')->willThrowException(new \TypeError('not a hash of mine'));
        $fieldTypes = $this->createMock(FieldTypeService::class);
        $fieldTypes->method('getFieldType')->willReturn($type);

        self::assertSame(['x' => 1], (new FieldInputs([], $fieldTypes))->transform($this->definition('acme_point'), ['x' => 1]));
    }

    public function testAScalarForAFieldTypeWithoutAdapterIsPassedThrough(): void
    {
        $fieldTypes = $this->createMock(FieldTypeService::class);
        $fieldTypes->expects(self::never())->method('getFieldType');

        self::assertSame('Hello', (new FieldInputs([], $fieldTypes))->transform($this->definition('ibexa_string'), 'Hello'));
    }

    public function testWithoutTheFieldTypeServiceItIsAPassThrough(): void
    {
        self::assertSame(['a'], (new FieldInputs([]))->transform($this->definition('acme_point'), ['a']));
    }
}
