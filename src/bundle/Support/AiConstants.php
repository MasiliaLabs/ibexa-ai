<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Support;

/** Values of a stock Ibexa repository that the tools fall back to. */
final class AiConstants
{
    public const DEFAULT_LANGUAGE_CODE = 'eng-GB';

    public const MEDIA_ROOT_LOCATION_ID = 43;

    public const ROOT_LOCATION_ID = 2;

    /** Read by masilia/ibexa-ai-craft only, and it names a configuration that no longer exists: it goes with the rework of that package. */
    public const CONFIG_NAMESPACE = 'masilia_ai_assistant';
}
