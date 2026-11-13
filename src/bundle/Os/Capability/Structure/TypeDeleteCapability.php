<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class TypeDeleteCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.type.delete';
    }

    public function summary(): string
    {
        return 'Delete a content type that has no contents.';
    }

    public function description(): string
    {
        return 'Deletes the type for good. A type that still has contents cannot be deleted: trash and delete the contents first.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['type'], 'properties' => ['type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the content type']]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['class', 'delete']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete an unused type', 'arguments' => ['type' => 'landing_page']]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingType((string) $arguments['type'])) !== null) {
            return $problem;
        }

        return $this->types()->isContentTypeUsed($this->types()->loadContentTypeByIdentifier((string) $arguments['type'])) ? sprintf('There are contents of type "%s": delete them first (the trash counts).', $arguments['type']) : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a content type', [['Type', (string) $arguments['type']]], ['The type and its fields are deleted for good. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $service->deleteContentType($service->loadContentTypeByIdentifier((string) $arguments['type']));

        return CapabilityResult::ok(sprintf('Content type "%s" was deleted', $arguments['type']), []);
    }
}
