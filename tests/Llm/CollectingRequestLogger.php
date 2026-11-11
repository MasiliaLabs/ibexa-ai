<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Llm;

use Masilia\IbexaAi\Observability\RequestLog;
use Masilia\IbexaAi\Observability\RequestLogger;

final class CollectingRequestLogger implements RequestLogger
{
    /** @var list<RequestLog> */
    public array $entries = [];

    public function log(RequestLog $entry): void
    {
        $this->entries[] = $entry;
    }
}
