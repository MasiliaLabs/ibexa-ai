<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Agent\Stream;

/**
 * The outcome of one tool call, as shown in the timeline.
 *
 * Tools answer with a JSON object {success, message, data}. Neuron answers a rejected or skipped
 * call with its own text instead, which must not look like a success.
 */
final class ToolOutcome
{
    private const NOT_EXECUTED_PREFIX = 'TOOL NOT EXECUTED';
    private const MAX_MESSAGE_LENGTH = 500;

    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public static function fromRaw(string $raw): array
    {
        if (str_starts_with($raw, self::NOT_EXECUTED_PREFIX)) {
            return ['ok' => false, 'message' => 'Not run: the change was rejected.', 'data' => []];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return [
                'ok' => (bool) ($decoded['success'] ?? false),
                'message' => self::clip((string) ($decoded['message'] ?? '')),
                'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
            ];
        }

        return ['ok' => true, 'message' => self::clip($raw), 'data' => []];
    }

    private static function clip(string $text): string
    {
        return mb_strlen($text) > self::MAX_MESSAGE_LENGTH
            ? mb_substr($text, 0, self::MAX_MESSAGE_LENGTH) . '…'
            : $text;
    }
}
