import { MarkdownTextPrimitive } from '@assistant-ui/react-markdown';
import React from 'react';
import remarkGfm from 'remark-gfm';

/** Text parts of the assistant, as Markdown (community renderer: raw HTML is not rendered, links are sanitised). */
export function Markdown() {
    return <MarkdownTextPrimitive remarkPlugins={[remarkGfm]} className="masilia-chat__markdown" />;
}
