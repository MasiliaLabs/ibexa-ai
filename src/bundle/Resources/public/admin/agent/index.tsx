import { StrictMode } from 'react';
import type { createRoot } from 'react-dom/client';
// Ibexa provides React DOM as an external; its root API is on the default export (as in ai-settings.js),
// whose typings do not expose it, hence the cast.
import ReactDOM from 'react-dom';

import { AgentWidget } from './AgentWidget';

const ROOT_ID = 'masilia-ai-agent-root';

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

    const reactDom = ReactDOM as unknown as { createRoot: typeof createRoot };

    reactDom.createRoot(container).render(
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
