import React, { StrictMode, Suspense, lazy, useState } from 'react';
import type { ReactNode } from 'react';

import { Icon } from '../shared/ids';
import { AssetsBridge } from './bridge';
import { loadChatApp } from './loader';

const ROOT_ID = 'masilia-ai-chat-root';

// The assistant (assistant-ui, the AG-UI client, Markdown) is large and the admin layout loads on every page:
// it is fetched when the launcher is first used.
const ChatApp = lazy(loadChatApp);

interface ReactDomClient {
    createRoot: (container: Element) => { render: (node: ReactNode) => void };
}

function Launcher({ loading, onClick }: { loading: boolean; onClick: () => void }) {
    return (
        <div className="masilia-chat__anchor">
            <button type="button" className="masilia-chat__launcher" title="Content assistant" aria-label="Open the assistant" aria-busy={loading} onClick={onClick}>
                <Icon name="ai" size="medium" />
            </button>
        </div>
    );
}

function Shell() {
    const [wanted, setWanted] = useState(false);

    return wanted ? (
        <Suspense fallback={<Launcher loading onClick={() => undefined} />}>
            <ChatApp />
        </Suspense>
    ) : (
        <Launcher loading={false} onClick={() => setWanted(true)} />
    );
}

function mount(): void {
    const client = (window as unknown as { ReactDOMClient?: ReactDomClient }).ReactDOMClient;
    // The button is only there for who the role lets use the assistant (see ChatAccessConfig).
    const allowed = (window as unknown as { ibexa?: { adminUiConfig?: { masiliaAi?: { canUse?: boolean } } } }).ibexa?.adminUiConfig?.masiliaAi?.canUse === true;
    if (!allowed || client === undefined || document.getElementById(ROOT_ID) !== null) {
        return;
    }

    const container = document.createElement('div');
    container.id = ROOT_ID;
    document.body.appendChild(container);

    client.createRoot(container).render(
        <StrictMode>
            <AssetsBridge>
                <Shell />
            </AssetsBridge>
        </StrictMode>,
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
