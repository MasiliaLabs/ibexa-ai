<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Observability;

interface RequestLogger
{
    public function log(RequestLog $entry): void;
}
