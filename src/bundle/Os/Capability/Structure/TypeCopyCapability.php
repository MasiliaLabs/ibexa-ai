<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class TypeCopyCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.type.copy';
    }

    public function summary(): string
    {
        return 'Copy a content type (without contents).';
    }

    public function description(): string
    {
        return 'Creates a copy of the type with all its fields, under a generated identifier (copy_of_...). Rename it with structure.type.update.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['type'], 'properties' => ['type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the content type']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['class', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'Start from an existing type', 'arguments' => ['type' => 'article']]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missingType((string) $arguments['type']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Copy a content type', [['Type', (string) $arguments['type']]], ['The copy has no contents.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $copy = $this->types()->copyContentType($this->types()->loadContentTypeByIdentifier((string) $arguments['type']));

        return CapabilityResult::ok(sprintf('Copied to "%s"', $copy->identifier), ['type' => $copy->identifier]);
    }
}
