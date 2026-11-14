<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Masilia\IbexaAi\Os\Neuron\CapabilityTool;
use Masilia\IbexaAi\Os\Neuron\OsAgent;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\Execution\CapabilityRunner;
use NeuronAI\Agent\Middleware\ToolSearchTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the model asks tool_search for, in words, against what the package ships: the capability it needs must be among the ones
 * it gets back. A name or a description that stops saying what a capability does fails here, not in front of a user.
 */
final class ToolSearchRecallTest extends TestCase
{
    use EveryCapability;

    /** @return iterable<string, array{string, string}> The words of the model (keywords, as tool_search asks for), the capability it needs */
    public static function requests(): iterable
    {
        $asked = [
            'move a page to another folder' => 'location.move',
            'copy a page' => 'location.copy',
            'copy a folder with everything in it' => 'location.copy_subtree',
            'hide content' => 'content.set_visibility',
            'publish the draft of an article' => 'content.publish',
            'start editing a content, a new draft' => 'content.draft.create',
            'change the title of an article' => 'content.update',
            'create an article' => 'content.create',
            'put a content in the trash' => 'content.trash',
            'get something back from the trash' => 'trash.restore',
            'empty the trash' => 'trash.empty',
            'translate a page into french' => 'localization.translation.add',
            'add a language to the site' => 'localization.language.create',
            'which languages are enabled' => 'localization.language.list',
            'rename a section' => 'section.update',
            'put a content in a section' => 'section.assign',
            'add a field to a content type' => 'structure.field.add',
            'create a content type' => 'structure.type.create',
            'content type fields' => 'structure.type.describe',
            'add an extra url alias to a page' => 'url.alias.add',
            'redirect an old address with a wildcard' => 'url.wildcard.create',
            'change the state of a content' => 'state.set',
            'which contents point at this one' => 'relations.list',
            'add an image to the media library' => 'media.add_image',
            'locations of a content' => 'location.list',
            'swap two pages' => 'location.swap',
            'remove one of the places of a content' => 'location.remove',
            'which drafts do I have' => 'workspace.drafts.list',
            'my bookmarks' => 'workspace.bookmarks.list',
            'unread notifications' => 'workspace.notifications.list',
        ];
        foreach ($asked as $words => $capability) {
            yield $words => [$words, $capability];
        }
    }

    /** @return list<CapabilityTool> */
    private function tools(): array
    {
        // The search reads names and descriptions only: nothing is run.
        $runner = (new \ReflectionClass(CapabilityRunner::class))->newInstanceWithoutConstructor();
        $tools = [];
        foreach (self::capabilities() as [$class]) {
            $tools[] = new CapabilityTool($this->make($class), $runner);
        }

        return $tools;
    }

    #[DataProvider('requests')]
    public function testTheCapabilityThatIsNeededIsAmongTheOnesFound(string $words, string $capability): void
    {
        $found = array_map(static fn (CapabilityTool $tool): string => $tool->capability()->name(), (new ToolSearchTool($this->tools(), OsAgent::SEARCH_RESULTS))->search($words));

        self::assertContains($capability, $found, sprintf('"%s" brought %s', $words, implode(', ', $found)));
    }

    public function testThePoolIsAsBigAsTheCapabilitiesTheTestsKnow(): void
    {
        self::assertGreaterThanOrEqual(90, count($this->tools()));
    }
}
