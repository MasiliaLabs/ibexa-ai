<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Throwable;

/**
 * What a model says for a field, turned into what Ibexa stores: the adapter of the field type when there is one (dates as
 * text, labels of a selection, HTML for a rich text, a matrix as rows…), otherwise the field type's own reading of its
 * hash (FieldType::fromHash), the way the REST API reads a value. A value the field type refuses is handed over as it is:
 * the repository then refuses it with its own, precise message.
 */
readonly class FieldInputs
{
    /** @var array<string, FieldInput> */
    private array $adapters;

    /**
     * @param iterable<FieldInput> $adapters Tagged services
     */
    public function __construct(
        iterable $adapters,
        private ?FieldTypeService $fieldTypes = null,
    ) {
        $map = [];
        foreach ($adapters as $adapter) {
            $map[$adapter->getFieldTypeIdentifier()] = $adapter;
        }
        $this->adapters = $map;
    }

    /**
     * @param FieldDefinition $fieldDef Drives the choice of the adapter and gives it the settings (the options of a selection)
     * @param mixed           $value    What the model said
     *
     * @return mixed A value ready for ContentStruct::setField()
     */
    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        $type = $fieldDef->getFieldTypeIdentifier();
        $adapter = $this->adapters[$type] ?? null;
        if ($adapter !== null) {
            return $adapter->transform($fieldDef, $value);
        }

        return $this->fromHash($type, $value);
    }

    private function fromHash(string $type, mixed $value): mixed
    {
        if ($this->fieldTypes === null || !is_array($value)) {
            return $value;
        }
        try {
            return $this->fieldTypes->getFieldType($type)->fromHash($value);
        } catch (Throwable) {
            return $value;
        }
    }
}
