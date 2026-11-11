import React from 'react';
import type { ReactNode } from 'react';
import { parseMarkdown } from '../markdown';
import type { Block, Inline } from '../markdown';

interface MarkdownProps {
    text: string;
}

/**
 * Renders an assistant message. The text is read into a tree first, and only known elements
 * are created from it: nothing the model writes is inserted as HTML.
 */
export function Markdown({ text }: MarkdownProps) {
    return <div className="masilia-agent__md">{parseMarkdown(text).map(renderBlock)}</div>;
}

function renderBlock(block: Block, key: number): ReactNode {
    switch (block.type) {
        case 'heading':
            // The panel title is the h2 and a change to review the h3: message headings start below them.
            return block.level <= 2 ? (
                <h4 key={key} className="masilia-agent__md-heading">
                    {renderInline(block.children)}
                </h4>
            ) : (
                <h5 key={key} className="masilia-agent__md-heading masilia-agent__md-heading--minor">
                    {renderInline(block.children)}
                </h5>
            );
        case 'list': {
            const items = block.items.map((item, index) => <li key={index}>{renderInline(item)}</li>);

            return block.ordered ? <ol key={key}>{items}</ol> : <ul key={key}>{items}</ul>;
        }
        case 'code':
            return (
                <pre key={key} className="masilia-agent__inputs">
                    <code>{block.text}</code>
                </pre>
            );
        case 'rule':
            return <hr key={key} />;
        default:
            return <p key={key}>{renderInline(block.children)}</p>;
    }
}

function renderInline(nodes: Inline[]): ReactNode[] {
    return nodes.map((node, key) => {
        switch (node.type) {
            case 'strong':
                return <strong key={key}>{renderInline(node.children)}</strong>;
            case 'em':
                return <em key={key}>{renderInline(node.children)}</em>;
            case 'code':
                return <code key={key}>{node.text}</code>;
            case 'link':
                return (
                    <a key={key} href={node.href} target="_blank" rel="noopener noreferrer">
                        {renderInline(node.children)}
                    </a>
                );
            default:
                return <React.Fragment key={key}>{node.text}</React.Fragment>;
        }
    });
}
