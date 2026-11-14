<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Field\Input;

use Ibexa\Contracts\Core\FieldType\FieldType;
use Ibexa\Core\Base\Exceptions\InvalidArgumentType;
use Ibexa\Core\FieldType\Date\Type as DateType;
use Ibexa\Core\FieldType\DateAndTime\Type as DateAndTimeType;
use Ibexa\Core\FieldType\Float\Type as FloatType;
use Ibexa\Core\FieldType\Integer\Type as IntegerType;
use Ibexa\Core\FieldType\Relation\Type as RelationType;
use Ibexa\Core\FieldType\RelationList\Type as RelationListType;
use Ibexa\Core\FieldType\Url\Type as UrlType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Why the field types of dates, relations, urls and floats have no adapter here, and the integer and float ones have: Ibexa itself
 * turns what a model says into a value (FieldType::acceptValue(), what setField() does). The expected hashes below are what it makes
 * of each form; where it refuses a form that a model gives, the adapter stays (IntegerInput, FloatInput).
 * Arrays go through FieldType::fromHash(), see FieldInputs.
 *
 * Known differences from the adapters that were removed: a date is midnight UTC (the adapter gave midnight in the server's zone), a
 * url given as text has no label (the adapter used the link as its label), a relation given as an integer is accepted (the adapter
 * refused it).
 */
final class NativeEquivalenceTest extends TestCase
{
    /** @return iterable<string, array{FieldType, mixed, array<string, mixed>|int|float|null}> */
    public static function ibexaMakesTheValue(): iterable
    {
        $relation = (new ReflectionClass(RelationType::class))->newInstanceWithoutConstructor();
        $relations = (new ReflectionClass(RelationListType::class))->newInstanceWithoutConstructor();

        yield 'a date as text' => [new DateType(), '2026-06-14', ['timestamp' => 1781395200, 'rfc850' => 'Sunday, 14-Jun-26 00:00:00 UTC']];
        yield 'a date as a phrase' => [new DateType(), '14 June 2026', ['timestamp' => 1781395200, 'rfc850' => 'Sunday, 14-Jun-26 00:00:00 UTC']];
        yield 'a date as a timestamp' => [new DateType(), 1781395200, ['timestamp' => 1781395200, 'rfc850' => 'Sunday, 14-Jun-26 00:00:00 GMT+0000']];
        yield 'a relation by content id' => [$relation, 123, ['destinationContentId' => 123]];
        yield 'a relation by content id as text' => [$relation, '123', ['destinationContentId' => 123]];
        yield 'relations by content ids' => [$relations, [4, 5], ['destinationContentIds' => [4, 5]]];
        yield 'an url as text, without a label' => [new UrlType(), 'https://example.com', ['link' => 'https://example.com', 'text' => null]];
        yield 'a float' => [new FloatType(), 1.5, 1.5];
        yield 'a float given as an integer' => [new FloatType(), 2, 2];
        yield 'a float as text' => [new FloatType(), '2.5', 2.5];
        yield 'a float as padded text' => [new FloatType(), ' 3 ', 3];
    }

    /**
     * @param array<string, mixed>|int|float|null $expected
     */
    #[DataProvider('ibexaMakesTheValue')]
    public function testIbexaTurnsItIntoTheValueItself(FieldType $type, mixed $input, mixed $expected): void
    {
        $hash = $type->toHash($type->acceptValue($input));

        self::assertEquals($expected, $hash);
    }

    public function testADateAndTimeAsTextKeepsItsTimeAndZone(): void
    {
        $hash = (new DateAndTimeType())->toHash((new DateAndTimeType())->acceptValue('2026-06-14T10:00:00+02:00'));

        self::assertSame(1781424000, $hash['timestamp']);
    }

    public function testAnArrayOfAUrlIsReadByFromHash(): void
    {
        $url = new UrlType();

        self::assertEquals(['link' => 'https://example.com', 'text' => 'Example'], $url->toHash($url->fromHash(['link' => 'https://example.com', 'text' => 'Example'])));
    }

    /** @return iterable<string, array{FieldType, mixed}> */
    public static function ibexaRefuses(): iterable
    {
        yield 'an integer as text' => [new IntegerType(), '5'];
        yield 'an integer written as a float' => [new IntegerType(), 5.0];
        yield 'an empty integer as text' => [new IntegerType(), ''];
        yield 'an empty float as text' => [new FloatType(), ''];
        yield 'a blank float as text' => [new FloatType(), '   '];
    }

    #[DataProvider('ibexaRefuses')]
    public function testWhatIbexaRefusesKeepsItsAdapter(FieldType $type, mixed $input): void
    {
        $this->expectException(InvalidArgumentType::class);

        $type->acceptValue($input);
    }
}
