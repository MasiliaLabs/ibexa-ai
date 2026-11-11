import { useState } from 'react';
import { Button } from '../ids';

import type { Approval, Decision } from '../state';

interface ApprovalCardProps {
    approval: Approval;
    onDecide: (approvalId: string, decision: Decision) => void;
}

/**
 * A change proposed by the assistant, waiting for the user. Nothing runs until the user approves it.
 * Refusing can carry a reason, which the assistant reads and takes into account.
 */
export function ApprovalCard({ approval, onDecide }: ApprovalCardProps) {
    const [refusing, setRefusing] = useState(false);
    const [reason, setReason] = useState('');
    const decided = approval.decision !== null;

    return (
        <section className="masilia-agent__approval" aria-labelledby={`approval-${approval.id}`}>
            <h3 id={`approval-${approval.id}`} className="masilia-agent__approval-title">
                Change to review
            </h3>
            <p className="masilia-agent__approval-reason">{approval.reason || 'The assistant proposes a change.'}</p>

            <details className="masilia-agent__step-details">
                <summary>Details</summary>
                <pre className="masilia-agent__inputs">{JSON.stringify(approval.inputs, null, 2)}</pre>
            </details>

            {decided ? (
                <p className="masilia-agent__approval-state" role="status">
                    {approval.decision?.kind === 'approve' ? 'Approved. It will run.' : 'Refused. Nothing was changed.'}
                </p>
            ) : refusing ? (
                <div className="masilia-agent__refuse">
                    <label className="masilia-agent__label" htmlFor={`reason-${approval.id}`}>
                        Why? (optional)
                    </label>
                    <textarea
                        id={`reason-${approval.id}`}
                        className="masilia-agent__textarea"
                        rows={2}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                    />
                    <div className="masilia-agent__actions">
                        <Button type="secondary" onClick={() => setRefusing(false)}>
                            Back
                        </Button>
                        <Button type="primary" onClick={() => onDecide(approval.id, { kind: 'reject', reason: reason.trim() })}>
                            Refuse
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="masilia-agent__actions">
                    <Button type="secondary" onClick={() => setRefusing(true)}>
                        Refuse
                    </Button>
                    <Button type="primary" onClick={() => onDecide(approval.id, { kind: 'approve' })}>
                        Approve
                    </Button>
                </div>
            )}
        </section>
    );
}
