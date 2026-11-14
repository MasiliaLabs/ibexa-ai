<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Attachment;

use RuntimeException;

/**
 * The file cannot be kept as an attachment; the message says why, for the user.
 */
final class AttachmentRejectedException extends RuntimeException
{
}
