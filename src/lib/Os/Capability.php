<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

/**
 * One thing an administrator can do in Ibexa, as the assistant can do it: described for the model, permissioned,
 * previewable, and run with the rights of the user who asked.
 *
 * The model never sees one tool per capability: it finds them (find_capabilities), reads one (describe_capability)
 * and runs it (run_capability). A capability is therefore described well, by its summary and its examples.
 */
interface Capability
{
    /** Dotted, lower case: "content.update". The part before the dot is the domain. */
    public function name(): string;

    /** One line for the list of capabilities. */
    public function summary(): string;

    /** What it does, when to use it, what to know before. */
    public function description(): string;

    /**
     * JSON Schema of the arguments (type "object").
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    public function risk(): Risk;

    /**
     * The Ibexa policies the user needs, as [module, function] pairs, on top of the permission of the risk.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function requires(): array;

    /**
     * Examples of valid calls, for the model.
     *
     * @return list<array{description: string, arguments: array<string, mixed>}>
     */
    public function examples(): array;

    /**
     * A problem the schema cannot see, found without writing anything: the content type does not exist, the parent is
     * in another site, the image is gone. Null when the call can go ahead. A call with a problem is answered to the
     * model, not shown to the user for approval.
     *
     * @param array<string, mixed> $arguments Already validated against the schema
     */
    public function validate(array $arguments): ?string;

    /**
     * What the call will do, for the person who approves it. Nothing is written.
     *
     * @param array<string, mixed> $arguments Already validated against the schema
     */
    public function preview(array $arguments): Preview;

    /**
     * @param array<string, mixed> $arguments Already validated against the schema
     */
    public function execute(array $arguments): CapabilityResult;
}
