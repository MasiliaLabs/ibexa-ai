<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Chat\Stream;

use Generator;
use JsonException;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use NeuronAI\Workflow\Streaming\SSEEncoder;

/**
 * Server-Sent Events framing of an AG-UI run: Neuron's own encoder, plus the keep-alive comment (a line starting
 * with a colon, which clients ignore and proxies count as activity).
 */
final class AgUiFrames
{
    public const KEEP_ALIVE_FRAME = ": keep-alive\n\n";

    /**
     * @param Generator<int, ProtocolEvent, mixed, mixed> $events
     * @return Generator<int, string>
     */
    public static function encode(Generator $events): Generator
    {
        while ($events->valid()) {
            $event = $events->current();

            if ($event->type === ReasoningFilteredAGUIAdapter::KEEP_ALIVE) {
                yield self::KEEP_ALIVE_FRAME;
                $events->next();
                continue;
            }

            try {
                $frame = SSEEncoder::frame($event);
            } catch (JsonException $e) {
                // The wire cannot carry this event: raised inside the run, the adapter can close the protocol.
                $events->throw($e);
                continue;
            }

            yield $frame;
            $events->next();
        }
    }
}
