<?php

namespace App\Services\Ingest;

use App\Events\IssueCreated;
use App\Models\AppCommand;
use App\Models\CacheEvent;
use App\Models\Issue;
use App\Models\IssueEvent;
use App\Models\JobAttempt;
use App\Models\LogEntry;
use App\Models\MinitorException;
use App\Models\MinitorRequest;
use App\Models\MonitoredUser;
use App\Models\OutgoingRequest;
use App\Models\Project;
use App\Models\Query;
use App\Models\QueuedJob;
use App\Models\ScheduledTask;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Persists decoded laravel/nightwatch records (already validated/authenticated
 * by IngestController) into project-scoped storage. Each record carries a
 * `t` discriminator field identifying its shape - see minitor/agent's
 * README for where these field names come from.
 */
class RecordIngestor
{
    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function ingest(Project $project, array $records): void
    {
        foreach ($records as $record) {
            match ($record['t'] ?? null) {
                'request' => $this->ingestRequest($project, $record),
                'query' => $this->ingestQuery($project, $record),
                'exception' => $this->ingestException($project, $record),
                'queued-job' => $this->ingestQueuedJob($project, $record),
                'job-attempt' => $this->ingestJobAttempt($project, $record),
                'command' => $this->ingestCommand($project, $record),
                'scheduled-task' => $this->ingestScheduledTask($project, $record),
                'cache-event' => $this->ingestCacheEvent($project, $record),
                'outgoing-request' => $this->ingestOutgoingRequest($project, $record),
                'log' => $this->ingestLog($project, $record),
                'user' => $this->ingestUser($project, $record),
                default => null,
            };
        }

        $project->forceFill(['last_seen_at' => now()])->save();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestRequest(Project $project, array $record): void
    {
        MinitorRequest::create([
            'project_id' => $project->id,
            'method' => (string) ($record['method'] ?? ''),
            'url' => (string) ($record['url'] ?? ''),
            'route_name' => $record['route_name'] ?? null,
            'route_path' => $record['route_path'] ?? null,
            'route_action' => $record['route_action'] ?? null,
            'ip' => $record['ip'] ?? null,
            'status_code' => (int) ($record['status_code'] ?? 0),
            'duration_us' => (int) ($record['duration'] ?? 0),
            'request_size' => (int) ($record['request_size'] ?? 0),
            'response_size' => (int) ($record['response_size'] ?? 0),
            'deploy' => $record['deploy'] ?? null,
            'trace_id' => $record['trace_id'] ?? null,
            'user_external_id' => $this->userExternalId($record),
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestQuery(Project $project, array $record): void
    {
        Query::create([
            'project_id' => $project->id,
            'sql' => (string) ($record['sql'] ?? ''),
            // Nightwatch already collapses variable-length `in (...)` clauses
            // and bulk insert value lists into this hash before it ever
            // reaches us - reuse it directly rather than recomputing our own,
            // same as we do for exception fingerprints.
            'query_group' => isset($record['_group']) ? (string) $record['_group'] : null,
            'file' => $record['file'] ?? null,
            'line' => (int) ($record['line'] ?? 0),
            'duration_us' => (int) ($record['duration'] ?? 0),
            'connection' => $record['connection'] ?? null,
            'connection_type' => $record['connection_type'] ?? null,
            'execution_source' => $record['execution_source'] ?? null,
            'trace_id' => $record['trace_id'] ?? null,
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestException(Project $project, array $record): void
    {
        $fingerprint = (string) ($record['_group'] ?? sha1(json_encode($record)));
        $occurredAt = $this->timestamp($record);
        $message = $record['message'] ?? null;

        $issue = Issue::query()
            ->where('project_id', $project->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($issue === null) {
            $issue = Issue::create([
                'project_id' => $project->id,
                'fingerprint' => $fingerprint,
                'class' => (string) ($record['class'] ?? 'Unknown'),
                'file' => (string) ($record['file'] ?? ''),
                'line' => (int) ($record['line'] ?? 0),
                'last_message' => $message,
                'occurrences_count' => 1,
                'first_seen_at' => $occurredAt,
                'last_seen_at' => $occurredAt,
            ]);

            event(new IssueCreated($issue));
        } else {
            $issue->increment('occurrences_count');
            $issue->forceFill([
                'last_message' => $message,
                'last_seen_at' => $occurredAt->greaterThan($issue->last_seen_at) ? $occurredAt : $issue->last_seen_at,
            ])->save();

            // A resolved issue recurring means it wasn't actually fixed -
            // reopen it, same as Sentry/Bugsnag. An ignored issue is a
            // deliberate, ongoing suppression, so it stays ignored.
            if ($issue->status === 'resolved') {
                $issue->forceFill(['status' => 'open'])->save();

                IssueEvent::create([
                    'issue_id' => $issue->id,
                    'user_id' => null,
                    'type' => 'status_changed',
                    'meta' => ['from' => 'resolved', 'to' => 'open', 'reason' => 'recurred'],
                ]);

                event(new IssueCreated($issue, isReopen: true));
            }
        }

        MinitorException::create([
            'project_id' => $project->id,
            'issue_id' => $issue->id,
            'message' => $message,
            'code' => isset($record['code']) ? (string) $record['code'] : null,
            'handled' => (bool) ($record['handled'] ?? false),
            'trace' => $record['trace'] ?? null,
            'trace_id' => $record['trace_id'] ?? null,
            'php_version' => $record['php_version'] ?? null,
            'laravel_version' => $record['laravel_version'] ?? null,
            'user_external_id' => $this->userExternalId($record),
            'server' => $record['server'] ?? null,
            'execution_source' => $record['execution_source'] ?? null,
            'execution_preview' => $record['execution_preview'] ?? null,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestQueuedJob(Project $project, array $record): void
    {
        QueuedJob::create([
            'project_id' => $project->id,
            'job_id' => isset($record['job_id']) ? (string) $record['job_id'] : null,
            'name' => (string) ($record['name'] ?? ''),
            'connection' => $record['connection'] ?? null,
            'queue' => $record['queue'] ?? null,
            'user_external_id' => $this->userExternalId($record),
            'duration_us' => (int) ($record['duration'] ?? 0),
            'trace_id' => $record['trace_id'] ?? null,
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestJobAttempt(Project $project, array $record): void
    {
        JobAttempt::create([
            'project_id' => $project->id,
            'job_id' => isset($record['job_id']) ? (string) $record['job_id'] : null,
            'attempt_id' => isset($record['attempt_id']) ? (string) $record['attempt_id'] : null,
            'attempt' => (int) ($record['attempt'] ?? 1),
            'name' => (string) ($record['name'] ?? ''),
            'connection' => $record['connection'] ?? null,
            'queue' => $record['queue'] ?? null,
            'status' => (string) ($record['status'] ?? 'processed'),
            'user_external_id' => $this->userExternalId($record),
            'duration_us' => (int) ($record['duration'] ?? 0),
            'trace_id' => $record['trace_id'] ?? null,
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestCommand(Project $project, array $record): void
    {
        AppCommand::create([
            'project_id' => $project->id,
            'class' => $record['class'] ?? null,
            'name' => (string) ($record['name'] ?? ''),
            'command' => $record['command'] ?? null,
            'exit_code' => (int) ($record['exit_code'] ?? 0),
            'duration_us' => (int) ($record['duration'] ?? 0),
            'trace_id' => $record['trace_id'] ?? null,
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestScheduledTask(Project $project, array $record): void
    {
        ScheduledTask::create([
            'project_id' => $project->id,
            'name' => (string) ($record['name'] ?? ''),
            'cron' => $record['cron'] ?? null,
            'timezone' => $record['timezone'] ?? null,
            'repeat_seconds' => (int) ($record['repeat_seconds'] ?? 0),
            'without_overlapping' => (bool) ($record['without_overlapping'] ?? false),
            'on_one_server' => (bool) ($record['on_one_server'] ?? false),
            'run_in_background' => (bool) ($record['run_in_background'] ?? false),
            'even_in_maintenance_mode' => (bool) ($record['even_in_maintenance_mode'] ?? false),
            'status' => (string) ($record['status'] ?? 'processed'),
            'duration_us' => (int) ($record['duration'] ?? 0),
            'trace_id' => $record['trace_id'] ?? null,
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestCacheEvent(Project $project, array $record): void
    {
        CacheEvent::create([
            'project_id' => $project->id,
            'store' => $record['store'] ?? null,
            'key' => $record['key'] ?? null,
            'type' => (string) ($record['type'] ?? ''),
            'duration_us' => (int) ($record['duration'] ?? 0),
            'ttl' => (int) ($record['ttl'] ?? 0),
            'trace_id' => $record['trace_id'] ?? null,
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestOutgoingRequest(Project $project, array $record): void
    {
        OutgoingRequest::create([
            'project_id' => $project->id,
            'host' => $record['host'] ?? null,
            'method' => $record['method'] ?? null,
            'url' => $record['url'] ?? null,
            'duration_us' => (int) ($record['duration'] ?? 0),
            'request_size' => (int) ($record['request_size'] ?? 0),
            'response_size' => (int) ($record['response_size'] ?? 0),
            'status_code' => (int) ($record['status_code'] ?? 0),
            'execution_source' => $record['execution_source'] ?? null,
            'execution_preview' => $record['execution_preview'] ?? null,
            'trace_id' => $record['trace_id'] ?? null,
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestLog(Project $project, array $record): void
    {
        LogEntry::create([
            'project_id' => $project->id,
            'level' => (string) ($record['level'] ?? 'info'),
            'message' => $record['message'] ?? null,
            'context' => $record['context'] ?? null,
            'extra' => $record['extra'] ?? null,
            'trace_id' => $record['trace_id'] ?? null,
            'occurred_at' => $this->timestamp($record),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function ingestUser(Project $project, array $record): void
    {
        if (! isset($record['id']) || $record['id'] === '') {
            return;
        }

        MonitoredUser::updateOrCreate(
            ['project_id' => $project->id, 'external_id' => (string) $record['id']],
            [
                'name' => $record['name'] ?? null,
                'username' => $record['username'] ?? null,
                'last_seen_at' => $this->timestamp($record),
            ],
        );
    }

    /**
     * Nightwatch's `user` field is whatever the monitored app's configured
     * user resolver returns as an id (via Nightwatch::user(...)) - absent or
     * null means the request/exception was unauthenticated ("guest").
     *
     * @param  array<string, mixed>  $record
     */
    private function userExternalId(array $record): ?string
    {
        $id = $record['user'] ?? null;

        return $id === null || $id === '' ? null : (string) $id;
    }

    /**
     * Nightwatch timestamps are unix microtime floats (seconds.microseconds).
     *
     * @param  array<string, mixed>  $record
     */
    private function timestamp(array $record): CarbonInterface
    {
        $microtime = $record['timestamp'] ?? null;

        return is_numeric($microtime) ? Carbon::createFromTimestamp((float) $microtime) : now();
    }
}
