<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Api;

/**
 * Encodes stream events as Server-Sent Events lines, in the shape the admin
 * front already reads: token events, a final done event carrying the format,
 * or a single error event.
 */
final class SseEncoder
{
    public function token(string $token): string
    {
        return $this->event(['token' => $token, 'done' => false]);
    }

    public function done(string $format): string
    {
        return $this->event(['token' => '', 'done' => true, 'format' => $format]);
    }

    public function error(ApiError $error): string
    {
        return $this->event($error->toArray());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function event(array $payload): string
    {
        return 'data: ' . json_encode($payload, JSON_THROW_ON_ERROR) . "\n\n";
    }
}
