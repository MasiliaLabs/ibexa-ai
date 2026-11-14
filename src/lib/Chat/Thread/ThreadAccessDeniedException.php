<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Thread;

use RuntimeException;

/**
 * The conversation exists and belongs to another user.
 */
final class ThreadAccessDeniedException extends RuntimeException
{
}
