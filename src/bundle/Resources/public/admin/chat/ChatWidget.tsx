import { AssistantModalPrimitive, AssistantRuntimeProvider, AuiConfig, Suggestions } from '@assistant-ui/react';
import React, { useState } from 'react';

import { AssetsBridge } from './bridge';
import { Thread } from './components/Thread';
import { Icon } from '../shared/ids';
import type { ChatRuntime } from './runtime/types';

// Starting points shown while the conversation is empty: what the assistant is good at here.
const CONFIG = AuiConfig({
    suggestions: Suggestions([
        { title: 'Site structure', label: 'sites and pages', prompt: 'Show me the structure of the site.' },
        { title: 'Missing translations', label: 'pages without French', prompt: 'Which pages have no French translation?' },
        { title: 'Hidden content', label: 'not visible to visitors', prompt: 'Which content is hidden from visitors?' },
        { title: 'What can you do?', label: 'in this admin', prompt: 'What can you help me with here?' },
    ]),
});

interface ChatWidgetProps {
    chat: ChatRuntime;
}

/**
 * The floating assistant: a launcher in the corner of every admin page and a panel above it.
 */
export function ChatWidget({ chat }: ChatWidgetProps) {
    // It is only loaded when the user clicked the launcher: it opens at once.
    const [open, setOpen] = useState(true);

    return (
        <AssetsBridge>
            <AssistantRuntimeProvider runtime={chat.runtime} config={CONFIG}>
                <AssistantModalPrimitive.Root open={open} onOpenChange={setOpen}>
                    <AssistantModalPrimitive.Anchor className="masilia-chat__anchor">
                        <AssistantModalPrimitive.Trigger className="masilia-chat__launcher" title="Content assistant" aria-label={open ? 'Close the assistant' : 'Open the assistant'}>
                            <Icon name="ai" size="medium" />
                        </AssistantModalPrimitive.Trigger>
                    </AssistantModalPrimitive.Anchor>
                    <AssistantModalPrimitive.Content
                        className="masilia-chat__panel"
                        sideOffset={12}
                        align="end"
                        side="top"
                        aria-label="Content assistant"
                        onOpenAutoFocus={(event) => {
                            // The message box takes the focus, not the first button of the header.
                            event.preventDefault();
                            document.getElementById('masilia-chat-input')?.focus();
                        }}
                    >
                        <Thread chat={chat} onClose={() => setOpen(false)} />
                    </AssistantModalPrimitive.Content>
                </AssistantModalPrimitive.Root>
            </AssistantRuntimeProvider>
        </AssetsBridge>
    );
}
