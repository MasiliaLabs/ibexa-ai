import React from 'react';
import { useEffect, useRef } from 'react';
import type { Decision, Turn } from '../state';
import { TurnView } from './TurnView';

interface ThreadProps {
    turns: Turn[];
    loading: boolean;
    busy: boolean;
    onDecide: (approvalId: string, decision: Decision) => void;
}

/** Distance from the bottom, in pixels, under which the thread keeps following new content. */
const FOLLOW_THRESHOLD = 48;

/**
 * The conversation. A live region announces new text to screen readers; it is polite, so it does not interrupt.
 * The thread follows new content to the bottom, unless the user scrolled up to read something.
 */
export function Thread({ turns, loading, busy, onDecide }: ThreadProps) {
    const logRef = useRef<HTMLDivElement>(null);
    const following = useRef(true);

    useEffect(() => {
        const log = logRef.current;
        if (log !== null && following.current) {
            log.scrollTop = log.scrollHeight;
        }
    }, [turns]);

    const onScroll = () => {
        const log = logRef.current;
        if (log !== null) {
            following.current = log.scrollHeight - log.scrollTop - log.clientHeight < FOLLOW_THRESHOLD;
        }
    };

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
        <div
            ref={logRef}
            className="masilia-agent__thread"
            role="log"
            aria-live="polite"
            aria-relevant="additions text"
            onScroll={onScroll}
        >
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
