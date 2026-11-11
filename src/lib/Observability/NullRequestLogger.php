<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Observability;

final class NullRequestLogger implements RequestLogger
{
    public function log(RequestLog $entry): void
    {
    }
}
