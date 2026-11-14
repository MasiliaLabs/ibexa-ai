<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Ibexa\Field;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Ibexa\Field\FieldValueShapes;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\FieldInput;
use Masilia\Bundle\IbexaAi\Os\Neuron\OsSystemPrompt;
use Masilia\IbexaAi\Os\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** What the model is told to give for each field type is what the adapters take: one list, checked against them. */
final class FieldValueShapesTest extends TestCase
{
    private function definition(string $type, array $settings = []): FieldDefinition
    {
        $definition = $this->createMock(FieldDefinition::class);
        $definition->method('getFieldTypeIdentifier')->willReturn($type);
        $definition->method('getFieldSettings')->willReturn($settings);

        return $definition;
    }

    /** @return iterable<string, array{string}> */
    public static function adapterTypes(): iterable
    {
        foreach (glob(__DIR__ . '/../../../src/bundle/Ibexa/Field/Input/*Input.php') ?: [] as $file) {
            $class = 'Masilia\\Bundle\\IbexaAi\\Ibexa\\Field\\Input\\' . basename($file, '.php');
            $reflection = new ReflectionClass($class);
            if ($reflection->isInterface() || $reflection->isAbstract() || !$reflection->implementsInterface(FieldInput::class)) {
                continue;
            }
            /** @var FieldInput $adapter */
            $adapter = $reflection->newInstanceWithoutConstructor();
            yield basename($file, '.php') => [$adapter->getFieldTypeIdentifier()];
        }
    }

    #[DataProvider('adapterTypes')]
    public function testEveryTypeAnAdapterTakesIsDescribedToTheModel(string $type): void
    {
        $shape = FieldValueShapes::for($this->definition($type, ['options' => ['Dark'], 'columns' => [['identifier' => 'label']]]));

        self::assertNotNull($shape, $type . ' has an adapter but the model is not told what to give');
        self::assertArrayHasKey('schema', $shape);
        self::assertNotNull($shape['example']);
    }

    /** @return iterable<string, array{string}> */
    public static function documentedTypes(): iterable
    {
        foreach (array_keys(FieldValueShapes::documented()) as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('documentedTypes')]
    public function testTheExampleOfATypeFollowsItsOwnSchema(string $type): void
    {
        $shape = FieldValueShapes::for($this->definition($type));
        self::assertNotNull($shape);

        $branches = $shape['schema']['oneOf'] ?? [$shape['schema']];
        $accepted = array_filter($branches, static fn (array $branch): bool => SchemaValidator::validate($branch, $shape['example']) === []);

        self::assertNotSame([], $accepted, $type . ': the example breaks its own schema');
    }

    public function testASelectionOffersItsOptionsAndAMatrixItsColumns(): void
    {
        $selection = FieldValueShapes::for($this->definition('ibexa_selection', ['options' => ['Dark', 'Light']]));
        $matrix = FieldValueShapes::for($this->definition('ibexa_matrix', ['columns' => [['identifier' => 'icon', 'name' => 'Icon'], ['identifier' => 'label', 'name' => 'Label']]]));

        self::assertSame(['Dark', 'Light'], $selection['schema']['oneOf'][0]['enum']);
        self::assertSame('Dark', $selection['example']);
        self::assertSame(['icon', 'label'], $matrix['schema']['items']['required']);
        self::assertSame([['icon' => 'text', 'label' => 'text']], $matrix['example']);
    }

    public function testATypeNobodyKnowsHasNoShapeAndIsLeftToIbexa(): void
    {
        self::assertNull(FieldValueShapes::for($this->definition('acme_point')));
    }

    public function testThePromptTellsTheFormatsOfTheTypesThatHaveNoSetting(): void
    {
        $prompt = OsSystemPrompt::get();

        foreach (array_keys(FieldValueShapes::documented()) as $type) {
            self::assertStringContainsString('`' . $type . '`', $prompt);
        }
        self::assertStringNotContainsString('{{FORMATS}}', $prompt);
        self::assertStringContainsString('Never DocBook XML', $prompt);
    }
}
