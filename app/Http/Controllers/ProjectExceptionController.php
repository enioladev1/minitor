<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\MinitorRequest;
use App\Models\Project;
use App\Services\Exceptions\ExceptionContext;
use App\Services\Stats\ExceptionStats;
use App\Services\Stats\RequestStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProjectExceptionController extends Controller
{
    /**
     * The exceptions directory: every distinct issue (grouped by
     * class+code+file+line, same fingerprint the Issues workflow uses) that
     * occurred within the selected window, with occurrence/impacted-user
     * counts and a handled/unhandled classification from its latest
     * occurrence - the project-wide "Exceptions" landing page.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);
        $userFilter = $request->string('user')->toString() ?: null;

        $scoped = RequestStats::applyUserFilter(
            $project->exceptions()->where('occurred_at', '>=', $range->since),
            $userFilter
        );

        $aggregates = (clone $scoped)
            ->selectRaw('issue_id')
            ->selectRaw('max(occurred_at) as last_seen_at')
            ->selectRaw('count(*) as count')
            ->selectRaw('count(distinct user_external_id) as users')
            ->groupBy('issue_id')
            ->get()
            ->keyBy('issue_id');

        $latest = (clone $scoped)
            ->orderBy('issue_id')
            ->orderByDesc('occurred_at')
            ->selectRaw('distinct on (issue_id) issue_id, handled, message')
            ->get()
            ->keyBy('issue_id');

        $issues = Issue::whereIn('id', $aggregates->keys())->get()->keyBy('id');

        $allRows = $aggregates->map(function ($aggregate, $issueId) use ($issues, $latest) {
            $issue = $issues->get($issueId);
            $latestRow = $latest->get($issueId);

            return [
                'id' => $issueId,
                'class' => $issue?->class ?? 'Unknown',
                'message' => $latestRow->message ?? $issue?->last_message,
                'handled' => (bool) ($latestRow->handled ?? false),
                'last_seen_at' => Carbon::parse($aggregate->last_seen_at)->toIso8601String(),
                'count' => (int) $aggregate->count,
                'users' => (int) $aggregate->users,
            ];
        })->values();

        $unhandledCount = $allRows->where('handled', false)->count();
        $handledCount = $allRows->where('handled', true)->count();

        $rows = $allRows;

        if ($search = $request->string('q')->toString()) {
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['class']), strtolower($search))
                || str_contains(strtolower((string) $row['message']), strtolower($search)))->values();
        }

        if ($status = $request->string('status')->toString()) {
            $rows = match ($status) {
                'handled' => $rows->where('handled', true)->values(),
                'unhandled' => $rows->where('handled', false)->values(),
                default => $rows,
            };
        }

        $sortMap = ['last_seen' => 'last_seen_at', 'count' => 'count', 'users' => 'users'];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? 'last_seen_at';
        $descending = $request->string('direction')->toString() !== 'asc';
        $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $descending)->values();

        return Inertia::render('projects/exceptions/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'status', 'user', 'range']),
            'occurrenceSeries' => ExceptionStats::occurrenceSeries($scoped, $range),
            'exceptionStats' => ExceptionStats::summary($scoped),
            'exceptions' => $rows->all(),
            'unhandledCount' => $unhandledCount,
            'handledCount' => $handledCount,
            'users' => $this->userOptions($project),
        ]);
    }

    /**
     * Drill into a single exception issue: lifetime identity stats,
     * occurrence chart scoped to the selected range, the latest stack
     * trace, and every individual occurrence - linked back to its
     * originating request where the trace correlates to one we ingested.
     */
    public function show(Request $request, Project $project, Issue $issue): Response
    {
        abort_unless($issue->project_id === $project->id, 404);

        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);
        $userFilter = $request->string('user')->toString() ?: null;

        $scoped = RequestStats::applyUserFilter(
            $issue->occurrences()->where('occurred_at', '>=', $range->since),
            $userFilter
        );

        $latestOccurrence = $issue->occurrences()->orderByDesc('occurred_at')->first();

        $impactedUsers = (int) $issue->occurrences()->distinct('user_external_id')->whereNotNull('user_external_id')->count('user_external_id');

        $occurrences7d = $issue->occurrences()->where('occurred_at', '>=', now()->subDays(7))->count();
        $occurrences24h = $issue->occurrences()->where('occurred_at', '>=', now()->subHours(24))->count();

        $list = clone $scoped;

        $occurrences = $list->orderByDesc('occurred_at')->paginate(50)->withQueryString();

        $traceIds = collect($occurrences->items())->pluck('trace_id')->filter()->unique()->values();
        $sourceRequests = MinitorRequest::where('project_id', $project->id)
            ->whereIn('trace_id', $traceIds)
            ->get(['id', 'trace_id'])
            ->keyBy('trace_id');

        $occurrences->through(fn ($occurrence) => [
            'id' => $occurrence->id,
            'message' => $occurrence->message,
            'handled' => $occurrence->handled,
            'occurred_at' => $occurrence->occurred_at,
            'user_external_id' => $occurrence->user_external_id,
            'execution_source' => $occurrence->execution_source,
            'execution_preview' => $occurrence->execution_preview,
            'source_request_id' => $sourceRequests->get($occurrence->trace_id)?->id,
        ]);

        return Inertia::render('projects/exceptions/Show', [
            'project' => $project,
            'issue' => $issue,
            'latestOccurrence' => $latestOccurrence,
            'latestOccurrenceContext' => $latestOccurrence
                ? ExceptionContext::build($project, $latestOccurrence)
                : null,
            'range' => $rangeKey,
            'filters' => $request->only(['user', 'range']),
            'occurrenceSeries' => ExceptionStats::occurrenceSeries($scoped, $range),
            'exceptionStats' => ExceptionStats::summary($scoped),
            'impactedUsers' => $impactedUsers,
            'occurrences7d' => $occurrences7d,
            'occurrences24h' => $occurrences24h,
            'phpVersions' => $issue->occurrences()->whereNotNull('php_version')->distinct()->pluck('php_version'),
            'laravelVersions' => $issue->occurrences()->whereNotNull('laravel_version')->distinct()->pluck('laravel_version'),
            'servers' => $issue->occurrences()->whereNotNull('server')->distinct()->pluck('server'),
            'occurrences' => $occurrences,
            'users' => $this->userOptions($project),
        ]);
    }

    /**
     * @return list<array{external_id: string, label: string}>
     */
    private function userOptions(Project $project): array
    {
        return $project->monitoredUsers()
            ->orderByDesc('last_seen_at')
            ->limit(100)
            ->get()
            ->map(fn ($user) => [
                'external_id' => $user->external_id,
                'label' => $user->name ?: ($user->username ?: $user->external_id),
            ])
            ->all();
    }
}
