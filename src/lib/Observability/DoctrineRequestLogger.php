<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Observability;

use Masilia\IbexaAi\Persistence\Entity\AiRequestLog;
use Masilia\Contracts\IbexaAi\Observability\RequestLog;
use Masilia\Contracts\IbexaAi\Observability\RequestLogger;
use Doctrine\Persistence\ManagerRegistry;
use Throwable;

/**
 * Buffers request logs during the request and writes them once the response is sent
 * (see {@see RequestLogFlushListener}), so streamed answers are not slowed by database writes.
 */
final class DoctrineRequestLogger implements RequestLogger
{
    /** @var list<RequestLog> */
    private array $buffer = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
    }

    public function log(RequestLog $entry): void
    {
        $this->buffer[] = $entry;
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $entries = $this->buffer;
        $this->buffer = [];

        try {
            $manager = $this->registry->getManagerForClass(AiRequestLog::class);
            if ($manager === null) {
                return;
            }
            foreach ($entries as $entry) {
                $manager->persist(AiRequestLog::fromLog($entry));
            }
            $manager->flush();
        } catch (Throwable) {
            // Logging must never break the editor's request.
        }
    }
}
