<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Stats\JobStats;
use App\Services\Stats\RequestStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectJobController extends Controller
{
    /**
     * The jobs directory: every distinct job class the project has seen,
     * with aggregate queued/processed/released/failed counts and timings —
     * the project-wide "Jobs" landing page, not a raw per-attempt feed.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);
        $userFilter = $request->string('user')->toString() ?: null;

        $attemptsScoped = RequestStats::applyUserFilter(
            $project->jobAttempts()->where('occurred_at', '>=', $range->since),
            $userFilter
        );
        $queuedScoped = RequestStats::applyUserFilter(
            $project->queuedJobs()->where('occurred_at', '>=', $range->since),
            $userFilter
        );

        $queuedCounts = (clone $queuedScoped)
            ->selectRaw('name, count(*) as queued')
            ->groupBy('name')
            ->pluck('queued', 'name');

        $rows = (clone $attemptsScoped)
            ->selectRaw('name')
            ->selectRaw("count(*) filter (where status = 'processed') as processed")
            ->selectRaw("count(*) filter (where status = 'released') as released")
            ->selectRaw("count(*) filter (where status = 'failed') as failed")
            ->selectRaw('count(*) as total')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupBy('name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'queued' => (int) ($queuedCounts[$row->name] ?? 0),
                'processed' => (int) $row->processed,
                'released' => (int) $row->released,
                'failed' => (int) $row->failed,
                'total' => (int) $row->total,
                'avg_us' => (int) round($row->avg_us ?? 0),
                'p95_us' => (int) round($row->p95_us ?? 0),
            ]);

        foreach ($queuedCounts as $name => $count) {
            if (! $rows->contains('name', $name)) {
                $rows->push([
                    'name' => $name,
                    'queued' => (int) $count,
                    'processed' => 0,
                    'released' => 0,
                    'failed' => 0,
                    'total' => 0,
                    'avg_us' => 0,
                    'p95_us' => 0,
                ]);
            }
        }

        if ($search = $request->string('q')->toString()) {
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['name']), strtolower($search)))->values();
        }

        $sortColumns = ['name', 'queued', 'processed', 'released', 'failed', 'total', 'avg_us', 'p95_us'];
        $sortMap = ['avg' => 'avg_us', 'p95' => 'p95_us'];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? $request->string('sort')->toString();
        $sortColumn = in_array($sortColumn, $sortColumns, true) ? $sortColumn : 'total';
        $descending = $request->string('direction')->toString() !== 'asc';

        $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $descending)->values();

        return Inertia::render('projects/jobs/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'user', 'range']),
            'attemptsSeries' => JobStats::attemptSeries($attemptsScoped, $range),
            'durationSeries' => JobStats::durationSeries($attemptsScoped, $range),
            'jobStats' => JobStats::summary($attemptsScoped),
            'jobs' => $rows->all(),
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

    /**
     * Drill into a single job class: its own charts, filterable by attempt
     * status / slow attempts, and the individual attempt rows.
     */
    public function job(Request $request, Project $project): Response
    {
        $name = $request->string('name')->toString();

        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);
        $userFilter = $request->string('user')->toString() ?: null;

        $scoped = RequestStats::applyUserFilter(
            $project->jobAttempts()
                ->where('occurred_at', '>=', $range->since)
                ->where('name', $name),
            $userFilter
        );

        $list = clone $scoped;

        if ($status = $request->string('status')->toString()) {
            match ($status) {
                'processed' => $list->where('status', 'processed'),
                'released' => $list->where('status', 'released'),
                'failed' => $list->where('status', 'failed'),
                default => null,
            };
        }

        if ($durationFilter = $request->string('duration')->toString()) {
            $summary = JobStats::summary($scoped);
            match ($durationFilter) {
                'avg' => $list->where('duration_us', '>=', $summary['avg_us']),
                'p95' => $list->where('duration_us', '>=', $summary['p95_us']),
                default => null,
            };
        }

        return Inertia::render('projects/jobs/Job', [
            'project' => $project,
            'name' => $name,
            'range' => $rangeKey,
            'filters' => $request->only(['status', 'duration', 'user', 'range']),
            'attemptsSeries' => JobStats::attemptSeries($scoped, $range),
            'durationSeries' => JobStats::durationSeries($scoped, $range),
            'jobStats' => JobStats::summary($scoped),
            'attempts' => $list->orderByDesc('occurred_at')->paginate(50)->withQueryString(),
            'users' => $this->userOptions($project),
        ]);
    }
}
