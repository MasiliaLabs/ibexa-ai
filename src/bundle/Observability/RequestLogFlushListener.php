<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Observability;

use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final readonly class RequestLogFlushListener
{
    public function __construct(
        private DoctrineRequestLogger $logger,
    ) {
    }

    public function onTerminate(TerminateEvent|ConsoleTerminateEvent|ExceptionEvent $event): void
    {
        $this->logger->flush();
    }
}
