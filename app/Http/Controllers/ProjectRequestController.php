<?php

namespace App\Http\Controllers;

use App\Models\MinitorRequest;
use App\Models\Project;
use App\Services\Stats\RequestStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRequestController extends Controller
{
    /**
     * The routes directory: every distinct method+path the project has seen,
     * with aggregate counts/timings - the project-wide "Requests" landing
     * page, not a raw per-request feed.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = TimeRange::resolve($request);
        $range = new TimeRange($rangeKey);
        $userFilter = $request->string('user')->toString() ?: null;

        $scoped = RequestStats::applyUserFilter(
            $project->requests()->where('occurred_at', '>=', $range->since),
            $userFilter
        );

        $routesQuery = (clone $scoped)
            ->selectRaw("coalesce(nullif(route_path, ''), url) as path")
            ->selectRaw('method')
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where status_code < 400) as success')
            ->selectRaw('count(*) filter (where status_code >= 400 and status_code < 500) as client_error')
            ->selectRaw('count(*) filter (where status_code >= 500) as server_error')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupByRaw("coalesce(nullif(route_path, ''), url), method");

        if ($search = $request->string('q')->toString()) {
            $routesQuery->havingRaw("coalesce(nullif(route_path, ''), url) like ?", ["%{$search}%"]);
        }

        $sortMap = [
            'method' => 'method',
            'path' => 'path',
            'success' => 'success',
            'client_error' => 'client_error',
            'server_error' => 'server_error',
            'total' => 'total',
            'avg' => 'avg_us',
            'p95' => 'p95_us',
        ];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? 'total';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        return Inertia::render('projects/requests/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'user', 'range']),
            'requestsSeries' => RequestStats::series($scoped, $range),
            'durationSeries' => RequestStats::durationSeries($scoped, $range),
            'requestStats' => RequestStats::summary($scoped),
            'routes' => $routesQuery->orderBy($sortColumn, $direction)->paginate(50)->withQueryString(),
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
     * Drill into a single route (method + path): its own charts, filterable
     * by status class / slow requests, and the individual request rows.
     */
    public function route(Request $request, Project $project): Response
    {
        $method = $request->string('method')->toString();
        $path = $request->string('path')->toString();

        $rangeKey = TimeRange::resolve($request);
        $range = new TimeRange($rangeKey);
        $userFilter = $request->string('user')->toString() ?: null;

        $base = $project->requests()
            ->where('occurred_at', '>=', $range->since)
            ->where('method', $method)
            ->where(function ($q) use ($path) {
                $q->where('route_path', $path)->orWhere(function ($q2) use ($path) {
                    $q2->where(function ($q3) {
                        $q3->whereNull('route_path')->orWhere('route_path', '');
                    })->where('url', $path);
                });
            });

        $scoped = RequestStats::applyUserFilter($base, $userFilter);

        $list = clone $scoped;

        if ($status = $request->string('status')->toString()) {
            match ($status) {
                'success' => $list->where('status_code', '<', 400),
                'client_error' => $list->whereBetween('status_code', [400, 499]),
                'server_error' => $list->where('status_code', '>=', 500),
                default => null,
            };
        }

        if ($durationFilter = $request->string('duration')->toString()) {
            $summary = RequestStats::summary($scoped);
            match ($durationFilter) {
                'avg' => $list->where('duration_us', '>=', $summary['avg_us']),
                'p95' => $list->where('duration_us', '>=', $summary['p95_us']),
                default => null,
            };
        }

        return Inertia::render('projects/requests/Route', [
            'project' => $project,
            'method' => $method,
            'path' => $path,
            'range' => $rangeKey,
            'filters' => $request->only(['status', 'duration', 'user', 'range']),
            'requestsSeries' => RequestStats::series($scoped, $range),
            'durationSeries' => RequestStats::durationSeries($scoped, $range),
            'requestStats' => RequestStats::summary($scoped),
            'requests' => $list->orderByDesc('occurred_at')->paginate(50)->withQueryString(),
            'users' => $this->userOptions($project),
        ]);
    }

    public function show(Project $project, MinitorRequest $request): Response
    {
        abort_unless($request->project_id === $project->id, 404);

        return Inertia::render('projects/requests/Show', [
            'project' => $project,
            'requestRecord' => $request,
        ]);
    }
}
