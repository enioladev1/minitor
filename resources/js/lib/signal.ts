/**
 * MINITOR's signature signal system: the same three tones — good, warn, bad —
 * mean the same thing everywhere data is shown (a request's status, a
 * query's duration, a job's outcome), so health reads at a glance without
 * reading a single number. Keep every new table/detail view using these
 * helpers rather than inventing ad-hoc color logic.
 */
export type SignalTone = 'good' | 'warn' | 'bad';

export function statusTone(statusCode: number): SignalTone {
    if (statusCode >= 500) {
        return 'bad';
    }

    if (statusCode >= 400) {
        return 'warn';
    }

    return 'good';
}

export function durationTone(
    microseconds: number,
    thresholds: { warn: number; bad: number } = {
        warn: 200_000,
        bad: 1_000_000,
    },
): SignalTone {
    if (microseconds >= thresholds.bad) {
        return 'bad';
    }

    if (microseconds >= thresholds.warn) {
        return 'warn';
    }

    return 'good';
}

export const toneClasses: Record<SignalTone, string> = {
    good: 'text-signal-good',
    warn: 'text-signal-warn',
    bad: 'text-signal-bad',
};

export const toneDotClasses: Record<SignalTone, string> = {
    good: 'bg-signal-good',
    warn: 'bg-signal-warn',
    bad: 'bg-signal-bad',
};

export function formatDuration(microseconds: number): string {
    if (microseconds < 1_000) {
        return `${microseconds}µs`;
    }

    if (microseconds < 1_000_000) {
        return `${(microseconds / 1_000).toFixed(1)}ms`;
    }

    return `${(microseconds / 1_000_000).toFixed(2)}s`;
}

export function formatBucketLabel(iso: string, range: string): string {
    const date = new Date(iso);

    if (range === '1h' || range === '24h') {
        return date.toLocaleTimeString(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        });
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

export function formatBucketDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export function formatCompactNumber(value: number): string {
    return new Intl.NumberFormat(undefined, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function formatDateTime(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
    });
}

export function formatCountdown(iso: string): string {
    const seconds = Math.max(
        0,
        Math.round((new Date(iso).getTime() - Date.now()) / 1000),
    );

    const units: [string, number][] = [
        ['d', Math.floor(seconds / 86_400)],
        ['h', Math.floor((seconds % 86_400) / 3_600)],
        ['m', Math.floor((seconds % 3_600) / 60)],
        ['s', seconds % 60],
    ];

    const parts: string[] = [];
    let started = false;

    for (const [label, value] of units) {
        if (!started && value === 0 && label !== 's') {
            continue;
        }

        started = true;
        parts.push(`${value}${label}`);
    }

    return parts.length > 0 ? parts.join(' ') : '0s';
}

export function formatRelative(value: string): string {
    const date = new Date(value);
    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const divisions: [Intl.RelativeTimeFormatUnit, number][] = [
        ['second', 60],
        ['minute', 60],
        ['hour', 24],
        ['day', 30],
        ['month', 12],
        ['year', Infinity],
    ];

    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    let duration = seconds;

    for (const [unit, amount] of divisions) {
        if (Math.abs(duration) < amount) {
            return rtf.format(Math.round(duration), unit);
        }

        duration /= amount;
    }

    return rtf.format(Math.round(duration), 'year');
}
