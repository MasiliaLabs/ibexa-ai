/**
 * How the history is shown: conversations grouped by day, with a short label for when each was last used.
 * Pure: the current time is given.
 */

export interface ThreadSummary {
    id: string;
    title: string;
    /** ISO 8601 */
    updatedAt: string;
}

export interface ThreadGroup {
    label: 'Today' | 'Yesterday' | 'Earlier';
    threads: ThreadSummary[];
}

const DAY = 24 * 60 * 60 * 1000;

function startOfDay(date: Date): number {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
}

/** Whole days between the day of `date` and the day of `now`, by the calendar. */
function daysAgo(date: Date, now: Date): number {
    return Math.round((startOfDay(now) - startOfDay(date)) / DAY);
}

export function whenLabel(iso: string, now: Date): string {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const minutes = Math.floor((now.getTime() - date.getTime()) / 60000);
    if (minutes < 1) {
        return 'Just now';
    }
    const days = daysAgo(date, now);
    if (days === 0) {
        return minutes < 60 ? `${minutes} min ago` : `${Math.floor(minutes / 60)} h ago`;
    }
    if (days === 1) {
        return 'Yesterday';
    }

    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', ...(date.getFullYear() !== now.getFullYear() ? { year: 'numeric' } : {}) }).format(date);
}

export function groupThreads(threads: readonly ThreadSummary[], now: Date): ThreadGroup[] {
    const groups: ThreadGroup[] = [
        { label: 'Today', threads: [] },
        { label: 'Yesterday', threads: [] },
        { label: 'Earlier', threads: [] },
    ];

    for (const thread of threads) {
        const days = daysAgo(new Date(thread.updatedAt), now);
        groups[days <= 0 ? 0 : days === 1 ? 1 : 2].threads.push(thread);
    }

    return groups.filter((group) => group.threads.length > 0);
}
