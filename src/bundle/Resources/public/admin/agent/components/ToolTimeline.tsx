import { TOOL_ICONS, iconPath } from '../icons';
import type { ToolStep } from '../state';

const LABELS: Record<string, string> = {
    explore_site: 'Explored the site',
    browse_location: 'Looked up content',
    propose_plan: 'Proposed a change',
};

interface ToolTimelineProps {
    steps: ToolStep[];
}

/**
 * What the assistant did, step by step. Details stay folded so the conversation stays readable.
 */
export function ToolTimeline({ steps }: ToolTimelineProps) {
    return (
        <ol className="masilia-agent__timeline" aria-label="Actions">
            {steps.map((step) => (
                <li key={step.id} className={`masilia-agent__step masilia-agent__step--${step.status}`}>
                    <span className="masilia-agent__step-icon" aria-hidden="true">
                        <img src={iconPath(TOOL_ICONS[step.status])} alt="" width={16} height={16} />
                    </span>
                    <details className="masilia-agent__step-details">
                        <summary>
                            {LABELS[step.tool] ?? step.tool}
                            <span className="visually-hidden">
                                {step.status === 'running' ? ', running' : step.status === 'ok' ? ', done' : ', failed'}
                            </span>
                        </summary>
                        {step.message !== '' ? <p className="masilia-agent__step-message">{step.message}</p> : null}
                        {Object.keys(step.inputs).length > 0 ? (
                            <pre className="masilia-agent__inputs">{JSON.stringify(step.inputs, null, 2)}</pre>
                        ) : null}
                    </details>
                </li>
            ))}
        </ol>
    );
}
