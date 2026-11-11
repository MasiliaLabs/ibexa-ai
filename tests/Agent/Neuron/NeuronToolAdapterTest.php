<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Neuron;

use Masilia\Bundle\IbexaAi\Agent\Neuron\NeuronToolAdapter;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use PHPUnit\Framework\TestCase;

final class NeuronToolAdapterTest extends TestCase
{
    public function testPastItsBudgetAToolIsNotRunAndTheModelIsToldToAnswer(): void
    {
        $tool = new CountingTool();
        $adapter = new NeuronToolAdapter($tool);

        for ($call = 0; $call < NeuronToolAdapter::CALL_BUDGET; ++$call) {
            $adapter->execute();
        }
        self::assertSame(NeuronToolAdapter::CALL_BUDGET, $tool->runs);
        self::assertTrue($this->decodedResult($adapter)['success']);

        $adapter->execute();

        self::assertSame(NeuronToolAdapter::CALL_BUDGET, $tool->runs, 'The tool must not run past its budget.');
        $refused = $this->decodedResult($adapter);
        self::assertFalse($refused['success']);
        self::assertStringContainsString('Call budget reached', $refused['message']);
        self::assertStringContainsString('Answer the user now', $refused['message']);
    }

    public function testTheBudgetIsSharedByTheCopiesOfATool(): void
    {
        $tool = new CountingTool();
        $adapter = new NeuronToolAdapter($tool);

        // Neuron works on copies of a tool for each call: the budget must follow them.
        for ($call = 0; $call < NeuronToolAdapter::CALL_BUDGET + 2; ++$call) {
            (clone $adapter)->execute();
        }

        self::assertSame(NeuronToolAdapter::CALL_BUDGET, $tool->runs);
    }

    public function testNeuronHardLimitStaysAboveTheBudget(): void
    {
        $adapter = new NeuronToolAdapter(new CountingTool());

        self::assertGreaterThan(NeuronToolAdapter::CALL_BUDGET, $adapter->getMaxRuns());
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function decodedResult(NeuronToolAdapter $adapter): array
    {
        return json_decode((string) $adapter->getResult(), true, 512, JSON_THROW_ON_ERROR);
    }
}

final class CountingTool implements ToolInterface
{
    public int $runs = 0;

    public function getName(): string
    {
        return 'browse_location';
    }

    public function getDescription(): string
    {
        return 'Counts its runs.';
    }

    public function getParameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): ToolResult
    {
        ++$this->runs;

        return ToolResult::ok('ran');
    }
}
