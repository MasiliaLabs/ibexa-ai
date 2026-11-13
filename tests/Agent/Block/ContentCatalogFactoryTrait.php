<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Block;

use Masilia\Bundle\IbexaAi\Agent\Block\ContentTypeSchemas;

/**
 * @internal test-only
 */
trait ContentCatalogFactoryTrait
{
    use BlockCatalogFactoryTrait;

    /**
     * Build {@see ContentTypeSchemas} from content type definitions organized by group name
     * (the groups do not matter to it: the content types are read by identifier).
     *
     * @param array<string, array<string, array{name: string, fields: array<string, string|array{type: string, settings?: array<string, mixed>, validator?: array<string, mixed>, required?: bool}>}>> $groups
     */
    private function createContentCatalog(array $groups): ContentTypeSchemas
    {
        return $this->createBlockCatalog(array_merge(...array_values($groups)));
    }
}
