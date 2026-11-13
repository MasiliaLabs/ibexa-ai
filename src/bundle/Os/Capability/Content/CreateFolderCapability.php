<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Os\Capability\PlanCapability;

final class CreateFolderCapability extends PlanCapability
{
    public function name(): string
    {
        return 'content.create_folder';
    }

    public function summary(): string
    {
        return 'Create a folder under a location.';
    }

    public function description(): string
    {
        return 'Creates and publishes a folder with the given name under a parent location. The answer has content_id and location_id, '
            . 'which later steps of propose_changes can use as the parent of what goes in it.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'parent_location_id'],
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                'parent_location_id' => ['type' => 'integer', 'minimum' => 1],
                'siteaccess' => ['type' => 'string', 'description' => 'The site the folder is for: it gives the language'],
                'language' => ['type' => 'string', 'description' => 'Language code or primary code, one of the site\'s'],
            ],
        ];
    }

    public function requires(): array
    {
        return [['content', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'A folder at the root of a site', 'arguments' => ['name' => 'Press kit', 'parent_location_id' => 1018, 'siteaccess' => 'fossil_exit']]];
    }

    protected function plan(array $arguments): array
    {
        return ['intent' => Plan::INTENT_CREATE_FOLDER] + array_filter([
            'title' => $arguments['name'] ?? null,
            'parent_location_id' => $arguments['parent_location_id'] ?? null,
            'siteaccess' => $arguments['siteaccess'] ?? null,
            'language' => $arguments['language'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
