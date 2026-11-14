<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat;

/** Why a chat request is not run: what the transport (HTTP, here) turns into its own status. */
enum Refusal
{
    /** The request is not what the endpoint takes: no thread, a conversation that does not read, nothing to say. */
    case Invalid;

    /** The message is longer than one message may be. */
    case TooLong;

    /** The conversation belongs to someone else. */
    case Forbidden;
}
