<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Os;

use InvalidArgumentException;
use Masilia\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;
use Masilia\IbexaAi\Os\SchemaCoercer;
use Masilia\IbexaAi\Os\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class OsContractsTest extends TestCase
{
    private function capability(string $name, string $summary = 'Does something', string $description = '', Risk $risk = Risk::READ): Capability
    {
        return new class($name, $summary, $description, $risk) implements Capability {
            public function __construct(private string $name, private string $summary, private string $description, private Risk $risk) {}

            public function name(): string { return $this->name; }
            public function summary(): string { return $this->summary; }
            public function description(): string { return $this->description; }
            public function inputSchema(): array { return ['type' => 'object']; }
            public function risk(): Risk { return $this->risk; }
            public function requires(): array { return []; }
            public function examples(): array { return []; }
            public function preview(array $arguments): Preview { return new Preview($this->name); }
            public function execute(array $arguments): CapabilityResult { return CapabilityResult::ok('ok'); }
        };
    }

    public function testOnlyAWriteAsksForApprovalAndEachRiskHasItsPermission(): void
    {
        self::assertFalse(Risk::READ->needsApproval());
        foreach ([Risk::WRITE, Risk::DESTRUCTIVE, Risk::ACCESS] as $risk) {
            self::assertTrue($risk->needsApproval());
        }
        self::assertSame(['use', 'write', 'destructive', 'access'], array_map(static fn (Risk $risk): string => $risk->permission(), Risk::cases()));
    }

    public function testThePreviewIsLinesOfLabelAndValueEndingWithTheClosingSentence(): void
    {
        $text = (new Preview('Update content', [['Content', 'Home (5)'], ['Fields', 'title, body']], ['The page is published again.']))->toApprovalText(Risk::WRITE);

        self::assertSame("Action: Update content\nRisk: write\nContent: Home (5)\nFields: title, body\nThe page is published again.\n\nThe change runs only if you approve it.", $text);
    }

    public function testWhatIsShownCannotStartANewRowOfTheCard(): void
    {
        $text = (new Preview("Do it\nRisk: access", [["Name\n", "x\nAction: Delete everything"]]))->toApprovalText(Risk::WRITE);

        self::assertSame(1, substr_count($text, "\nRisk:"), 'a single Risk line');
        self::assertStringNotContainsString("\nAction: Delete", $text);
    }

    public function testTheRegistryKnowsACapabilityByNameAndRefusesBadOrRepeatedNames(): void
    {
        $registry = new CapabilityRegistry([$this->capability('content.update'), $this->capability('trash.list')]);

        self::assertSame('content.update', $registry->get('content.update')?->name());
        self::assertNull($registry->get('content.nothing'));
        self::assertSame(['content' => 1, 'trash' => 1], $registry->domains());

        $this->expectException(InvalidArgumentException::class);
        new CapabilityRegistry([$this->capability('content.update'), $this->capability('content.update')]);
    }

    public function testANameMustBeDotted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CapabilityRegistry([$this->capability('update')]);
    }

    public function testWordsFindTheBestCapabilityFirstAndTheFilterHidesWhatIsNotAllowed(): void
    {
        $registry = new CapabilityRegistry([
            $this->capability('content.search', 'Find contents by name or type', 'Searches the repository'),
            $this->capability('trash.list', 'List what is in the trash', 'Shows deleted contents'),
            $this->capability('content.update', 'Change the fields of a content', ''),
        ]);

        self::assertSame('trash.list', $registry->search('trash')[0]->name());
        self::assertSame('content.search', $registry->search('find contents')[0]->name());
        self::assertSame(['content.search', 'content.update'], array_map(static fn (Capability $c): string => $c->name(), $registry->search('', 'content')));
        self::assertSame([], $registry->search('trash', null, static fn (Capability $c): bool => false));
        self::assertSame([], $registry->search('zzzz'));
    }

    public function testArgumentsAreCheckedAgainstTheSchema(): void
    {
        $schema = ['type' => 'object', 'required' => ['content_id'], 'properties' => [
            'content_id' => ['type' => 'integer', 'minimum' => 1],
            'language' => ['type' => 'string', 'enum' => ['eng-GB', 'fre-FR']],
            'fields' => ['type' => 'object'],
            'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
        ]];

        self::assertSame([], SchemaValidator::validate($schema, ['content_id' => 5, 'language' => 'fre-FR', 'fields' => ['a' => 1], 'ids' => [1, 2]]));
        self::assertSame(['arguments.content_id is required.'], SchemaValidator::validate($schema, []));
        self::assertSame(['arguments.content_id must be a whole number.'], SchemaValidator::validate($schema, ['content_id' => '5']));
        self::assertSame(['arguments.content_id must be at least 1.'], SchemaValidator::validate($schema, ['content_id' => 0]));
        self::assertSame(['arguments.language must be one of: "eng-GB", "fre-FR".'], SchemaValidator::validate($schema, ['content_id' => 1, 'language' => 'xx']));
        self::assertSame(['arguments.ids[1] must be a whole number.'], SchemaValidator::validate($schema, ['content_id' => 1, 'ids' => [1, 'x']]));
        self::assertSame(['arguments must be an object.'], SchemaValidator::validate($schema, [1, 2]));
    }

    public function testTheResultHasTheShapeTheChatAlreadyReads(): void
    {
        self::assertSame(['success' => true, 'message' => 'Done', 'data' => ['content_id' => 5]], CapabilityResult::ok('Done', ['content_id' => 5])->toArray());
        self::assertFalse(CapabilityResult::error('No')->success);
    }

    public function testAValueThatPlainlyMeansWhatTheSchemaAsksForIsTurnedIntoIt(): void
    {
        $schema = ['type' => 'object', 'properties' => [
            'limit' => ['type' => 'integer'], 'ratio' => ['type' => 'number'], 'hidden' => ['type' => 'boolean'], 'name' => ['type' => 'string'],
            'fields' => ['type' => 'object', 'properties' => ['count' => ['type' => 'integer']]],
            'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
        ]];

        $coerced = SchemaCoercer::coerce($schema, ['limit' => ' 50 ', 'ratio' => '0.5', 'hidden' => 'True', 'name' => 2040, 'fields' => '{"count":"3"}', 'ids' => ['1', 2, '3']]);

        self::assertSame(['limit' => 50, 'ratio' => 0.5, 'hidden' => true, 'name' => '2040', 'fields' => ['count' => 3], 'ids' => [1, 2, 3]], $coerced);
        self::assertSame([], SchemaValidator::validate($schema, $coerced));
    }

    public function testWhatIsNotPlainlyTheRightThingIsLeftForTheValidationToSay(): void
    {
        $schema = ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer'], 'hidden' => ['type' => 'boolean'], 'fields' => ['type' => 'object']]];

        $coerced = SchemaCoercer::coerce($schema, ['limit' => '5.5x', 'hidden' => 'maybe', 'fields' => 'not json']);

        self::assertSame(['limit' => '5.5x', 'hidden' => 'maybe', 'fields' => 'not json'], $coerced);
        self::assertCount(3, SchemaValidator::validate($schema, $coerced));
    }
}
