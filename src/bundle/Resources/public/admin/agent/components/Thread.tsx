import type { Decision, Turn } from '../state';
import { TurnView } from './TurnView';

interface ThreadProps {
    turns: Turn[];
    loading: boolean;
    busy: boolean;
    onDecide: (approvalId: string, decision: Decision) => void;
}

/**
 * The conversation. A live region announces new text to screen readers; it is polite, so it does not interrupt.
 */
export function Thread({ turns, loading, busy, onDecide }: ThreadProps) {
    if (loading && turns.length === 0) {
        return (
            <div className="masilia-agent__thread masilia-agent__thread--empty" aria-busy="true">
                <p className="masilia-agent__hint">Loading the conversation…</p>
            </div>
        );
    }

    if (turns.length === 0) {
        return (
            <div className="masilia-agent__thread masilia-agent__thread--empty">
                <p className="masilia-agent__hint">
                    Ask about the site or describe a change. Any change to content is shown to you first, and runs only if you approve it.
                </p>
            </div>
        );
    }

    return (
        <div className="masilia-agent__thread" role="log" aria-live="polite" aria-relevant="additions text">
            {turns.map((turn, index) => (
                <TurnView
                    key={turn.id}
                    turn={turn}
                    active={index === turns.length - 1 && busy}
                    onDecide={onDecide}
                />
            ))}
        </div>
    );
}
