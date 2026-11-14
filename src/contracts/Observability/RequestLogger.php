<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Observability;

interface RequestLogger
{
    public function log(RequestLog $entry): void;
}
