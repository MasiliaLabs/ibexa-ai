<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat;

use Masilia\Contracts\IbexaAi\Api\ApiError;
use RuntimeException;

/** A chat request the server does not run, and what to tell the client: the error, and why (the transport picks its own status). */
final class RunRefused extends RuntimeException
{
    public function __construct(public readonly ApiError $error, public readonly Refusal $reason)
    {
        parent::__construct($error->message);
    }

    public static function invalid(string $message): self
    {
        return new self(ApiError::validation($message), Refusal::Invalid);
    }
}
