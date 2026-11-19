<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Field\Input;

use Ibexa\Core\FieldType\Author\Value as AuthorValue;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\Input\AuthorInput;
use PHPUnit\Framework\TestCase;

final class AuthorInputTest extends TestCase
{
    private function pairs(mixed $value): array
    {
        $result = (new AuthorInput())->transform(new FieldDefinition(), $value);
        self::assertInstanceOf(AuthorValue::class, $result);

        return array_map(static fn ($a): array => [$a->name, $a->email], iterator_to_array($result->authors));
    }

    public function testItIsForTheAuthorFieldType(): void
    {
        self::assertSame('ibexa_author', (new AuthorInput())->getFieldTypeIdentifier());
    }

    public function testANameWithAnEmailInBrackets(): void
    {
        self::assertSame([['Contact REGAIN', 'contact@regain.org']], $this->pairs('Contact REGAIN <contact@regain.org>'));
    }

    public function testAnEmailAloneIsBothTheNameAndTheAddress(): void
    {
        self::assertSame([['contact@regain.org', 'contact@regain.org']], $this->pairs('contact@regain.org'));
    }

    public function testAPlainNameHasNoAddressForIbexaToRefuseWithItsOwnMessage(): void
    {
        self::assertSame([['REGAIN', '']], $this->pairs('REGAIN'));
    }

    public function testAnObjectAndAListOfObjects(): void
    {
        self::assertSame([['A', 'a@x.org']], $this->pairs(['name' => 'A', 'email' => 'a@x.org']));
        self::assertSame([['A', 'a@x.org'], ['B', 'b@x.org']], $this->pairs([['name' => 'A', 'email' => 'a@x.org'], 'B <b@x.org>']));
    }

    public function testEmptyThingsAreDropped(): void
    {
        self::assertSame([], $this->pairs(['', ['name' => ''], '  ']));
    }

    public function testWhatIsNotTextOrAListIsLeftToIbexa(): void
    {
        self::assertSame(12, (new AuthorInput())->transform(new FieldDefinition(), 12));
    }
}
