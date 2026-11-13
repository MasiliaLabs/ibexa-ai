/**
 * What the assistant does to look around (explore, browse, a capability that only reads) is folded into one line; a
 * change is not: it asks for a decision (or has been decided) and stays in the open.
 */
export function groupSteps(part: { type: string; toolName?: string; approval?: unknown }): readonly `group-${string}`[] {
    return part.type === 'tool-call' && part.toolName !== 'propose_plan' && part.approval === undefined ? ['group-steps'] : [];
}
