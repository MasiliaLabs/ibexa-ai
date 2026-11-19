import React from 'react';

/**
 * The launcher of the assistant: a speech bubble whose three dots keep "typing", with a spark badge, and the label "Ask the assistant"
 * typed above it once when the page opens, then faded so that only the bubble stays. The label is decoration (the button carries the
 * name for assistive technology). The robot (AssistantMark) is kept as another look: use it in place of this component.
 */
export function AssistantLauncher({ size = 84 }: { size?: number }): React.ReactElement {
    return (
        <span className="masilia-launcher">
            <span className="masilia-launcher__label" aria-hidden="true">
                <span className="masilia-launcher__type">Ask the assistant</span>
            </span>
            <svg className="masilia-launcher__bubble" width={size} height={size * 0.95} viewBox="0 0 96 92" aria-hidden="true" focusable="false">
                <path
                    className="masilia-launcher__shape"
                    d="M18 8h52a14 14 0 0 1 14 14v28a14 14 0 0 1-14 14H44L26 80a2 2 0 0 1-3.4-1.5V64H18A14 14 0 0 1 4 50V22A14 14 0 0 1 18 8z"
                />
                <circle className="masilia-launcher__dot" cx="30" cy="36" r="5" />
                <circle className="masilia-launcher__dot" cx="44" cy="36" r="5" />
                <circle className="masilia-launcher__dot" cx="58" cy="36" r="5" />
                <g className="masilia-launcher__badge">
                    <circle cx="78" cy="10" r="11" />
                    <path d="M78 3.5c.6 3.6 2 5 5.5 5.5-3.5.6-4.9 2-5.5 5.5-.6-3.5-2-4.9-5.5-5.5 3.5-.5 4.9-1.9 5.5-5.5z" />
                </g>
            </svg>
        </span>
    );
}
