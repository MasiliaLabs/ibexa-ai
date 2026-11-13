import type { ToolCallMessagePartProps } from '@assistant-ui/react';
import React, { useState } from 'react';

import { Button, Icon } from '../../shared/ids';
import { parseApproval } from '../approval';
import { toolOutcome } from '../toolResult';

const LABELS: Record<string, string> = {
    explore_site: 'Explored the site',
    browse_location: 'Looked up content',
    propose_plan: 'Proposed a change',
    find_capabilities: 'Looked for a capability',
    describe_capability: 'Read how a capability works',
    run_capability: 'Ran a capability',
};

/** The tools that act on a named capability say which. */
const NAMED = new Set(['describe_capability', 'run_capability']);

type State = 'waiting' | 'refused' | 'running' | 'failed' | 'ok';

const ICONS: Record<State, string> = {
    waiting: 'info-circle',
    refused: 'discard',
    running: 'arrows-reload',
    failed: 'alert-error',
    ok: 'check-circle',
};

const STATE_LABELS: Record<State, string> = {
    waiting: 'waiting for your decision',
    refused: 'refused',
    running: 'running',
    failed: 'failed',
    ok: 'done',
};

/**
 * One tool call of the assistant: what it did, with its inputs folded. When the call is a change waiting for
 * the user, the approval controls come with it.
 */
export function ToolCard(props: ToolCallMessagePartProps) {
    const { toolName, args, result, isError, status, approval } = props;
    const waiting = approval !== undefined && approval.approved === undefined && approval.resolution === undefined;
    const refused = approval?.approved === false;
    const outcome = result === undefined ? null : toolOutcome(result);
    const running = !waiting && status.type === 'running';
    const failed = !refused && (isError === true || outcome?.ok === false || (status.type === 'incomplete' && status.reason === 'error'));
    const state = waiting ? 'waiting' : refused ? 'refused' : running ? 'running' : failed ? 'failed' : 'ok';
    const inputs = args !== undefined && Object.keys(args as object).length > 0 ? JSON.stringify(args, null, 2) : '';

    return (
        <div className={`masilia-chat__tool masilia-chat__tool--${state}`}>
            {/* While the change waits for a decision, its card says it all. */}
            {state === 'waiting' ? null : (
                <details className="masilia-chat__tool-details">
                    <summary>
                        <span className="masilia-chat__tool-icon" aria-hidden="true">
                            <Icon name={ICONS[state]} size="small" />
                        </span>
                        {LABELS[toolName] ?? toolName}
                    {NAMED.has(toolName) && typeof (args as { name?: unknown } | undefined)?.name === 'string' ? <span className="masilia-chat__tool-capability">{(args as { name: string }).name}</span> : null}
                        <span className="visually-hidden">{`, ${STATE_LABELS[state]}`}</span>
                    </summary>
                    {outcome !== null && outcome.message !== '' ? <p className="masilia-chat__tool-message">{outcome.message}</p> : null}
                    {inputs !== '' ? <pre className="masilia-chat__tool-inputs">{inputs}</pre> : null}
                </details>
            )}
            {state === 'ok' && outcome?.href ? (
                <a className="masilia-chat__tool-link" href={outcome.href} target="_blank" rel="noopener noreferrer">
                    <Icon name="open-new-window" size="small" />
                    Open the content
                </a>
            ) : null}
            {approval !== undefined ? <Approval {...props} approval={approval} /> : null}
        </div>
    );
}

function Approval({ approval, respondToApproval }: ToolCallMessagePartProps & { approval: NonNullable<ToolCallMessagePartProps['approval']> }) {
    const [refusing, setRefusing] = useState(false);
    const [reason, setReason] = useState('');
    const [failed, setFailed] = useState(false);

    const decided = approval.approved !== undefined;
    const cancelled = approval.resolution !== undefined;

    const answer = async (approved: boolean, why?: string) => {
        setFailed(false);
        try {
            await respondToApproval({ approved, ...(why !== undefined && why !== '' ? { reason: why } : {}) });
        } catch {
            setFailed(true);
        }
    };

    if (decided || cancelled) {
        return (
            <p className="masilia-chat__approval-state" role="status">
                {cancelled ? 'This change is no longer waiting.' : approval.approved ? 'Approved.' : 'Refused. Nothing was changed.'}
            </p>
        );
    }

    const summary = parseApproval(approval.prompt);

    return (
        <section className={`masilia-chat__approval${summary.risk === 'destructive' || summary.risk === 'access' ? ' masilia-chat__approval--serious' : ''}`} aria-label="Change to review">
            <header className="masilia-chat__approval-head">
                <span className="masilia-chat__approval-icon" aria-hidden="true">
                    <Icon name="clipboard-check" size="small" />
                </span>
                <div>
                    <p className="masilia-chat__approval-eyebrow">{summary.risk === 'destructive' ? 'Review this change: it cannot be undone' : summary.risk === 'access' ? 'Review this change: it changes who can do what' : 'Review this change'}</p>
                    <h3 className="masilia-chat__approval-title">{summary.heading ?? 'The assistant proposes a change'}</h3>
                </div>
            </header>
            {summary.rows.length > 0 ? (
                <dl className="masilia-chat__approval-rows">
                    {summary.rows.map((row) => (
                        <div key={row.label} className="masilia-chat__approval-row">
                            <dt>{row.label}</dt>
                            <dd>{row.value}</dd>
                        </div>
                    ))}
                </dl>
            ) : null}
            {summary.notes.map((note) => (
                <p key={note} className="masilia-chat__approval-reason">{note}</p>
            ))}
            {failed ? <p className="masilia-chat__error" role="alert">Your decision could not be sent. Try again.</p> : null}
            {refusing ? (
                <div className="masilia-chat__refuse">
                    <label className="masilia-chat__label" htmlFor={`refuse-${approval.id}`}>Why? (optional)</label>
                    <textarea
                        id={`refuse-${approval.id}`}
                        className="masilia-chat__textarea"
                        rows={2}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                    />
                    <div className="masilia-chat__actions">
                        <Button type="secondary" size="small" onClick={() => setRefusing(false)}>Back</Button>
                        <Button type="primary" size="small" onClick={() => void answer(false, reason.trim())}>Refuse</Button>
                    </div>
                </div>
            ) : (
                <div className="masilia-chat__actions">
                    <Button type="secondary" size="small" onClick={() => setRefusing(true)}>Refuse</Button>
                    <Button type="primary" size="small" onClick={() => void answer(true)}>Approve</Button>
                </div>
            )}
        </section>
    );
}
