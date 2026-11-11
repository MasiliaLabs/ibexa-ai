<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Observability;

/**
 * One model call, as recorded for the usage dashboard. Contains no prompt or answer text.
 */
final readonly class RequestLog
{
    public function __construct(
        public string $providerIdentifier,
        public string $modelIdentifier,
        public ?string $siteaccess,
        public bool $success,
        public int $latencyMs,
        public ?string $errorCode = null,
        public ?int $tokensIn = null,
        public ?int $tokensOut = null,
        public ?string $finishReason = null,
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }
}
