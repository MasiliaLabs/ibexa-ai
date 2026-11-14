<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Security;

/**
 * What a user must be allowed to do to use an endpoint of the AI bundle. One place says it: an endpoint names its access
 * (#[RequiresAccess]), and the policy is here.
 */
enum Access
{
    /**
     * The chat, its conversations, its attachments, and the AI settings: administrators. (What the assistant can then do is
     * decided capability by capability, by the masilia_ai policies of the user: use, write, destructive, structure, access.)
     */
    case Administrator;

    /** The suggestions and the image generation that the editors have on a content they edit. */
    case ContentEditor;

    /** Reading what the assistant is and what it did: whoever may use it. */
    case Assistant;

    /**
     * @return array{0: string, 1: string} The module and the function of the Ibexa policy
     */
    public function policy(): array
    {
        return match ($this) {
            self::Administrator => ['setup', 'administrate'],
            self::ContentEditor => ['content', 'edit'],
            self::Assistant => ['masilia_ai', 'use'],
        };
    }
}
