import React from 'react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Button } from '../../shared/ids';

interface ComposerProps {
    busy: boolean;
    /** True while a change waits for the user's decision: the run cannot take a new message then. */
    blocked: boolean;
    onSend: (text: string) => void;
    onStop: () => void;
}

/**
 * Message input. Enter sends, Shift+Enter starts a new line. While the assistant answers, the send
 * button becomes a stop button: stopping only stops the display, the run finishes on the server.
 * While a change awaits a decision, the input is disabled until the user approves or refuses it.
 */
export function Composer({ busy, blocked, onSend, onStop }: ComposerProps) {
    const [text, setText] = useState('');

    const submit = () => {
        if (text.trim() === '' || busy || blocked) {
            return;
        }
        onSend(text);
        setText('');
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <div className="masilia-agent__composer">
            <label className="visually-hidden" htmlFor="masilia-agent-input">
                Message the assistant
            </label>
            <textarea
                id="masilia-agent-input"
                className="masilia-agent__textarea"
                rows={2}
                value={text}
                disabled={blocked}
                placeholder={blocked ? 'Approve or refuse the change above first' : 'Ask, or describe a change…'}
                onChange={(event) => setText(event.target.value)}
                onKeyDown={onKeyDown}
            />
            {busy ? (
                <Button type="secondary" icon="discard" ariaLabel="Stop" title="Stop" onClick={onStop} />
            ) : (
                <Button type="primary" icon="send" ariaLabel="Send" title="Send" onClick={submit} disabled={blocked} />
            )}
        </div>
    );
}
