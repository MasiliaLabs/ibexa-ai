<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Audit;

/**
 * Where the current run comes from, so that what it does can be recorded with it. Set once per request by the chat.
 */
final class RunContext
{
    public ?string $threadId = null;
    public ?string $siteaccess = null;

    public function begin(string $threadId, string $siteaccess): void
    {
        $this->threadId = $threadId;
        $this->siteaccess = $siteaccess;
    }
}
