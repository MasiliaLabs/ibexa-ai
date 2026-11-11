<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Api;

use Masilia\IbexaAi\Api\ApiError;
use Masilia\IbexaAi\Api\SseEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SseEncoder::class)]
#[CoversClass(ApiError::class)]
final class SseEncoderTest extends TestCase
{
    public function testTokenLineMatchesTheFrontContract(): void
    {
        self::assertSame("data: {\"token\":\"Hi\",\"done\":false}\n\n", (new SseEncoder())->token('Hi'));
    }

    public function testDoneLineCarriesTheFormat(): void
    {
        self::assertSame(
            "data: {\"token\":\"\",\"done\":true,\"format\":\"html\"}\n\n",
            (new SseEncoder())->done('html'),
        );
    }

    public function testErrorLineUsesTheErrorEnvelope(): void
    {
        $line = (new SseEncoder())->error(ApiError::serviceUnavailable('down'));

        self::assertSame("data: {\"error\":{\"code\":\"SERVICE_UNAVAILABLE\",\"message\":\"down\"}}\n\n", $line);
    }

    public function testAccessDeniedEnvelope(): void
    {
        self::assertSame(
            ['error' => ['code' => 'ACCESS_DENIED', 'message' => 'Access Denied']],
            ApiError::accessDenied()->toArray(),
        );
    }
}
