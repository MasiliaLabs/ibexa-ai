import { useSyncExternalStore } from 'react';

/**
 * Whether the assistant is told which page the editor has open. On by default; the editor can turn it off from the
 * chip above the message box, and the choice is kept in the browser.
 */

const KEY = 'masilia.ai.share-page';
const listeners = new Set<() => void>();

export function sharesPage(): boolean {
    try {
        return window.localStorage.getItem(KEY) !== '0';
    } catch {
        return true;
    }
}

export function setSharesPage(share: boolean): void {
    try {
        window.localStorage.setItem(KEY, share ? '1' : '0');
    } catch {
        // Not kept: it still applies until the page is left.
    }
    listeners.forEach((listener) => listener());
}

export function useSharesPage(): boolean {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        sharesPage,
        () => true,
    );
}
