import { AssistantRuntimeProvider } from '@assistant-ui/react';
import React, { useEffect, useMemo, useRef, useState } from 'react';

import { AssetsBridge } from './bridge';
import { Thread } from './components/Thread';
import { runContext } from './runtime/pageContext';
import type { ChatRuntime } from './runtime/types';
import { createSuggestionsConfig } from './suggestions';
import { AssistantMark } from './AssistantMark';

interface ChatWidgetProps {
    chat: ChatRuntime;
}

const WIDE_KEY = 'masilia.ai.wide';

/**
 * Files dragged over the panel stay in it: the page (and the browser, which would open the file) is not told.
 * The message box decides what it takes.
 */
function keepFiles(event: React.DragEvent): void {
    event.stopPropagation();
    if (event.dataTransfer.types.includes('Files')) {
        event.preventDefault();
    }
}

function readWide(): boolean {
    try {
        return window.localStorage.getItem(WIDE_KEY) === '1';
    } catch {
        return false;
    }
}

/**
 * The assistant: a panel docked to the right of the admin, opened from a launcher in the corner. It can be widened
 * for long answers; the choice is kept.
 */
export function ChatWidget({ chat }: ChatWidgetProps) {
    // It is only loaded when the user clicked the launcher: it opens at once.
    const [open, setOpen] = useState(true);
    const [wide, setWide] = useState(readWide);
    const launcher = useRef<HTMLButtonElement>(null);
    const wasOpen = useRef(true);
    const config = useMemo(() => createSuggestionsConfig(runContext(window.location.pathname).length > 0), []);

    useEffect(() => {
        if (open) {
            document.getElementById('masilia-chat-input')?.focus();
        } else if (wasOpen.current) {
            launcher.current?.focus();
        }
        wasOpen.current = open;
    }, [open]);

    // The page makes room for the panel, as a docked side panel does, instead of being covered by it.
    useEffect(() => {
        const root = document.documentElement;
        root.classList.toggle('masilia-chat-open', open);
        root.classList.toggle('masilia-chat-wide', open && wide);

        return () => root.classList.remove('masilia-chat-open', 'masilia-chat-wide');
    }, [open, wide]);

    const toggleWide = () => {
        const next = !wide;
        setWide(next);
        try {
            window.localStorage.setItem(WIDE_KEY, next ? '1' : '0');
        } catch {
            // Not kept: it still applies until the page is left.
        }
    };

    return (
        <AssetsBridge>
            <AssistantRuntimeProvider runtime={chat.runtime} config={config}>
                {open ? (
                    <aside
                        className={`masilia-chat__dock${wide ? ' masilia-chat__dock--wide' : ''}`}
                        aria-label="Content assistant"
                        onKeyDown={(event) => {
                            if (event.key === 'Escape' && !event.defaultPrevented) {
                                setOpen(false);
                            }
                        }}
                        // The admin turns a file dropped anywhere on its page into content (its multi-file upload).
                        // A file dropped on the assistant is for the assistant, and must not reach the page.
                        onDragEnter={keepFiles}
                        onDragOver={keepFiles}
                        onDragLeave={(event) => event.stopPropagation()}
                        onDrop={keepFiles}
                    >
                        <Thread chat={chat} wide={wide} onToggleWide={toggleWide} onClose={() => setOpen(false)} />
                    </aside>
                ) : (
                    <div className="masilia-chat__anchor">
                        <button ref={launcher} type="button" className="masilia-chat__launcher" title="Content assistant" aria-label="Open the assistant" onClick={() => setOpen(true)}>
                            <AssistantMark />
                        </button>
                    </div>
                )}
            </AssistantRuntimeProvider>
        </AssetsBridge>
    );
}
