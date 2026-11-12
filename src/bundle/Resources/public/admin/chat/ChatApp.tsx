import React from 'react';

import { ChatWidget } from './ChatWidget';
import { useAguiRuntime } from './runtime/useAguiRuntime';

export default function ChatApp() {
    return <ChatWidget chat={useAguiRuntime()} />;
}
