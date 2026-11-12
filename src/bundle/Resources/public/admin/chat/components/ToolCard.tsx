import type { ToolCallMessagePartProps } from '@assistant-ui/react';
import React, { useState } from 'react';

import { Button, Icon } from '../../shared/ids';

const LABELS: Record<string, string> = {
    explore_site: 'Explored the site',
    browse_location: 'Looked up content',
    propose_plan: 'Proposed a change',
};

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
    const running = !waiting && status.type === 'running';
    const failed = !refused && (isError === true || (status.type === 'incomplete' && status.reason === 'error'));
    const state = waiting ? 'waiting' : refused ? 'refused' : running ? 'running' : failed ? 'failed' : 'ok';
    const inputs = args !== undefined && Object.keys(args as object).length > 0 ? JSON.stringify(args, null, 2) : '';

    return (
        <div className={`masilia-chat__tool masilia-chat__tool--${state}`}>
            <details className="masilia-chat__tool-details">
                <summary>
                    <span className="masilia-chat__tool-icon" aria-hidden="true">
                        <Icon name={ICONS[state]} size="small" />
                    </span>
                    {LABELS[toolName] ?? toolName}
                    <span className="visually-hidden">{`, ${STATE_LABELS[state]}`}</span>
                </summary>
                {typeof result === 'string' && result !== '' ? <p className="masilia-chat__tool-message">{result}</p> : null}
                {inputs !== '' ? <pre className="masilia-chat__tool-inputs">{inputs}</pre> : null}
            </details>
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

    return (
        <section className="masilia-chat__approval" aria-label="Change to review">
            <h3 className="masilia-chat__approval-title">Change to review</h3>
            <p className="masilia-chat__approval-reason">{approval.prompt ?? 'The assistant proposes a change.'}</p>
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
