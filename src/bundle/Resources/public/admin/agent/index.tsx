import React, { StrictMode } from 'react';
import type { ReactNode } from 'react';

import { AgentWidget } from './AgentWidget';

const ROOT_ID = 'masilia-ai-agent-root';

interface ReactDomClient {
    createRoot: (container: Element) => { render: (node: ReactNode) => void };
}

/**
 * Ibexa loads the React DOM client API as the global `ReactDOMClient` (react-dom-load).
 * The `react-dom` default export has no `createRoot` under React 19, so it is not used here.
 */
function reactDomClient(): ReactDomClient {
    const client = (window as unknown as { ReactDOMClient?: ReactDomClient }).ReactDOMClient;
    if (client === undefined) {
        throw new Error('The Ibexa React DOM client is not loaded.');
    }

    return client;
}

/**
 * Mounts the assistant once, in its own element appended to the admin body.
 * Nothing of the Ibexa page is modified: the widget only adds its root.
 */
function mount(): void {
    if (document.getElementById(ROOT_ID) !== null) {
        return;
    }

    const container = document.createElement('div');
    container.id = ROOT_ID;
    document.body.appendChild(container);

    reactDomClient().createRoot(container).render(
        <StrictMode>
            <AgentWidget />
        </StrictMode>,
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
