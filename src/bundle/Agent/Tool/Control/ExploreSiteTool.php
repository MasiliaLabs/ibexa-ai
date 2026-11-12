<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool\Control;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Agent\Worker\SiteExplorer;


/**
 * Single composite exploration tool.
 *
 * Replaces 4 separate tools (browse_site_structure, find_parent_candidates,
 * list_blocks, load_siteaccess) with ONE call. Solves the LLM-loop problem:
 * the orchestrator can only call this once per turn, and it always returns
 * a unified ExplorationResult.
 *
 * Requires a `siteaccess` parameter (the front-office site the user wants
 * to manage, e.g. "fossilexit"). If the user hasn't specified one, the LLM
 * should call ask_user first to get it.
 */
final readonly class ExploreSiteTool implements ToolInterface
{
    public function __construct(
        private SiteExplorer $explorer,
    ) {
    }

    public function getName(): string
    {
        return 'explore_site';
    }

    public function getDescription(): string
    {
        return 'Discover front-office siteaccesses and explore the structure of the specified one. ' .
            'Returns: list of all configured siteaccesses, the matched siteaccess from user input, ' .
            'its root location ID, the site tree under that root, candidate parent locations, ' .
            'and available block types. Call this ONCE per request when you need site context.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'siteaccess' => [
                    'type' => 'string',
                    'description' => 'Front-office siteaccess the user named. Fuzzy match is supported ' .
                        '(a name with spaces or different casing still matches). Never guess a siteaccess: ' .
                        'if the user did not name a site, leave this empty to get the list of siteaccesses, ' .
                        'then ask the user which one they mean.',
                ],
            ],
        ];
    }

    public function execute(array $arguments): ToolResult
    {
        $siteaccess = trim((string) ($arguments['siteaccess'] ?? ''));

        $result = $this->explorer->explore($siteaccess);

        if ($siteaccess === '' && !$result->hasError()) {
            return ToolResult::ok(
                'No siteaccess was given. Available siteaccesses: ' . implode(', ', $result->siteaccesses) .
                '. Ask the user which one they mean; do not pick one yourself.',
                $result->toArray(),
            );
        }

        if ($result->hasError()) {
            return ToolResult::error($result->errorMessage ?? 'Exploration failed');
        }

        if (!$result->hasMatchedSiteaccess()) {
            return ToolResult::ok(
                sprintf(
                    'Siteaccess "%s" not found. Available siteaccesses: %s',
                    $siteaccess,
                    implode(', ', $result->siteaccesses),
                ),
                $result->toArray(),
            );
        }

        return ToolResult::ok(
            sprintf(
                'Explored %d siteaccesses. Matched: %s (root location %d).',
                count($result->siteaccesses),
                $result->matchedSiteaccess,
                $result->rootLocationId ?? 0,
            ),
            $result->toArray(),
        );
    }
}
