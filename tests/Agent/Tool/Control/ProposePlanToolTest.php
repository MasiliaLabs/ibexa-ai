<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Tool\Control;

use Masilia\Bundle\IbexaAi\Agent\Tool\Control\ProposePlanTool;
use Masilia\Bundle\IbexaAi\Agent\Tool\GatedToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolRegistry;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanBuilder;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanExecutor;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The approval gate of propose_plan: a valid plan is gated with a summary, an invalid one is not
 * gated (the model gets the validation message instead), and only the execution path changes content.
 */
#[CoversClass(ProposePlanTool::class)]
final class ProposePlanToolTest extends TestCase
{
    public function testAValidPlanIsGatedWithASummaryTheUserCanRead(): void
    {
        $tool = $this->tool();

        $reason = $tool->approvalReason([
            'intent' => 'create_folder',
            'title' => 'Media',
            'parent_location_id' => 1,
        ]);

        self::assertNotNull($reason);
        self::assertStringContainsString('Intent: create_folder', $reason);
        self::assertStringContainsString('Title: "Media"', $reason);
        self::assertStringContainsString('The change runs only if you approve it.', $reason);
    }

    public function testAnInvalidPlanIsNotGatedSoTheModelCanFixIt(): void
    {
        $tool = $this->tool();

        self::assertNull($tool->approvalReason(['intent' => 'create_folder']));
    }

    public function testTheSummaryNamesTheLanguageAndTheFieldsThatChange(): void
    {
        $reason = $this->tool()->approvalReason([
            'intent' => 'update_content',
            'content_id' => 1342,
            'language' => 'fre-FR',
            'fields' => ['title' => 'Titre', 'body' => '<p>Texte</p>'],
        ]);

        self::assertNotNull($reason);
        self::assertStringContainsString('Language: fre-FR', $reason);
        self::assertStringContainsString('other languages are not changed', $reason);
        self::assertStringContainsString('Fields: title, body', $reason);
    }

    public function testItIsAGatedToolAndAlsoAToolInterface(): void
    {
        $tool = $this->tool();

        self::assertInstanceOf(ToolInterface::class, $tool);
        self::assertInstanceOf(GatedToolInterface::class, $tool);
        self::assertSame('propose_plan', $tool->getName());
    }

    public function testAnInvalidPlanReportsTheValidationErrorWithoutRunning(): void
    {
        $result = $this->tool()->execute(['intent' => 'create_folder']);

        self::assertFalse($result->success);
        self::assertNotSame('', $result->message);
    }

    /**
     * PlanExecutor is final, so it cannot be doubled. An empty registry makes any accidental
     * execution fail with "tool not available", which keeps these tests free of side effects.
     */
    private function tool(): ProposePlanTool
    {
        $executor = new PlanExecutor(new ToolRegistry([]), new NullLogger());

        return new ProposePlanTool(new PlanBuilder(), $executor);
    }
}
