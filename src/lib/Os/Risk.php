<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

/**
 * What a capability can do to the repository. It decides whether the user is asked first, and which permission
 * of the "masilia_ai" module the user needs to use it.
 */
enum Risk: string
{
    /** Reads only. Runs without asking. */
    case READ = 'read';
    /** Creates or changes something that can be changed back. The user approves it first. */
    case WRITE = 'write';
    /** Removes something for good, or something many others depend on. Approved, with a warning. */
    case DESTRUCTIVE = 'destructive';
    /** Changes who can do what: roles, policies, users. Approved, with a warning, and off by default. */
    case ACCESS = 'access';

    public function needsApproval(): bool
    {
        return $this !== self::READ;
    }

    /** The function of the "masilia_ai" permission module a user needs to run a capability of this risk. */
    public function permission(): string
    {
        return match ($this) {
            self::READ => 'use',
            self::WRITE => 'write',
            self::DESTRUCTIVE => 'destructive',
            self::ACCESS => 'access',
        };
    }
}
