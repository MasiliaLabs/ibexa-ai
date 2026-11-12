import React, { StrictMode } from 'react';
import type { ReactNode } from 'react';

import { SettingsApp } from './components/SettingsApp';

const ROOT_ID = 'ai-settings-react-root';

interface ReactDomClient {
    createRoot: (container: Element) => { render: (node: ReactNode) => void };
}

/**
 * Ibexa loads the React DOM client API as the global `ReactDOMClient` (react-dom-load); the `react-dom`
 * default export has no `createRoot` under React 19.
 */
function mount(): void {
    const client = (window as unknown as { ReactDOMClient?: ReactDomClient }).ReactDOMClient;
    const container = document.getElementById(ROOT_ID);
    if (client === undefined || container === null) {
        return;
    }

    client.createRoot(container).render(
        <StrictMode>
            <SettingsApp />
        </StrictMode>,
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
