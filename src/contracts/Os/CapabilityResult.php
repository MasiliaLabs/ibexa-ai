<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Os;

/**
 * What a capability answers: the same shape the chat already reads for a tool (success, message, data).
 */
final readonly class CapabilityResult
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function ok(string $message, array $data = []): self
    {
        return new self(true, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function error(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['success' => $this->success, 'message' => $this->message, 'data' => $this->data];
    }
}
