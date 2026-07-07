<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Stats\CacheStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectCacheEventController extends Controller
{
    /**
     * The top 100 cache keys directory: every distinct store+key the
     * project has touched, with hit/miss/write/delete/failure counts -
     * the project-wide "Cache" landing page, not a raw per-event feed.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = TimeRange::resolve($request);
        $range = new TimeRange($rangeKey);

        $scoped = $project->cacheEvents()->where('occurred_at', '>=', $range->since);

        $rows = (clone $scoped)
            ->selectRaw('store')
            ->selectRaw('key')
            ->selectRaw("count(*) filter (where type = 'hit') as hits")
            ->selectRaw("count(*) filter (where type = 'miss') as misses")
            ->selectRaw("count(*) filter (where type = 'write') as writes")
            ->selectRaw("count(*) filter (where type = 'delete') as deletes")
            ->selectRaw("count(*) filter (where type in ('write-failure', 'delete-failure')) as failures")
            ->selectRaw('count(*) as total')
            ->groupBy('store', 'key')
            ->orderByDesc('total')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $hits = (int) $row->hits;
                $misses = (int) $row->misses;
                $lookups = $hits + $misses;

                return [
                    'store' => $row->store,
                    'key' => $row->key,
                    'hit_rate' => $lookups > 0 ? round(($hits / $lookups) * 100, 2) : null,
                    'hits' => $hits,
                    'misses' => $misses,
                    'writes' => (int) $row->writes,
                    'deletes' => (int) $row->deletes,
                    'failures' => (int) $row->failures,
                    'total' => (int) $row->total,
                ];
            });

        $sortMap = ['hit_rate' => 'hit_rate', 'hits' => 'hits', 'misses' => 'misses', 'writes' => 'writes', 'deletes' => 'deletes', 'failures' => 'failures', 'total' => 'total'];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? null;

        if ($sortColumn !== null) {
            $descending = $request->string('direction')->toString() !== 'asc';
            $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $descending)->values();
        }

        return Inertia::render('projects/cache-events/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'eventSeries' => CacheStats::eventSeries($scoped, $range),
            'failureSeries' => CacheStats::failureSeries($scoped, $range),
            'cacheStats' => CacheStats::summary($scoped),
            'keys' => $rows->all(),
        ]);
    }

    /**
     * Drill into a single store+key: its own event mix and every raw event.
     */
    public function key(Request $request, Project $project): Response
    {
        $store = $request->string('store')->toString();
        $key = $request->string('key')->toString();

        $rangeKey = TimeRange::resolve($request);
        $range = new TimeRange($rangeKey);

        $scoped = $project->cacheEvents()
            ->where('occurred_at', '>=', $range->since)
            ->where('store', $store)
            ->where('key', $key);

        $list = clone $scoped;

        if ($type = $request->string('type')->toString()) {
            $list->where('type', $type);
        }

        return Inertia::render('projects/cache-events/Key', [
            'project' => $project,
            'store' => $store,
            'cacheKey' => $key,
            'range' => $rangeKey,
            'filters' => $request->only(['type', 'range']),
            'eventSeries' => CacheStats::eventSeries($scoped, $range),
            'failureSeries' => CacheStats::failureSeries($scoped, $range),
            'cacheStats' => CacheStats::summary($scoped),
            'events' => $list->orderByDesc('occurred_at')->paginate(50)->withQueryString(),
        ]);
    }
}
