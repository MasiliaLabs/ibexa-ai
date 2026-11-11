/**
 * Icon files of the Ibexa Design System, served by the admin under this path.
 */
export function iconPath(name: string): string {
    return `/bundles/ibexaadminuiassets/vendors/ids-assets/dist/img/icons/${name}.svg`;
}

/** Icon for each tool state in the timeline. */
export const TOOL_ICONS = {
    running: 'arrows-synchronize',
    ok: 'check-circle',
    failed: 'alert-warning',
} as const;
