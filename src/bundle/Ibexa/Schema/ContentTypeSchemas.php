<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Schema;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Throwable;

/**
 * The schema of any content type, read from the repository (what the content model says, with no convention about which
 * types are blocks or pages).
 */
final class ContentTypeSchemas implements ContentTypeSchemaSource
{
    /** @var array<string, array{identifier: string, name: string, fields: array<string, array<string, mixed>>}|null> */
    private array $memo = [];

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly FieldSchemas $fields = new FieldSchemas(),
    ) {
    }

    public function schema(string $identifier): ?array
    {
        if (array_key_exists($identifier, $this->memo)) {
            return $this->memo[$identifier];
        }

        try {
            $type = $this->contentTypeService->loadContentTypeByIdentifier($identifier);
        } catch (NotFoundException) {
            return $this->memo[$identifier] = null;
        }

        return $this->memo[$identifier] = [
            'identifier' => $type->getIdentifier(),
            'name' => $type->getName(),
            'fields' => $this->fields->forType($type),
        ];
    }

    public function fields(ContentType $type): array
    {
        return $this->fields->forType($type);
    }

    public function knownTypes(): array
    {
        $known = [];
        try {
            foreach ($this->contentTypeService->loadContentTypeGroups() as $group) {
                foreach ($this->contentTypeService->loadContentTypes($group) as $type) {
                    $known[$type->getIdentifier()] = true;
                }
            }
        } catch (Throwable) {
            return [];
        }
        $names = array_keys($known);
        sort($names);

        return $names;
    }
}
