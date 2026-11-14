<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Os\Selftest;

/**
 * Steps a package adds to `masilia:ai:os:selftest` to test its own capabilities end to end, on the same throwaway
 * location as the OS's own. Register one by implementing this interface (it is found by autoconfiguration).
 */
interface SelftestScenario
{
    /** A short name, shown when the scenario is skipped. */
    public function name(): string;

    /**
     * Runs the steps through the context. Throw nothing for a failing step: the context counts it. A scenario that
     * cannot run on this installation says so with {@see SelftestContext::skip()} and returns.
     */
    public function run(SelftestContext $context): void;

    /**
     * Removes whatever the scenario made that the contents registered with {@see SelftestContext::track()} do not
     * cover. Called even when a step threw.
     */
    public function cleanup(): void;
}
