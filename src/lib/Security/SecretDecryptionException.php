<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Security;

use RuntimeException;

/**
 * A stored secret cannot be read: it was written with another key, or it was altered.
 */
final class SecretDecryptionException extends RuntimeException
{
}
