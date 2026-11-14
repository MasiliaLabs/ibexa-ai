<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool;

/**
 * Canonical tool name identifiers.
 *
 * Used by tool getName() and error messages.
 * Centralising here prevents silent drift.
 */
final class ToolName
{
    public const SEARCH_CONTENT = 'search_content';
    public const LOAD_CONTENT = 'load_content';
}
