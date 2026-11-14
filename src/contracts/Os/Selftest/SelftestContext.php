<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Os\Selftest;

/**
 * What a scenario of the selftest is given: a way to run a capability and check the answer, the location to work under,
 * and a way to have what it creates deleted at the end.
 */
final readonly class SelftestContext
{
    /**
     * @param \Closure(string, string, array<string, mixed>, ?callable, ?string, bool): void $step
     * @param \Closure(): array<string, array<string, mixed>>                                $answers
     * @param \Closure(int): void                                                            $track
     * @param \Closure(string): void                                                         $skip
     */
    public function __construct(
        private \Closure $step,
        private \Closure $answers,
        private \Closure $track,
        private \Closure $skip,
        /** The location the throwaway contents go under */
        public int $parentLocationId,
        /** A short value that is different on each run, to name what the scenario creates */
        public string $stamp,
    ) {
    }

    /**
     * Runs a capability the way the assistant would (checked, typed, executed) and checks its answer.
     *
     * @param array<string, mixed>     $arguments
     * @param (callable(array<string, mixed>): ?string)|null $check    Returns what is wrong with the answer data, or null
     * @param string|null              $remember The answer is kept under this name, see {@see answer()}
     * @param bool                     $refused  The step must be refused (before or by running): it passes only then
     */
    public function step(string $label, string $capability, array $arguments, ?callable $check = null, ?string $remember = null, bool $refused = false): void
    {
        ($this->step)($label, $capability, $arguments, $check, $remember, $refused);
    }

    /**
     * @return array<string, mixed> The data of an answer kept with $remember (empty when there is none)
     */
    public function answer(string $name): array
    {
        return ($this->answers)()[$name] ?? [];
    }

    /** Has the content, and everything under it, deleted for good at the end of the selftest. */
    public function track(int $contentId): void
    {
        ($this->track)($contentId);
    }

    public function skip(string $reason): void
    {
        ($this->skip)($reason);
    }
}
