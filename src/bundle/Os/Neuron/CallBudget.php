<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Neuron;

use ArrayObject;

/**
 * A call budget per tool and per run. Past it the tool is not executed any more: the model is told to answer with what it
 * has, and the turn goes on. Neuron's own limit (setMaxRuns), which aborts the whole run with an exception, is kept a few
 * calls above as a last resort against a loop.
 *
 * The count lives in an object shared by the clones Neuron makes of the tool for each call.
 */
trait CallBudget
{
    public const CALL_BUDGET = 8;

    private const HARD_LIMIT_MARGIN = 4;

    /** @var ArrayObject<string, int>|null */
    private ?ArrayObject $calls = null;

    private function startBudget(): void
    {
        $this->calls = new ArrayObject(['count' => 0]);
        $this->setMaxRuns(self::CALL_BUDGET + self::HARD_LIMIT_MARGIN);
    }

    /** Counts this call; the refusal to give the model when the budget is spent, null otherwise. */
    private function overBudget(): ?ToolResult
    {
        $this->calls ??= new ArrayObject(['count' => 0]);
        $this->calls['count'] += 1;
        if ($this->calls['count'] <= self::CALL_BUDGET) {
            return null;
        }

        return ToolResult::error(sprintf(
            'Call budget reached: %s was already called %d times in this turn and was NOT run again. '
            . 'Do not call it again. Answer the user now with what you found, and say plainly what you could not check.',
            $this->getName(),
            self::CALL_BUDGET,
        ));
    }
}
