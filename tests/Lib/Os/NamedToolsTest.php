<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Masilia\IbexaAi\Os\Neuron\ConversationToolSearch;
use Masilia\IbexaAi\Os\Neuron\OsAgentFactory;
use NeuronAI\Chat\Messages\UserMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * A model calls what it has read about. The prompt names capabilities ("site.conventions"), and a tool found at an earlier turn is
 * named in the history: both must be callable, or the run fails with "non-existing tool".
 */
final class NamedToolsTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function texts(): iterable
    {
        yield 'with dots, in backticks' => ['Read `site.explore` and `site.conventions` first.', 'site.conventions', true];
        yield 'with its arguments' => ['`site.conventions(siteaccess?, location_id?)`: how pages are built', 'site.conventions', true];
        yield 'as a tool name' => ['call site__conventions now', 'site.conventions', true];
        yield 'at the end of a sentence' => ['See content.trash.', 'content.trash', true];
        yield 'not a longer name' => ['use content.create_folder', 'content.create', false];
        yield 'not inside another word' => ['xcontent.trash', 'content.trash', false];
        yield 'not named' => ['nothing here', 'site.conventions', false];
    }

    #[DataProvider('texts')]
    public function testACapabilityIsNamedWhenTheTextSaysItsName(string $text, string $capability, bool $named): void
    {
        self::assertSame($named, OsAgentFactory::isNamedIn($text, $capability));
    }

    use EveryCapability;

    public function testTheCapabilitiesThePromptNamesAreTheOnesExposed(): void
    {
        $skill = new class() implements \Masilia\Contracts\IbexaAi\Os\Skill {
            public function name(): string { return 'craft'; }
            public function instructions(): string { return 'Read `site.explore`, then call `url.alias.list` and site__conventions.'; }
        };
        $prompt = \Masilia\IbexaAi\Os\Neuron\OsSystemPrompt::get([$skill]);

        $named = [];
        foreach (self::capabilities() as [$class]) {
            $name = $this->make($class)->name();
            if (OsAgentFactory::isNamedIn($prompt, $name)) {
                $named[] = $name;
            }
        }

        // What the core prompt and the skill name, and nothing else (url.wildcard.create is in the pool, in no text).
        self::assertContains('structure.type.describe', $named);
        self::assertContains('relations.list', $named);
        self::assertContains('content.trash', $named);
        self::assertContains('url.alias.list', $named, 'named by a skill, with its dots');
        self::assertNotContains('url.wildcard.create', $named);
        self::assertNotContains('content.create_folder', $named);
        self::assertLessThan(30, count($named), 'a handful of tools, not the whole pool: ' . implode(', ', $named));
    }
}
