<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Stream;

use RuntimeException;

/**
 * A new message arrived while a change still waits for its decision: the run is suspended and takes no message.
 */
final class DecisionPendingException extends RuntimeException
{
}
