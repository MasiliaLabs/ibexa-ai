import React, { useState } from 'react';

import { ChatWidget } from './ChatWidget';
import { useAgentRuntime } from './runtime/useAgentRuntime';
import { useAguiRuntime } from './runtime/useAguiRuntime';

const STORAGE_KEY = 'masilia.ai.chat.mode';

type Mode = 'sse' | 'agui';

function initialMode(): Mode {
    try {
        const fromUrl = new URLSearchParams(window.location.search).get('ai-chat');
        if (fromUrl === 'sse' || fromUrl === 'agui') {
            window.localStorage.setItem(STORAGE_KEY, fromUrl);

            return fromUrl;
        }

        return window.localStorage.getItem(STORAGE_KEY) === 'agui' ? 'agui' : 'sse';
    } catch {
        return 'sse';
    }
}

function SseChat({ onSwitchMode }: { onSwitchMode: () => void }) {
    return <ChatWidget chat={useAgentRuntime()} onSwitchMode={onSwitchMode} />;
}

function AguiChat({ onSwitchMode }: { onSwitchMode: () => void }) {
    return <ChatWidget chat={useAguiRuntime()} onSwitchMode={onSwitchMode} />;
}

/**
 * Spike: the same view over two runtimes, to compare them. The choice is kept in the browser.
 */
export default function ChatApp() {
    const [mode, setMode] = useState<Mode>(initialMode);
    const switchMode = () => {
        const next: Mode = mode === 'sse' ? 'agui' : 'sse';
        try {
            window.localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // The choice only lasts for this page.
        }
        setMode(next);
    };

    return mode === 'agui' ? <AguiChat key="agui" onSwitchMode={switchMode} /> : <SseChat key="sse" onSwitchMode={switchMode} />;
}

