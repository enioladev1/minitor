<?php

namespace App\Services\Exceptions;

use App\Models\LogEntry;
use App\Models\MinitorException;
use App\Models\MinitorRequest;
use App\Models\MonitoredUser;
use App\Models\Project;
use App\Models\Query;

/**
 * Correlates an exception occurrence back to the rest of what was captured
 * on the same trace_id - the HTTP request it happened during, the queries
 * that ran before it, and any log lines - so the "Copy as Markdown" export
 * can include the same surrounding context Nightwatch's own export does.
 * We only surface fields this schema actually stores (no request headers or
 * payload capture exists here, unlike Nightwatch's hosted export).
 */
class ExceptionContext
{
    /**
     * @return array{
     *     source_request: array{method: string, url: string, route_name: ?string, status_code: int, ip: ?string}|null,
     *     authenticated_user: array{external_id: string, name: ?string, username: ?string}|null,
     *     queries: list<array{sql: string, duration_us: int, connection: ?string}>,
     *     logs: list<array{level: string, message: ?string, occurred_at: string}>,
     * }
     */
    public static function build(Project $project, MinitorException $exception): array
    {
        $sourceRequest = null;
        $queries = collect();
        $logs = collect();

        if ($exception->trace_id) {
            $sourceRequest = MinitorRequest::where('project_id', $project->id)
                ->where('trace_id', $exception->trace_id)
                ->first(['method', 'url', 'route_name', 'status_code', 'ip']);

            $queries = Query::where('project_id', $project->id)
                ->where('trace_id', $exception->trace_id)
                ->where('occurred_at', '<=', $exception->occurred_at)
                ->orderBy('occurred_at')
                ->get(['sql', 'duration_us', 'connection']);

            $logs = LogEntry::where('project_id', $project->id)
                ->where('trace_id', $exception->trace_id)
                ->orderBy('occurred_at')
                ->get(['level', 'message', 'occurred_at']);
        }

        $user = null;

        if ($exception->user_external_id) {
            $user = MonitoredUser::where('project_id', $project->id)
                ->where('external_id', $exception->user_external_id)
                ->first(['external_id', 'name', 'username']);
        }

        return [
            'source_request' => $sourceRequest ? [
                'method' => $sourceRequest->method,
                'url' => $sourceRequest->url,
                'route_name' => $sourceRequest->route_name,
                'status_code' => $sourceRequest->status_code,
                'ip' => $sourceRequest->ip,
            ] : null,
            'authenticated_user' => $user ? [
                'external_id' => $user->external_id,
                'name' => $user->name,
                'username' => $user->username,
            ] : null,
            'queries' => $queries->map(fn ($query) => [
                'sql' => $query->sql,
                'duration_us' => $query->duration_us,
                'connection' => $query->connection,
            ])->all(),
            'logs' => $logs->map(fn ($log) => [
                'level' => $log->level,
                'message' => $log->message,
                'occurred_at' => $log->occurred_at->toIso8601String(),
            ])->all(),
        ];
    }
}
