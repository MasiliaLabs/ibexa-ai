<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Masilia\IbexaAi\Os\Neuron\ToolResult;
use PHPUnit\Framework\TestCase;

final class ToolResultTest extends TestCase
{
    public function testOkCreatesSuccessResult(): void
    {
        $result = ToolResult::ok('Done', ['id' => 1]);

        self::assertTrue($result->success);
        self::assertSame('Done', $result->message);
        self::assertSame(['id' => 1], $result->data);
    }

    public function testOkWithDefaults(): void
    {
        $result = ToolResult::ok();

        self::assertTrue($result->success);
        self::assertSame('', $result->message);
        self::assertSame([], $result->data);
    }

    public function testErrorCreatesFailureResult(): void
    {
        $result = ToolResult::error('Something failed');

        self::assertFalse($result->success);
        self::assertSame('Something failed', $result->message);
        self::assertSame([], $result->data);
    }

    public function testToArrayReturnsCorrectShape(): void
    {
        $result = ToolResult::ok('Test', ['key' => 'value']);

        $array = $result->toArray();

        self::assertSame([
            'success' => true,
            'message' => 'Test',
            'data' => ['key' => 'value'],
        ], $array);
    }

    public function testErrorToArrayReturnsCorrectShape(): void
    {
        $result = ToolResult::error('Error');

        $array = $result->toArray();

        self::assertSame([
            'success' => false,
            'message' => 'Error',
            'data' => [],
        ], $array);
    }

    public function testItIsRecordedAsTheJsonTheChatReadsAndAFailureCarriesNeuronsErrorFlag(): void
    {
        $ok = ToolResult::ok('Done', ['id' => 5])->toOutput();
        $failed = ToolResult::error('Nope')->toOutput();

        self::assertFalse($ok->isError());
        self::assertSame(['success' => true, 'message' => 'Done', 'data' => ['id' => 5]], json_decode((string) $ok, true));
        self::assertTrue($failed->isError());
        self::assertSame(['success' => false, 'message' => 'Nope', 'data' => []], json_decode((string) $failed, true));
    }
}
