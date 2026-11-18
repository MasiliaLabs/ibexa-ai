import React from 'react';

/**
 * The assistant as a small robot: a head with a screen face (eyes that blink), an antenna that pulses, ears, a body and an arm that
 * waves when the pointer is over it. Drawn in the palette of the admin (primary for the shell, dark for the screen). The movement is
 * in chat.scss and stops with the "reduce motion" preference.
 */
export function AssistantMark({ size = 64 }: { size?: number }): React.ReactElement {
    return (
        <svg className="masilia-bot" width={size} height={size * 1.1} viewBox="0 0 64 70" aria-hidden="true" focusable="false">
            <ellipse className="masilia-bot__shadow" cx="32" cy="66" rx="14" ry="2.6" />
            <g className="masilia-bot__body-group">
                <line className="masilia-bot__antenna" x1="32" y1="9" x2="32" y2="3.5" />
                <circle className="masilia-bot__bulb" cx="32" cy="3" r="2.8" />
                <rect className="masilia-bot__ear" x="5" y="22" width="6" height="12" rx="3" />
                <rect className="masilia-bot__ear" x="53" y="22" width="6" height="12" rx="3" />
                <rect className="masilia-bot__head" x="9" y="9" width="46" height="34" rx="12" />
                <rect className="masilia-bot__screen" x="14" y="15" width="36" height="22" rx="8" />
                <g className="masilia-bot__eyes">
                    <ellipse className="masilia-bot__eye" cx="24.5" cy="26" rx="3.6" ry="4.4" />
                    <ellipse className="masilia-bot__eye" cx="39.5" cy="26" rx="3.6" ry="4.4" />
                </g>
                <path className="masilia-bot__smile" d="M28 33.2q4 2.8 8 0" />
                <rect className="masilia-bot__torso" x="20" y="45" width="24" height="15" rx="7" />
                <circle className="masilia-bot__heart" cx="32" cy="52.5" r="2.4" />
                <rect className="masilia-bot__arm masilia-bot__arm--left" x="12" y="47" width="6" height="12" rx="3" />
                <g className="masilia-bot__wave">
                    <rect className="masilia-bot__arm" x="46" y="47" width="6" height="12" rx="3" />
                </g>
            </g>
        </svg>
    );
}
