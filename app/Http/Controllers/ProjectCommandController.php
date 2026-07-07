<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Stats\CommandStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectCommandController extends Controller
{
    /**
     * The commands directory: every distinct artisan command the project
     * has run, with aggregate success/failure counts and timings — the
     * project-wide "Commands" landing page, not a raw per-call feed.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);

        $scoped = $project->commands()->where('occurred_at', '>=', $range->since);

        $rows = (clone $scoped)
            ->selectRaw('name')
            ->selectRaw('count(*) filter (where exit_code = 0) as success')
            ->selectRaw('count(*) filter (where exit_code != 0) as failed')
            ->selectRaw('count(*) as total')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupBy('name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'success' => (int) $row->success,
                'failed' => (int) $row->failed,
                'total' => (int) $row->total,
                'avg_us' => (int) round($row->avg_us ?? 0),
                'p95_us' => (int) round($row->p95_us ?? 0),
            ]);

        if ($search = $request->string('q')->toString()) {
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['name']), strtolower($search)))->values();
        }

        $sortColumns = ['name', 'success', 'failed', 'total', 'avg_us', 'p95_us'];
        $sortMap = ['avg' => 'avg_us', 'p95' => 'p95_us'];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? $request->string('sort')->toString();
        $sortColumn = in_array($sortColumn, $sortColumns, true) ? $sortColumn : 'total';
        $descending = $request->string('direction')->toString() !== 'asc';

        $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $descending)->values();

        return Inertia::render('projects/commands/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'range']),
            'callSeries' => CommandStats::callSeries($scoped, $range),
            'durationSeries' => CommandStats::durationSeries($scoped, $range),
            'commandStats' => CommandStats::summary($scoped),
            'commands' => $rows->all(),
        ]);
    }

    /**
     * Drill into a single command: its own charts, filterable by outcome /
     * slow calls, and the individual call rows.
     */
    public function command(Request $request, Project $project): Response
    {
        $name = $request->string('name')->toString();

        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);

        $scoped = $project->commands()
            ->where('occurred_at', '>=', $range->since)
            ->where('name', $name);

        $list = clone $scoped;

        if ($status = $request->string('status')->toString()) {
            match ($status) {
                'successful' => $list->where('exit_code', 0),
                'failed' => $list->where('exit_code', '!=', 0),
                default => null,
            };
        }

        if ($durationFilter = $request->string('duration')->toString()) {
            $summary = CommandStats::summary($scoped);
            match ($durationFilter) {
                'avg' => $list->where('duration_us', '>=', $summary['avg_us']),
                'p95' => $list->where('duration_us', '>=', $summary['p95_us']),
                default => null,
            };
        }

        return Inertia::render('projects/commands/Command', [
            'project' => $project,
            'name' => $name,
            'range' => $rangeKey,
            'filters' => $request->only(['status', 'duration', 'range']),
            'callSeries' => CommandStats::callSeries($scoped, $range),
            'durationSeries' => CommandStats::durationSeries($scoped, $range),
            'commandStats' => CommandStats::summary($scoped),
            'commands' => $list->orderByDesc('occurred_at')->paginate(50)->withQueryString(),
        ]);
    }
}
