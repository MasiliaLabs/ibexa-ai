<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Security;

/**
 * Put on a controller (the class, for all its actions, or one action) to say what the user must be allowed to do. The
 * {@see ApiAccessListener} answers 403 before the action runs, so that an action cannot forget to check it. A request that
 * changes something must also come from the admin interface (see the listener).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class RequiresAccess
{
    public function __construct(public Access $access)
    {
    }
}
