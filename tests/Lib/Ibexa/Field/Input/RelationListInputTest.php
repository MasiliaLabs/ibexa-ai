<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Field\Input;

use Masilia\IbexaAi\Ibexa\Field\Input\RelationListInput;
use Masilia\IbexaAi\Tests\Lib\Ibexa\Schema\FakeFieldDefinition;
use PHPUnit\Framework\TestCase;

final class RelationListInputTest extends TestCase
{
    private RelationListInput $transformer;

    protected function setUp(): void
    {
        $this->transformer = new RelationListInput();
    }

    public function testGetFieldTypeIdentifier(): void
    {
        self::assertSame('ibexa_object_relation_list', $this->transformer->getFieldTypeIdentifier());
    }

    public function testPassesThroughFlatArrayOfIntegers(): void
    {
        $result = $this->transformer->transform($this->createFieldDef(), [100, 200, 300]);

        self::assertSame([100, 200, 300], $result);
    }

    public function testExtractsDestinationContentIds(): void
    {
        $result = $this->transformer->transform($this->createFieldDef(), [
            'destinationContentIds' => [100, 200],
        ]);

        self::assertSame([100, 200], $result);
    }

    public function testFlattensNestedArrays(): void
    {
        $result = $this->transformer->transform($this->createFieldDef(), [[1176], [1177]]);

        self::assertSame([1176, 1177], $result);
    }

    public function testFlattensMixedNestedArrays(): void
    {
        $result = $this->transformer->transform($this->createFieldDef(), [[100, 200], [300]]);

        self::assertSame([100, 200, 300], $result);
    }

    public function testPassesThroughNonArrayValue(): void
    {
        $result = $this->transformer->transform($this->createFieldDef(), 'not_an_array');

        self::assertSame('not_an_array', $result);
    }

    public function testHandlesEmptyArray(): void
    {
        $result = $this->transformer->transform($this->createFieldDef(), []);

        self::assertSame([], $result);
    }

    private function createFieldDef(): FakeFieldDefinition
    {
        return new FakeFieldDefinition('items', 'ibexa_object_relation_list');
    }
}
