<?php

namespace App\Http\Controllers;

use App\Models\MonitoredUser;
use App\Models\Project;
use App\Services\Stats\RequestStats;
use App\Services\Stats\TimeRange;
use App\Services\Stats\UserStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectUserController extends Controller
{
    /**
     * The users directory: every distinct user the project's requests,
     * jobs, and exceptions have identified within the selected window,
     * with per-user activity counts - the project-wide "Users" landing
     * page, not a raw per-record feed.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);

        $requestsScoped = $project->requests()->where('occurred_at', '>=', $range->since);

        $usersQuery = $project->monitoredUsers()->where('last_seen_at', '>=', $range->since);

        if ($search = $request->string('q')->toString()) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('external_id', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->get();
        $externalIds = $users->pluck('external_id');

        $requestStatsByUser = $project->requests()
            ->where('occurred_at', '>=', $range->since)
            ->whereIn('user_external_id', $externalIds)
            ->selectRaw('user_external_id')
            ->selectRaw('count(*) filter (where status_code < 400) as success')
            ->selectRaw('count(*) filter (where status_code >= 400 and status_code < 500) as client_error')
            ->selectRaw('count(*) filter (where status_code >= 500) as server_error')
            ->selectRaw('count(*) as total')
            ->groupBy('user_external_id')
            ->get()
            ->keyBy('user_external_id');

        $jobStatsByUser = $project->queuedJobs()
            ->where('occurred_at', '>=', $range->since)
            ->whereIn('user_external_id', $externalIds)
            ->selectRaw('user_external_id, count(*) as total')
            ->groupBy('user_external_id')
            ->get()
            ->keyBy('user_external_id');

        $exceptionStatsByUser = $project->exceptions()
            ->where('occurred_at', '>=', $range->since)
            ->whereIn('user_external_id', $externalIds)
            ->selectRaw('user_external_id, count(*) as total')
            ->groupBy('user_external_id')
            ->get()
            ->keyBy('user_external_id');

        $rows = $users->map(function ($user) use ($requestStatsByUser, $jobStatsByUser, $exceptionStatsByUser) {
            $r = $requestStatsByUser->get($user->external_id);
            $j = $jobStatsByUser->get($user->external_id);
            $e = $exceptionStatsByUser->get($user->external_id);

            return [
                'external_id' => $user->external_id,
                'name' => $user->name,
                'username' => $user->username,
                'success' => (int) ($r->success ?? 0),
                'client_error' => (int) ($r->client_error ?? 0),
                'server_error' => (int) ($r->server_error ?? 0),
                'requests' => (int) ($r->total ?? 0),
                'queued_jobs' => (int) ($j->total ?? 0),
                'exceptions' => (int) ($e->total ?? 0),
                'last_seen_at' => $user->last_seen_at->toIso8601String(),
            ];
        });

        $sortMap = [
            'success' => 'success', 'client_error' => 'client_error', 'server_error' => 'server_error',
            'requests' => 'requests', 'queued_jobs' => 'queued_jobs', 'exceptions' => 'exceptions', 'last_seen' => 'last_seen_at',
        ];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? 'requests';
        $descending = $request->string('direction')->toString() !== 'asc';
        $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $descending)->values();

        return Inertia::render('projects/users/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'range']),
            'activeUsersSeries' => UserStats::activeUsersSeries($requestsScoped, $range),
            'requestsSeries' => UserStats::requestsSeries($requestsScoped, $range),
            'requestsSummary' => UserStats::requestsSummary($requestsScoped),
            'authenticatedUsers' => $users->count(),
            'users' => $rows->all(),
        ]);
    }

    /**
     * Drill into a single user: their request activity chart, top/slowest
     * routes, and every individual request - with links out to the
     * user-filtered Requests/Jobs/Exceptions pages for the rest of their
     * activity.
     */
    public function show(Request $request, Project $project, string $externalId): Response
    {
        $user = MonitoredUser::where('project_id', $project->id)->where('external_id', $externalId)->firstOrFail();

        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);

        $scoped = $project->requests()
            ->where('occurred_at', '>=', $range->since)
            ->where('user_external_id', $externalId);

        $list = clone $scoped;

        if ($search = $request->string('q')->toString()) {
            $list->where(function ($q) use ($search) {
                $q->where('url', 'like', "%{$search}%")->orWhere('route_path', 'like', "%{$search}%");
            });
        }

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

        $routeGroups = (clone $scoped)
            ->selectRaw("method, coalesce(nullif(route_path, ''), url) as path")
            ->selectRaw('count(*) as total')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupByRaw("method, coalesce(nullif(route_path, ''), url)")
            ->get();

        $topRoutes = $routeGroups->sortByDesc('total')->take(4)->values();
        $slowestRoutes = $routeGroups->sortByDesc('p95_us')->take(4)->values();

        return Inertia::render('projects/users/Show', [
            'project' => $project,
            'user' => $user,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'status', 'duration', 'range']),
            'requestsSeries' => RequestStats::series($scoped, $range),
            'requestStats' => RequestStats::summary($scoped),
            'topRoutes' => $topRoutes->map(fn ($row) => [
                'method' => $row->method,
                'path' => $row->path,
                'total' => (int) $row->total,
            ]),
            'slowestRoutes' => $slowestRoutes->map(fn ($row) => [
                'method' => $row->method,
                'path' => $row->path,
                'p95_us' => (int) round($row->p95_us ?? 0),
            ]),
            'requests' => $list->orderByDesc('occurred_at')->paginate(50)->withQueryString(),
        ]);
    }
}
