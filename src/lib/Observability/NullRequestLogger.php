<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Observability;

use Masilia\Contracts\IbexaAi\Observability\RequestLog;
use Masilia\Contracts\IbexaAi\Observability\RequestLogger;

final class NullRequestLogger implements RequestLogger
{
    public function log(RequestLog $entry): void
    {
    }
}
