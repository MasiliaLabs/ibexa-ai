<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Relations;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Relation;
use Ibexa\Contracts\Core\Repository\Values\Content\RelationList\RelationListItemInterface;
use Masilia\Bundle\IbexaAi\Os\Capability\BaseCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class ListCapability extends BaseCapability
{
    private const TYPES = [Relation::COMMON => 'common', Relation::EMBED => 'embed', Relation::LINK => 'link', Relation::FIELD => 'field', Relation::ASSET => 'asset'];

    private const LIMIT = 100;

    public function __construct(private readonly Repository $repository)
    {
    }

    public function name(): string
    {
        return 'relations.list';
    }

    public function summary(): string
    {
        return 'List what a content points to (its relations) and what points to it (reverse relations).';
    }

    public function description(): string
    {
        return 'Relations come from relation fields, links and embeds in rich text. "outgoing" are the contents this one refers to, "incoming" the contents '
            . 'that refer to it: check the incoming ones before trashing or deleting a content. Each item has content_id, name, content_type and the kind of relation.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'What refers to a page before it is trashed', 'arguments' => ['content_id' => 1353]]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentService();
        $info = $service->loadContentInfo((int) $arguments['content_id']);

        $outgoing = $service->loadRelationList($service->loadVersionInfo($info), 0, self::LIMIT);
        $incoming = $service->loadReverseRelationList($info, 0, self::LIMIT);

        return CapabilityResult::ok(
            sprintf('"%s" points to %d content(s) and %d point to it', $info->name, $outgoing->totalCount, $incoming->totalCount),
            [
                'outgoing' => $this->items($outgoing->items, false),
                'incoming' => $this->items($incoming->items, true),
                'outgoing_total' => $outgoing->totalCount,
                'incoming_total' => $incoming->totalCount,
            ],
        );
    }

    /**
     * @param list<RelationListItemInterface> $items
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $items, bool $source): array
    {
        $rows = [];
        foreach ($items as $item) {
            $relation = $item->getRelation();
            if ($relation === null) {
                $rows[] = ['name' => '(a content you may not read)'];
                continue;
            }
            $info = $source ? $relation->getSourceContentInfo() : $relation->getDestinationContentInfo();
            $rows[] = [
                'content_id' => $info->id,
                'name' => $info->name,
                'relation' => self::TYPES[$relation->type] ?? 'other',
                'field' => $relation->sourceFieldDefinitionIdentifier,
            ];
        }

        return $rows;
    }
}
