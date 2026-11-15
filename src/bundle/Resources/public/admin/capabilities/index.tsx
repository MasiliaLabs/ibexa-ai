import React, { StrictMode } from 'react';
import type { ReactNode } from 'react';

import { CapabilitiesApp } from './CapabilitiesApp';
import type { PageData } from './types';

const ROOT_ID = 'masilia-capabilities-root';

interface ReactDomClient {
    createRoot: (container: Element) => { render: (node: ReactNode) => void };
}

/** The page hands over what it shows in `data-page` (JSON built by Twig); React comes from the admin (`ReactDOMClient`). */
function mount(): void {
    const client = (window as unknown as { ReactDOMClient?: ReactDomClient }).ReactDOMClient;
    const container = document.getElementById(ROOT_ID);
    if (client === undefined || container === null) {
        return;
    }
    const data = JSON.parse(container.dataset.page ?? 'null') as PageData | null;
    if (data === null) {
        return;
    }

    client.createRoot(container).render(
        <StrictMode>
            <CapabilitiesApp data={data} />
        </StrictMode>,
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
