import { useEffect, useState } from 'react';
import { AssetsProvider, Button } from './ids';

import { useAgent } from './useAgent';
import type { AgentController } from './useAgent';
import { Thread } from './components/Thread';
import { Composer } from './components/Composer';
import { iconPath } from './icons';

/**
 * The floating assistant: a launcher button in the corner of every admin page, and a panel above it.
 */
export function AgentWidget() {
    const [open, setOpen] = useState(false);
    const agent = useAgent();

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };
        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open]);

    return (
        <AssetsProvider value={{ getIconPath: iconPath }}>
            <div className="masilia-agent">
                {open ? (
                    <Panel agent={agent} onClose={() => setOpen(false)} />
                ) : null}
                <div className="masilia-agent__launcher">
                    <Button
                        type="primary"
                        icon="ai"
                        size="medium"
                        ariaLabel={open ? 'Close the assistant' : 'Open the assistant'}
                        title="Content assistant"
                        onClick={() => setOpen((value) => !value)}
                        extraAria={{ 'aria-expanded': open, 'aria-controls': 'masilia-agent-panel' }}
                    />
                </div>
            </div>
        </AssetsProvider>
    );
}

interface PanelProps {
    agent: AgentController;
    onClose: () => void;
}

function Panel({ agent, onClose }: PanelProps) {
    const [confirmingClear, setConfirmingClear] = useState(false);
    const hasHistory = agent.state.turns.length > 0;

    const clear = async () => {
        setConfirmingClear(false);
        await agent.clear();
    };

    return (
        <section id="masilia-agent-panel" className="masilia-agent__panel" role="dialog" aria-label="Content assistant">
            <header className="masilia-agent__header">
                <h2 className="masilia-agent__title">Content assistant</h2>
                <div className="masilia-agent__header-actions">
                    {hasHistory && !confirmingClear ? (
                        <Button
                            type="tertiary"
                            icon="trash"
                            ariaLabel="New conversation"
                            title="New conversation"
                            onClick={() => setConfirmingClear(true)}
                        />
                    ) : null}
                    <Button type="tertiary" icon="discard" ariaLabel="Close" title="Close" onClick={onClose} />
                </div>
            </header>

            {confirmingClear ? (
                <div className="masilia-agent__confirm" role="alertdialog" aria-label="Start a new conversation">
                    <p>Start a new conversation? The current one will be cleared.</p>
                    <div className="masilia-agent__confirm-actions">
                        <Button type="secondary" onClick={() => setConfirmingClear(false)}>
                            Keep it
                        </Button>
                        <Button type="primary" onClick={() => void clear()}>
                            Start over
                        </Button>
                    </div>
                </div>
            ) : null}

            <Thread turns={agent.state.turns} loading={agent.loading} onDecide={agent.decide} busy={agent.state.busy} />

            <Composer busy={agent.state.busy} onSend={agent.send} onStop={agent.stop} />
        </section>
    );
}
