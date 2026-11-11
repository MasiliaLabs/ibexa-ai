<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Agent\Stream;

/**
 * Event names and encoding of the agent stream (version 1).
 *
 * Each event is a named Server-Sent Event whose payload is a JSON object with "v" (protocol version).
 * Events are emitted in this order for one run: text.delta and tool.* in any order, then either
 * approval.requested (one per pending change) or nothing, then run.completed. run.error replaces run.completed.
 */
final class AgentEvent
{
    public const VERSION = 1;

    public const TEXT_DELTA = 'text.delta';
    public const TOOL_STARTED = 'tool.started';
    public const TOOL_FINISHED = 'tool.finished';
    public const APPROVAL_REQUESTED = 'approval.requested';
    public const RUN_COMPLETED = 'run.completed';
    public const RUN_ERROR = 'run.error';

    /**
     * @param array<string, mixed> $data
     */
    public static function encode(string $event, array $data): string
    {
        $payload = json_encode(['v' => self::VERSION] + $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return "event: {$event}\ndata: {$payload}\n\n";
    }
}
