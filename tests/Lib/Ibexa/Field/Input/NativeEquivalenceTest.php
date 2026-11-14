<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Field\Input;

use Ibexa\Contracts\Core\FieldType\FieldType;
use Ibexa\Core\FieldType\Date\Type as DateType;
use Ibexa\Core\FieldType\DateAndTime\Type as DateAndTimeType;
use Ibexa\Core\FieldType\Float\Type as FloatType;
use Ibexa\Core\FieldType\Integer\Type as IntegerType;
use Ibexa\Core\FieldType\Relation\Type as RelationType;
use Ibexa\Core\FieldType\Url\Type as UrlType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Why the field types of dates, relations, urls and floats have no adapter here, and the integer one has: Ibexa itself turns what a
 * model says into a value (FieldType::acceptValue(), what setField() does), and for the forms below it does. Arrays go through
 * FieldType::fromHash() (see FieldInputs). The integer type only takes a real int, so "5" and 5.0, which a model gives, need IntegerInput.
 */
final class NativeEquivalenceTest extends TestCase
{
    /** @return iterable<string, array{FieldType, mixed}> */
    public static function acceptedByIbexa(): iterable
    {
        $relation = (new ReflectionClass(RelationType::class))->newInstanceWithoutConstructor();

        yield 'a date as text' => [new DateType(), '2026-06-14'];
        yield 'a date as a phrase' => [new DateType(), '14 June 2026'];
        yield 'a date as a timestamp' => [new DateType(), 1781395200];
        yield 'a date and time as text' => [new DateAndTimeType(), '2026-06-14T10:00:00'];
        yield 'a date and time without the T' => [new DateAndTimeType(), '2026-06-14 10:00'];
        yield 'a relation by content id' => [$relation, 123];
        yield 'a relation by content id as text' => [$relation, '123'];
        yield 'an url as text' => [new UrlType(), 'https://example.com'];
        yield 'a float' => [new FloatType(), 1.5];
        yield 'a float given as an integer' => [new FloatType(), 2];
        yield 'a float as text' => [new FloatType(), '2.5'];
        yield 'an integer' => [new IntegerType(), 5];
    }

    #[DataProvider('acceptedByIbexa')]
    public function testIbexaTurnsItIntoAValueItself(FieldType $type, mixed $input): void
    {
        $type->acceptValue($input);
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{mixed}> */
    public static function refusedByIbexa(): iterable
    {
        yield 'an integer as text' => ['5'];
        yield 'an integer written as a float' => [5.0];
        yield 'an empty integer as text' => [''];
    }

    #[DataProvider('refusedByIbexa')]
    public function testTheIntegerTypeOnlyTakesARealIntSoItKeepsItsAdapter(mixed $input): void
    {
        $this->expectException(\Ibexa\Core\Base\Exceptions\InvalidArgumentType::class);

        (new IntegerType())->acceptValue($input);
    }
}
