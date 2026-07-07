type Frame = {
    file: string;
    source: string;
    code: Record<string, string> | null;
};

export type ExceptionMarkdownInput = {
    class: string;
    file: string;
    line: number;
    message: string | null;
    code: string | null;
    handled: boolean;
    trace: string | null;
    occurredAt: string | null;
    executionSource: string | null;
    sourceRequest: {
        method: string;
        url: string;
        route_name: string | null;
        status_code: number;
        ip: string | null;
    } | null;
    authenticatedUser: {
        external_id: string;
        name: string | null;
        username: string | null;
    } | null;
    queries: Array<{
        sql: string;
        duration_us: number;
        connection: string | null;
    }>;
    logs: Array<{ level: string; message: string | null; occurred_at: string }>;
    firstSeenAt: string;
    lastSeenAt: string;
    occurrences24h: number;
    occurrences7d: number;
    impactedUsers: number;
};

function parseFrames(trace: string | null): Frame[] {
    if (!trace) {
        return [];
    }

    try {
        const frames = JSON.parse(trace);

        return Array.isArray(frames) ? frames : [];
    } catch {
        return [];
    }
}

function contextDetails(input: ExceptionMarkdownInput): string {
    if (input.sourceRequest) {
        return 'This exception was thrown during a HTTP Request.';
    }

    if (input.executionSource?.includes('Command')) {
        return 'This exception was thrown during an artisan command.';
    }

    if (input.executionSource) {
        return 'This exception was thrown during a queued job.';
    }

    return 'No surrounding execution context was captured.';
}

export function buildExceptionMarkdown(input: ExceptionMarkdownInput): string {
    const sections: string[] = [];

    sections.push(
        [
            '## Exception Summary',
            '',
            `- Class: \`${input.class}\``,
            `- Message: \`${input.message ?? '-'}\``,
            `- Code: \`${input.code ?? '0'}\``,
            `- File: \`${input.file}:${input.line}\``,
            `- Timestamp: \`${input.occurredAt ?? '-'}\``,
            `- Details: ${contextDetails(input)}`,
        ].join('\n'),
    );

    if (input.sourceRequest) {
        const r = input.sourceRequest;
        sections.push(
            [
                '## HTTP Request',
                '',
                `- Method: \`${r.method}\``,
                `- URL: \`${r.url}\``,
                `- Route: \`${r.route_name ?? '-'}\``,
                `- Status code: \`${r.status_code}\``,
                `- IP address: \`${r.ip ?? '-'}\``,
            ].join('\n'),
        );
    }

    if (input.authenticatedUser) {
        const u = input.authenticatedUser;
        sections.push(
            [
                '### Authenticated User',
                '',
                `- ID: \`${u.external_id}\``,
                `- Username: \`${u.username ?? u.name ?? '-'}\``,
            ].join('\n'),
        );
    }

    if (input.queries.length > 0) {
        const rows = input.queries.map(
            (q) =>
                `- \`${q.sql}\` (${(q.duration_us / 1000).toFixed(1)} ms)${q.connection ? ` [${q.connection}]` : ''}`,
        );
        sections.push(
            [
                '## Database Queries (before exception)',
                '',
                ...rows,
                '',
                'Only queries executed before the exception are shown. Bindings and sensitive values may be redacted.',
            ].join('\n'),
        );
    }

    if (input.logs.length > 0) {
        sections.push(
            [
                '## Logs',
                '',
                ...input.logs.map(
                    (l) => `- \`[${l.level}]\` ${l.message ?? '-'}`,
                ),
            ].join('\n'),
        );
    } else {
        sections.push(['## Logs', '', '- Not captured'].join('\n'));
    }

    const frames = parseFrames(input.trace);

    if (frames.length > 0) {
        const rows = frames.map(
            (frame, i) =>
                `- [${i}] ${frame.source || '-'}\n    at ${frame.file}`,
        );
        sections.push(
            [
                '## Stack Trace (most recent call first)',
                '',
                ...rows,
            ].join('\n'),
        );

        const withCode = frames.filter(
            (frame) => frame.code && Object.keys(frame.code).length > 0,
        );

        if (withCode.length > 0) {
            const blocks = withCode.map((frame) => {
                const lines = Object.entries(frame.code!)
                    .map(([line, text]) => [Number(line), text] as const)
                    .sort((a, b) => a[0] - b[0])
                    .map(([line, text]) => `${line}: ${text}`)
                    .join('\n');

                return `- File: ${frame.file}\n\n\`\`\`php\n${lines}\n\`\`\``;
            });

            sections.push(['## Code Context', '', ...blocks].join('\n\n'));
        }
    }

    sections.push(
        [
            '## Occurrence Statistics',
            '',
            `- First seen: \`${input.firstSeenAt}\``,
            `- Last seen: \`${input.lastSeenAt}\``,
            `- Occurrences (last 24 hours): \`${input.occurrences24h}\``,
            `- Occurrences (last 7 days): \`${input.occurrences7d}\``,
            `- Users affected: \`${input.impactedUsers}\``,
        ].join('\n'),
    );

    return sections.join('\n\n');
}
