<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Observability;

/** The start of the call to the model that is in flight, to give its latency when it ends. */
final class ModelCallClock
{
    private ?int $startedAt = null;

    public function start(): void
    {
        $this->startedAt = hrtime(true);
    }

    public function isRunning(): bool
    {
        return $this->startedAt !== null;
    }

    /** Stops the clock: the milliseconds since it started, or null when no call was in flight. */
    public function stop(): ?int
    {
        if ($this->startedAt === null) {
            return null;
        }
        $elapsed = (int) round((hrtime(true) - $this->startedAt) / 1_000_000);
        $this->startedAt = null;

        return $elapsed;
    }
}
