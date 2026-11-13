<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Os\Capability\PlanCapability;

final class CreateCapability extends PlanCapability
{
    public function name(): string
    {
        return 'content.create';
    }

    public function summary(): string
    {
        return 'Create a content of a given type under a parent, and publish it.';
    }

    public function description(): string
    {
        return 'Creates and publishes one content. The content type must exist and the parent location must exist. Field values follow the formats of the '
            . 'content type. With a siteaccess the content is written in a language of that site (the siteaccess\'s own unless you give another one of the site). '
            . 'The answer has content_id and location_id, which later steps of propose_changes can use.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_type', 'parent_location_id', 'fields'],
            'properties' => [
                'content_type' => ['type' => 'string', 'description' => 'Content type identifier, e.g. "article"'],
                'parent_location_id' => ['type' => 'integer', 'minimum' => 1],
                'fields' => ['type' => 'object', 'description' => 'Field identifier => value'],
                'siteaccess' => ['type' => 'string', 'description' => 'The site the content is for: it gives the language'],
                'language' => ['type' => 'string', 'description' => 'Language code or primary code ("fr", "fre-FR"), one of the site\'s'],
            ],
        ];
    }

    public function requires(): array
    {
        return [['content', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'An article under a folder', 'arguments' => ['content_type' => 'article', 'parent_location_id' => 1018, 'fields' => ['title' => 'Hello'], 'siteaccess' => 'fossil_exit']]];
    }

    protected function plan(array $arguments): array
    {
        return ['intent' => Plan::INTENT_CREATE_CONTENT] + array_filter([
            'content_type' => $arguments['content_type'] ?? null,
            'parent_location_id' => $arguments['parent_location_id'] ?? null,
            'fields' => $arguments['fields'] ?? [],
            'siteaccess' => $arguments['siteaccess'] ?? null,
            'language' => $arguments['language'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
