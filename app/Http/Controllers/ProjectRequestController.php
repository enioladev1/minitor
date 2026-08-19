<?php

namespace App\Http\Controllers;

use App\Models\MinitorRequest;
use App\Models\Project;
use App\Services\Stats\RequestStats;
use App\Services\Stats\TimeRange;
use App\Services\Timeline\RequestTimelineBuilder;
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

        $pathExpr = RequestStats::routePathExpr();
        $methodExpr = RequestStats::routeMethodExpr();

        $routesQuery = (clone $scoped)
            ->selectRaw("{$pathExpr} as path")
            ->selectRaw("{$methodExpr} as method")
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where status_code < 400) as success')
            ->selectRaw('count(*) filter (where status_code >= 400 and status_code < 500) as client_error')
            ->selectRaw('count(*) filter (where status_code >= 500) as server_error')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupByRaw("{$pathExpr}, {$methodExpr}");

        if ($search = $request->string('q')->toString()) {
            $routesQuery->havingRaw("{$pathExpr} like ?", ["%{$search}%"]);
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
            'routes' => $routesQuery
                // Unmatched Route is a catch-all bucket, not a real route -
                // keep it pinned to the bottom regardless of the chosen sort.
                ->orderByRaw("({$pathExpr} = ?) asc", [RequestStats::UNMATCHED_PATH])
                ->orderBy($sortColumn, $direction)
                ->paginate(50)
                ->withQueryString(),
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
     *
     * The unmatched-route bucket is a special case: rather than a flat feed
     * of every unmatched hit (often hundreds of scanner/bot requests from a
     * handful of IPs), it defaults to one row per IP with aggregate counts.
     * Clicking an IP (the `ip` filter) drills into that IP's raw requests.
     */
    public function route(Request $request, Project $project): Response
    {
        $method = $request->string('method')->toString();
        $path = $request->string('path')->toString();
        $isUnmatched = $method === RequestStats::UNMATCHED_METHOD && $path === RequestStats::UNMATCHED_PATH;

        $rangeKey = TimeRange::resolve($request);
        $range = new TimeRange($rangeKey);
        $userFilter = $request->string('user')->toString() ?: null;
        $ipFilter = $isUnmatched ? ($request->string('ip')->toString() ?: null) : null;

        $base = $project->requests()->where('occurred_at', '>=', $range->since);

        if ($isUnmatched) {
            $base->where(function ($q) {
                $q->whereNull('route_path')->orWhere('route_path', '');
            });
        } else {
            $base->where('method', $method)->where('route_path', $path);
        }

        if ($ipFilter !== null) {
            $base->where('ip', $ipFilter);
        }

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

        $groupedByIp = $isUnmatched && $ipFilter === null;

        $requests = $groupedByIp
            ? $list
                ->selectRaw('ip')
                ->selectRaw('count(*) as total')
                ->selectRaw('count(*) filter (where status_code < 400) as success')
                ->selectRaw('count(*) filter (where status_code >= 400 and status_code < 500) as client_error')
                ->selectRaw('count(*) filter (where status_code >= 500) as server_error')
                ->selectRaw('max(occurred_at) as last_seen_at')
                ->groupBy('ip')
                ->orderByDesc('total')
                ->paginate(50)
                ->withQueryString()
            : $list->orderByDesc('occurred_at')->paginate(50)->withQueryString();

        return Inertia::render('projects/requests/Route', [
            'project' => $project,
            'method' => $method,
            'path' => $path,
            'range' => $rangeKey,
            'filters' => $request->only(['status', 'duration', 'user', 'range', 'ip']),
            'requestsSeries' => RequestStats::series($scoped, $range),
            'durationSeries' => RequestStats::durationSeries($scoped, $range),
            'requestStats' => RequestStats::summary($scoped),
            'requests' => $requests,
            'groupedByIp' => $groupedByIp,
            'users' => $this->userOptions($project),
        ]);
    }

    public function show(Project $project, MinitorRequest $request, RequestTimelineBuilder $timelineBuilder): Response
    {
        abort_unless($request->project_id === $project->id, 404);

        return Inertia::render('projects/requests/Show', [
            'project' => $project,
            'requestRecord' => $request,
            'timeline' => $timelineBuilder->build($request),
        ]);
    }
}
