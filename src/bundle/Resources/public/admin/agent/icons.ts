/**
 * Icon references for the Ibexa Design System icon sprite (all-icons.svg).
 * Ibexa's own helper knows the sprite location; the widget does not hard-code it.
 */
interface IbexaIconHelper {
    getIconPath: (name: string) => string;
}

export function iconPath(name: string): string {
    const helper = (window as unknown as { ibexa?: { helpers?: { icon?: IbexaIconHelper } } }).ibexa?.helpers?.icon;

    return helper !== undefined ? helper.getIconPath(name) : `#${name}`;
}

/** Icon for each tool state in the timeline. */
export const TOOL_ICONS = {
    running: 'arrows-synchronize',
    ok: 'check-circle',
    failed: 'alert-warning',
} as const;
