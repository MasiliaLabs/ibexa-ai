<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Rest\Input\FieldTypeParser;
use Throwable;

/**
 * What a model says for a field, turned into what Ibexa stores: the adapter of the field type when there is one (dates as
 * text, labels of a selection, HTML for a rich text, a matrix as rows…), otherwise the field type's own reading of its
 * hash, read by the REST API's own FieldTypeParser (the processors of the field type, then FieldType::fromHash). A value the field type refuses is handed over as it is:
 * the repository then refuses it with its own, precise message.
 *
 * The adapters that remain exist for what a model says and Ibexa does not read: a label of a selection, "yes" for a boolean, HTML or
 * Markdown for a rich text, a list of words, a matrix with controlled columns, an image attached to the chat. The other values
 * (dates, urls, relations, floats…) are Ibexa's own reading: see NativeEquivalenceTest.
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
        private ?FieldTypeParser $parser = null,
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
        if ($this->parser === null || !is_array($value)) {
            return $value;
        }
        try {
            return $this->parser->parseValue($type, $value);
        } catch (Throwable) {
            return $value;
        }
    }
}
