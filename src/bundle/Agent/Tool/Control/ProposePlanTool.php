<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool\Control;

use Masilia\Bundle\IbexaAi\Agent\Tool\GatedToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanBuilder;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanExecutor;

/**
 * Proposes a content change for approval, then runs it.
 *
 * The approval is the agent's own: the call is gated, the user sees the summary and approves or rejects it.
 * Only an approved call runs the plan, so there is no re-invocation heuristic.
 */
final readonly class ProposePlanTool implements ToolInterface, GatedToolInterface
{
    public function __construct(
        private PlanBuilder $planBuilder,
        private PlanExecutor $planExecutor,
    ) {
    }

    public function getName(): string
    {
        return 'propose_plan';
    }

    public function getDescription(): string
    {
        return 'Propose a content change for the user to approve. The user sees a summary and approves or rejects it. ' .
            'An approved plan runs at once and returns the created or updated content IDs. ' .
            'Use this for every content change, once per plan. ' .
            'Required: intent ("create_content", "create_items", "update_content", ' .
            '"create_folder", "create_site_structure", "trash_content", "restore_content"). ' .
            'Other fields depend on the intent (content_type, title, siteaccess, parent_location_id, fields, content_id, items).';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'intent' => [
                    'type' => 'string',
                    'enum' => Plan::INTENTS,
                    'description' => 'The action to perform',
                ],
                'title' => ['type' => 'string', 'description' => 'Page or content title'],
                'content_type' => ['type' => 'string', 'description' => 'Content type identifier'],
                'siteaccess' => ['type' => 'string', 'description' => 'Front-office siteaccess name'],
                'parent_location_id' => ['type' => 'integer', 'description' => 'Parent location ID'],
                'blocks' => [
                    'type' => 'array',
                    'description' => 'Block definitions (for multi-step page creation via create_content)',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'fields' => ['type' => 'object'],
                        ],
                    ],
                ],
                'fields' => [
                    'type' => 'object',
                    'description' => 'Field values (for create_content, update_content)',
                ],
                'content_id' => ['type' => 'integer', 'description' => 'Content ID (for updates, trash, restore)'],
                'description' => ['type' => 'string', 'description' => 'Description or page summary'],
                'items' => [
                    'type' => 'array',
                    'description' => 'Child items to create (for create_items intent). Each: {type, fields}',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'fields' => ['type' => 'object'],
                        ],
                    ],
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'The language the content is written in: "fre-FR", or just "fr". ALWAYS set it to the language of the text you write (a French page: fr, an English page: en). It must be a language of the site; it is mapped to the site\'s language code. For update_content it writes that translation (created if missing, other languages untouched). Without it, new content is written in the siteaccess\'s own language and an update keeps the content\'s language.',
                ],
                'attachment_id' => [
                    'type' => 'string',
                    'description' => 'For add_image: the id of the image the user attached (the id in its <attachment-image> tag). Also give parent_location_id (an "Images" folder of the media library), title (the name of the image; the file name without its extension by default) and description (its alternative text).',
                ],
                'link_field' => [
                    'type' => 'string',
                    'description' => 'Relation list field on the parent content to link created items to (e.g. "blocks" for pages, "items" for blocks with child items). Used with create_items intent.',
                ],
            ],
            'required' => ['intent'],
        ];
    }
    public function approvalReason(array $arguments): ?string
    {
        try {
            $plan = $this->planBuilder->build($arguments);
        } catch (\InvalidArgumentException) {
            // Invalid plans are not gated: the model gets the validation message and can fix the plan.
            return null;
        }

        return $this->summary($plan) . "\n\nThe change runs only if you approve it.";
    }

    public function execute(array $arguments): ToolResult
    {
        try {
            $plan = $this->planBuilder->build($arguments);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error($e->getMessage());
        }

        $result = $this->planExecutor->execute($plan);
        if (!$result->success) {
            return ToolResult::error($result->message);
        }

        return ToolResult::ok($result->message, array_merge(
            ['content_id' => $result->contentId, 'location_id' => $result->locationId],
            $result->data,
        ));
    }

    private function summary(Plan $plan): string
    {
        $parts = [sprintf('Intent: %s', $plan->intent)];
        if ($plan->title !== null && $plan->title !== '') {
            $parts[] = sprintf('Title: "%s"', $plan->title);
        }
        if ($plan->contentType !== null && $plan->contentType !== '') {
            $parts[] = sprintf('Type: %s', $plan->contentType);
        }
        if ($plan->siteaccess !== null && $plan->siteaccess !== '') {
            $parts[] = sprintf('Siteaccess: %s', $plan->siteaccess);
        }
        if ($plan->parentLocationId !== null) {
            $parts[] = sprintf('Parent: %d', $plan->parentLocationId);
        }
        if ($plan->contentId !== null) {
            $parts[] = sprintf('Content ID: %d', $plan->contentId);
        }
        if ($plan->attachmentId !== null) {
            $parts[] = 'Image: attached by you';
        }
        if ($plan->language !== null) {
            $parts[] = sprintf('Language: %s%s', $plan->language, $plan->intent === Plan::INTENT_UPDATE_CONTENT ? ' (a translation: other languages are not changed)' : '');
        }
        // What will actually change, so the user does not approve a field list they cannot see.
        if (in_array($plan->intent, [Plan::INTENT_CREATE_CONTENT, Plan::INTENT_UPDATE_CONTENT], true) && $plan->fields !== []) {
            $parts[] = sprintf('Fields: %s', implode(', ', array_slice(array_keys($plan->fields), 0, 14)));
        }
        if ($plan->blocks !== []) {
            $parts[] = sprintf('Blocks: %d', count($plan->blocks));
        }
        if ($plan->description !== null && $plan->description !== '') {
            $parts[] = $plan->description;
        }

        return implode("\n", $parts);
    }
}
