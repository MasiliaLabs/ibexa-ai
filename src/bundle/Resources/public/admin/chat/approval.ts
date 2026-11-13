/**
 * What the user reviews before approving a change. The server describes the change as lines of "Label: value"
 * (see ProposePlanTool): it is the one source of truth for what will happen, including the values it resolved
 * (the language code of the site, for instance). They are read here into a heading and labelled rows, instead of
 * being shown as a block of text.
 */

export interface ApprovalRow {
    label: string;
    value: string;
}

export type ApprovalRisk = 'write' | 'destructive' | 'access';

export interface ApprovalSummary {
    heading: string | null;
    rows: ApprovalRow[];
    notes: string[];
    /** How far the change reaches, when the server says: destructive and access changes get a warning. */
    risk: ApprovalRisk | null;
}

const ACTIONS: Record<string, string> = {
    create_content: 'Create a page',
    create_items: 'Create items',
    update_content: 'Update content',
    create_folder: 'Create a folder',
    create_site_structure: 'Create a site',
    trash_content: 'Move to the trash',
    restore_content: 'Restore from the trash',
    add_image: 'Add an image to the media library',
};

const LABELS: Record<string, string> = {
    Title: 'Title',
    Type: 'Content type',
    Siteaccess: 'Site',
    Parent: 'Parent location',
    'Content ID': 'Content',
    Language: 'Language',
    Fields: 'Fields',
    Blocks: 'Blocks',
    Image: 'Image',
};

const CLOSING = 'The change runs only if you approve it.';

function unquote(value: string): string {
    return value.length >= 2 && value.startsWith('"') && value.endsWith('"') ? value.slice(1, -1) : value;
}

export function parseApproval(prompt: string | undefined): ApprovalSummary {
    const summary: ApprovalSummary = { heading: null, rows: [], notes: [], risk: null };

    let named = false;
    for (const raw of (prompt ?? '').split('\n')) {
        const line = raw.trim();
        if (line === '' || line === CLOSING) {
            continue;
        }

        const match = /^([A-Z][A-Za-z0-9 ]*): (.+)$/.exec(line);
        if (match === null) {
            summary.notes.push(line);
            continue;
        }

        const [, key, value] = match;
        if (key === 'Intent') {
            summary.heading = ACTIONS[value] ?? value;
        } else if (key === 'Action') {
            summary.heading = value;
            named = true;
        } else if (key === 'Note') {
            summary.notes.push(value);
        } else if (key === 'Risk') {
            summary.risk = value === 'write' || value === 'destructive' || value === 'access' ? value : null;
        } else if (key in LABELS) {
            summary.rows.push({ label: LABELS[key], value: unquote(value) });
        } else if (named) {
            // A capability of the AI OS (it begins with its "Action") names its own rows.
            summary.rows.push({ label: key, value: unquote(value) });
        } else {
            summary.notes.push(line);
        }
    }

    return summary;
}
