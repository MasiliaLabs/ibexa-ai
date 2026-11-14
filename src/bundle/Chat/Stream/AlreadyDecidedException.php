<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Chat\Stream;

use RuntimeException;

/**
 * A decision arrived for a change that is no longer waiting: it was decided already, typically in another window.
 */
final class AlreadyDecidedException extends RuntimeException
{
}
