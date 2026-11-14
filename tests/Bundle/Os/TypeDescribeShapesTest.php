<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaAi\Os\Capability\Structure\TypeCapability;
use PHPUnit\Framework\TestCase;

/** structure.type.describe tells the model, for each field, what value to give. */
final class TypeDescribeShapesTest extends TestCase
{
    private function described(string $type, array $settings = []): array
    {
        $field = new FieldDefinition(['identifier' => 'f', 'fieldTypeIdentifier' => $type, 'fieldSettings' => $settings, 'names' => ['eng-GB' => 'F'], 'mainLanguageCode' => 'eng-GB', 'isRequired' => false, 'isTranslatable' => true, 'isSearchable' => true, 'position' => 1]);
        $method = new \ReflectionMethod(TypeCapability::class, 'describeField');

        return $method->invoke(null, $field);
    }

    public function testAFieldSaysWhatValueToGiveWithAnExample(): void
    {
        $selection = $this->described('ibexa_selection', ['options' => ['Dark', 'Light']]);

        self::assertSame(['Dark', 'Light'], $selection['value']['oneOf'][0]['enum']);
        self::assertSame('Dark', $selection['example']);
        self::assertSame('ibexa_selection', $selection['field_type']);
    }

    public function testAFieldTypeNobodyKnowsHasNoShape(): void
    {
        $unknown = $this->described('acme_point');

        self::assertArrayNotHasKey('value', $unknown);
        self::assertArrayNotHasKey('example', $unknown);
    }
}
