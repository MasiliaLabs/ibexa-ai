/**
 * Where the editor is in the admin, sent to the agent with each run so "here" and "this page" mean something.
 * Read from the address of the content view (/view/content/{contentId}/full/true/{locationId}); anywhere else the
 * agent is told nothing.
 */

export const PAGE_CONTEXT = 'masilia.page';

export interface RunContext {
    description: string;
    value: string;
}

const CONTENT_VIEW = /^\/view\/content\/(\d+)\/[^/]+\/[^/]+\/(\d+)\/?$/;

export function runContext(pathname: string): RunContext[] {
    const match = CONTENT_VIEW.exec(pathname);
    if (match === null) {
        return [];
    }

    return [{ description: PAGE_CONTEXT, value: JSON.stringify({ contentId: Number(match[1]), locationId: Number(match[2]) }) }];
}
