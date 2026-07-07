<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Stats\QueryStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectQueryController extends Controller
{
    private const GROUP_EXPR = 'coalesce(query_group, sql)';

    /**
     * The queries directory: every distinct query shape the project has
     * run (grouped by Nightwatch's own `_group` hash, which collapses
     * variable-length `in (...)` clauses and bulk insert value lists), with
     * aggregate call counts and timings - the project-wide "Queries"
     * landing page, not a raw per-call feed.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = TimeRange::resolve($request);
        $range = new TimeRange($rangeKey);

        $scoped = $project->queries()->where('occurred_at', '>=', $range->since);

        $rows = (clone $scoped)
            ->selectRaw(self::GROUP_EXPR.' as group_key')
            ->selectRaw('(array_agg(sql order by occurred_at desc))[1] as sql')
            ->selectRaw('(array_agg(connection order by occurred_at desc))[1] as connection')
            ->selectRaw('(array_agg(connection_type order by occurred_at desc))[1] as connection_type')
            ->selectRaw('count(*) as calls')
            ->selectRaw('sum(duration_us) as sum_us')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupByRaw(self::GROUP_EXPR)
            ->get()
            ->map(fn ($row) => [
                'group_key' => $row->group_key,
                'sql' => $row->sql,
                'connection' => $row->connection,
                'connection_type' => $row->connection_type,
                'calls' => (int) $row->calls,
                'sum_us' => (int) $row->sum_us,
                'avg_us' => (int) round($row->avg_us ?? 0),
                'p95_us' => (int) round($row->p95_us ?? 0),
            ]);

        if ($search = $request->string('q')->toString()) {
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['sql']), strtolower($search)))->values();
        }

        $sortColumns = ['sql', 'connection', 'calls', 'sum_us', 'avg_us', 'p95_us'];
        $sortMap = ['query' => 'sql', 'total' => 'sum_us', 'avg' => 'avg_us', 'p95' => 'p95_us'];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? $request->string('sort')->toString();
        $sortColumn = in_array($sortColumn, $sortColumns, true) ? $sortColumn : 'sum_us';
        $descending = $request->string('direction')->toString() !== 'asc';

        $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $descending)->values();

        return Inertia::render('projects/queries/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'range']),
            'callSeries' => QueryStats::callSeries($scoped, $range),
            'durationSeries' => QueryStats::durationSeries($scoped, $range),
            'queryStats' => QueryStats::summary($scoped),
            'queries' => $rows->all(),
        ]);
    }

    /**
     * Drill into a single query shape: its own charts, filterable by slow
     * calls, and the individual call rows.
     */
    public function query(Request $request, Project $project): Response
    {
        $groupKey = $request->string('group')->toString();

        $rangeKey = TimeRange::resolve($request);
        $range = new TimeRange($rangeKey);

        $scoped = $project->queries()
            ->where('occurred_at', '>=', $range->since)
            ->whereRaw(self::GROUP_EXPR.' = ?', [$groupKey]);

        $representative = (clone $scoped)->orderByDesc('occurred_at')->first();

        $list = clone $scoped;

        if ($durationFilter = $request->string('duration')->toString()) {
            $summary = QueryStats::summary($scoped);
            match ($durationFilter) {
                'avg' => $list->where('duration_us', '>=', $summary['avg_us']),
                'p95' => $list->where('duration_us', '>=', $summary['p95_us']),
                default => null,
            };
        }

        return Inertia::render('projects/queries/Query', [
            'project' => $project,
            'groupKey' => $groupKey,
            'sql' => $representative?->sql,
            'range' => $rangeKey,
            'filters' => $request->only(['duration', 'range']),
            'callSeries' => QueryStats::callSeries($scoped, $range),
            'durationSeries' => QueryStats::durationSeries($scoped, $range),
            'queryStats' => QueryStats::summary($scoped),
            'calls' => $list->orderByDesc('occurred_at')->paginate(50)->withQueryString(),
        ]);
    }
}
