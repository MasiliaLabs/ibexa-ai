import React from 'react';

/**
 * The mark of the assistant: a speech bubble (a conversation) holding a sparkle (the AI). One colour for the bubble (the text colour
 * of its parent) and the colour of the button for the sparkles, so that it follows the palette of the admin.
 */
export function AssistantMark({ size = 28 }: { size?: number }): React.ReactElement {
    return (
        <svg className="masilia-chat__mark" width={size} height={size} viewBox="0 0 32 32" aria-hidden="true" focusable="false">
            <path
                className="masilia-chat__mark-bubble"
                d="M8 4.5h16a4.5 4.5 0 0 1 4.5 4.5v10a4.5 4.5 0 0 1-4.5 4.5h-7.6l-5.2 4.3a.9.9 0 0 1-1.5-.7v-3.6H8A4.5 4.5 0 0 1 3.5 19V9A4.5 4.5 0 0 1 8 4.5z"
            />
            <g className="masilia-chat__mark-spark" transform="translate(-1.5 1.5)">
                <path d="M16 7.2c.6 3.8 2.1 5.3 5.9 5.9-3.8.6-5.3 2.1-5.9 5.9-.6-3.8-2.1-5.3-5.9-5.9 3.8-.6 5.3-2.1 5.9-5.9z" />
                <path className="masilia-chat__mark-spark-small" d="M23.2 6.4c.25 1.6.9 2.2 2.5 2.5-1.6.25-2.2.9-2.5 2.5-.25-1.6-.9-2.2-2.5-2.5 1.6-.3 2.2-.9 2.5-2.5z" />
            </g>
        </svg>
    );
}
