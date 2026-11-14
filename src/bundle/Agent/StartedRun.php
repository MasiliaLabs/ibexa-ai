<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent;

/** A chat run that is under way: the headers of its answer and its AG-UI frames, produced as the agent works. */
final readonly class StartedRun
{
    /**
     * @param array<string, string>    $headers
     * @param iterable<int, string>    $frames  Server-Sent Events frames, each ready to be written to the client
     */
    public function __construct(public array $headers, public iterable $frames)
    {
    }
}
