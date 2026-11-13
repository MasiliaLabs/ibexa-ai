<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

/**
 * What a package that sits on the AI OS teaches the assistant: how a given kind of site, or a given team, builds things.
 *
 * The OS knows what an administrator can do in Ibexa and nothing about how any site is built. A skill adds that,
 * as a fragment of the system prompt (the conventions, the order of the steps, the content types that matter).
 * The capabilities a package brings are registered like the OS's own (see Capability); a skill only talks about
 * them. Several skills can be installed: they are added to the prompt in the order they are registered.
 */
interface Skill
{
    /** A short stable name ("craft"), used as the heading of the fragment. */
    public function name(): string;

    /**
     * The fragment of the prompt, in Markdown, written to the model: what this package is for, the capabilities it
     * adds and the rules that go with them. Without a title: the name is used as one.
     */
    public function instructions(): string;
}
