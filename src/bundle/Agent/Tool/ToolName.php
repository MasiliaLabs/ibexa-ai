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
    public const CREATE_CONTENT = 'create_content';
    public const UPDATE_CONTENT = 'update_content';
    public const SEARCH_CONTENT = 'search_content';
    public const LOAD_CONTENT = 'load_content';
    public const CREATE_FOLDER = 'create_folder';
}
