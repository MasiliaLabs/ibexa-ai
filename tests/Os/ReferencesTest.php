<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Os;

use Masilia\IbexaAi\Os\References;
use Masilia\IbexaAi\Os\SchemaValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReferencesTest extends TestCase
{
    public function testAReferenceNamesAStepAndAField(): void
    {
        self::assertSame(['folder', 'location_id'], References::parse('$folder.location_id'));
        self::assertNull(References::parse('folder.location_id'));
        self::assertNull(References::parse('$Folder.id'));
        self::assertNull(References::parse('price is $5.00'));
        self::assertNull(References::parse(42));
    }

    public function testTheStepsAValueDependsOnAreFoundAnywhereInIt(): void
    {
        self::assertSame(['a', 'b'], References::steps(['x' => '$a.id', 'y' => ['$b.id', '$a.name', 'plain']]));
        self::assertSame([], References::steps(['x' => 1]));
    }

    public function testAReferenceIsReplacedByWhatTheStepAnswered(): void
    {
        $answers = ['folder' => ['location_id' => 1363, 'content_id' => 1374]];

        self::assertSame(['parent_location_id' => 1363, 'fields' => ['title' => 'Kept', 'link' => 1374]], References::resolve(['parent_location_id' => '$folder.location_id', 'fields' => ['title' => 'Kept', 'link' => '$folder.content_id']], $answers));
    }

    public function testAReferenceToWhatHasNotRunOrWasNotAnsweredIsRefusedWithTheReason(): void
    {
        try {
            References::resolve('$later.id', ['folder' => ['id' => 1]]);
            self::fail('step has not run');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('step "later", which has not run', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not answer a field "nope" (it answered: id, name)');
        References::resolve('$folder.nope', ['folder' => ['id' => 1, 'name' => 'x']]);
    }

    public function testArgumentsWithReferencesCanBeCheckedBeforeTheStepsRun(): void
    {
        $schema = ['type' => 'object', 'required' => ['parent_location_id'], 'properties' => [
            'parent_location_id' => ['type' => 'integer', 'minimum' => 1],
            'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'fields' => ['type' => 'object'],
        ]];
        $arguments = ['parent_location_id' => '$folder.location_id', 'ids' => ['$folder.content_id', 5], 'fields' => ['link' => '$folder.content_id']];

        self::assertSame(['parent_location_id' => 1, 'ids' => [1, 5], 'fields' => ['link' => 'x']], References::standIn($schema, $arguments));
        self::assertSame([], SchemaValidator::validate($schema, References::standIn($schema, $arguments)));
        // Without stand-ins the text would fail the check: this is what they are for.
        self::assertNotSame([], SchemaValidator::validate($schema, $arguments));
    }
}
