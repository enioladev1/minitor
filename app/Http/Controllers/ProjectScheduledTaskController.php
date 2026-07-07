<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Schedule\CronDescriber;
use App\Services\Stats\ScheduledTaskStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectScheduledTaskController extends Controller
{
    /**
     * The scheduled-tasks directory: every distinct task the project's
     * scheduler has run, with aggregate processed/skipped/failed counts,
     * timings, and its cron schedule/next-run time — the project-wide
     * "Scheduled Tasks" landing page, not a raw per-run feed.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);

        $scoped = $project->scheduledTasks()->where('occurred_at', '>=', $range->since);

        $rows = (clone $scoped)
            ->selectRaw('name, cron, timezone, repeat_seconds')
            ->selectRaw("count(*) filter (where status = 'processed') as processed")
            ->selectRaw("count(*) filter (where status = 'skipped') as skipped")
            ->selectRaw("count(*) filter (where status = 'failed') as failed")
            ->selectRaw('count(*) as total')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupBy('name', 'cron', 'timezone', 'repeat_seconds')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'cron' => $row->cron,
                'timezone' => $row->timezone,
                'repeat_seconds' => (int) $row->repeat_seconds,
                'schedule' => CronDescriber::describe($row->cron ?? '', (int) $row->repeat_seconds),
                'next_run' => CronDescriber::nextRun($row->cron ?? '', $row->timezone, (int) $row->repeat_seconds)?->toIso8601String(),
                'processed' => (int) $row->processed,
                'skipped' => (int) $row->skipped,
                'failed' => (int) $row->failed,
                'total' => (int) $row->total,
                'avg_us' => (int) round($row->avg_us ?? 0),
                'p95_us' => (int) round($row->p95_us ?? 0),
            ]);

        if ($search = $request->string('q')->toString()) {
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['name']), strtolower($search)))->values();
        }

        $sortColumns = ['name', 'processed', 'skipped', 'failed', 'total', 'avg_us', 'p95_us'];
        $sortMap = ['avg' => 'avg_us', 'p95' => 'p95_us'];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? $request->string('sort')->toString();
        $sortColumn = in_array($sortColumn, $sortColumns, true) ? $sortColumn : 'name';
        $descending = $request->string('direction')->toString() === 'desc';

        $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $descending)->values();

        return Inertia::render('projects/scheduled-tasks/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'range']),
            'statusSeries' => ScheduledTaskStats::statusSeries($scoped, $range),
            'durationSeries' => ScheduledTaskStats::durationSeries($scoped, $range),
            'taskStats' => ScheduledTaskStats::summary($scoped),
            'tasks' => $rows->all(),
        ]);
    }

    /**
     * Drill into a single scheduled task: its own charts, filterable by
     * outcome / slow runs, and the individual run rows.
     */
    public function task(Request $request, Project $project): Response
    {
        $name = $request->string('name')->toString();
        $cron = $request->string('cron')->toString();
        $timezone = $request->string('timezone')->toString() ?: null;
        $repeatSeconds = $request->integer('repeat_seconds');

        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);

        $scoped = $project->scheduledTasks()
            ->where('occurred_at', '>=', $range->since)
            ->where('name', $name)
            ->where('cron', $cron)
            ->where('repeat_seconds', $repeatSeconds)
            ->where(function ($q) use ($timezone) {
                $timezone === null ? $q->whereNull('timezone') : $q->where('timezone', $timezone);
            });

        $list = clone $scoped;

        if ($status = $request->string('status')->toString()) {
            match ($status) {
                'processed' => $list->where('status', 'processed'),
                'skipped' => $list->where('status', 'skipped'),
                'failed' => $list->where('status', 'failed'),
                default => null,
            };
        }

        if ($durationFilter = $request->string('duration')->toString()) {
            $summary = ScheduledTaskStats::summary($scoped);
            match ($durationFilter) {
                'avg' => $list->where('duration_us', '>=', $summary['avg_us']),
                'p95' => $list->where('duration_us', '>=', $summary['p95_us']),
                default => null,
            };
        }

        return Inertia::render('projects/scheduled-tasks/Task', [
            'project' => $project,
            'name' => $name,
            'cron' => $cron,
            'timezone' => $timezone,
            'repeatSeconds' => $repeatSeconds,
            'schedule' => CronDescriber::describe($cron, $repeatSeconds),
            'nextRun' => CronDescriber::nextRun($cron, $timezone, $repeatSeconds)?->toIso8601String(),
            'range' => $rangeKey,
            'filters' => $request->only(['status', 'duration', 'range']),
            'statusSeries' => ScheduledTaskStats::statusSeries($scoped, $range),
            'durationSeries' => ScheduledTaskStats::durationSeries($scoped, $range),
            'taskStats' => ScheduledTaskStats::summary($scoped),
            'runs' => $list->orderByDesc('occurred_at')->paginate(50)->withQueryString(),
        ]);
    }
}
