<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Stream;

use RuntimeException;

/**
 * The model ended its turn with reasoning only: no text and no tool call.
 */
final class NoAnswerException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The model produced nothing to show.');
    }
}
