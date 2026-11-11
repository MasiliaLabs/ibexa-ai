import type { Decision, Turn } from '../state';
import { ApprovalCard } from './ApprovalCard';
import { ToolTimeline } from './ToolTimeline';

interface TurnViewProps {
    turn: Turn;
    active: boolean;
    onDecide: (approvalId: string, decision: Decision) => void;
}

/**
 * One exchange: the user's message, then what the assistant did and said.
 * Text is rendered as text (never as HTML) and keeps its line breaks.
 */
export function TurnView({ turn, active, onDecide }: TurnViewProps) {
    const streaming = turn.status === 'streaming' && active;
    const waitingForText = streaming && turn.text === '' && turn.tools.length === 0;

    return (
        <article className="masilia-agent__turn">
            {turn.user !== null ? (
                <p className="masilia-agent__message masilia-agent__message--user">
                    <span className="visually-hidden">You: </span>
                    {turn.user}
                </p>
            ) : null}

            {turn.tools.length > 0 ? <ToolTimeline steps={turn.tools} /> : null}

            {turn.text !== '' ? (
                <p className="masilia-agent__message masilia-agent__message--assistant">
                    <span className="visually-hidden">Assistant: </span>
                    {turn.text}
                </p>
            ) : null}

            {waitingForText ? (
                <p className="masilia-agent__typing" aria-label="The assistant is working">
                    <span />
                    <span />
                    <span />
                </p>
            ) : null}

            {turn.approvals.map((approval) => (
                <ApprovalCard key={approval.id} approval={approval} onDecide={onDecide} />
            ))}

            {turn.status === 'failed' ? (
                <p className="masilia-agent__error" role="alert">
                    {turn.error ?? 'The assistant could not answer.'}
                </p>
            ) : null}
        </article>
    );
}
