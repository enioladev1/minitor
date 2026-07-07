<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Stats\JobStats;
use App\Services\Stats\RequestStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectOverviewController extends Controller
{
    public function __invoke(Project $project, Request $request): Response
    {
        $rangeKey = TimeRange::resolve($request);
        $range = new TimeRange($rangeKey);

        $requestsQuery = $project->requests()->where('occurred_at', '>=', $range->since);
        $attemptsQuery = $project->jobAttempts()->where('occurred_at', '>=', $range->since);

        return Inertia::render('projects/Overview', [
            'project' => $project,
            'justCreated' => $request->boolean('setup'),
            'range' => $rangeKey,
            'requestsSeries' => RequestStats::series($requestsQuery, $range),
            'durationSeries' => RequestStats::durationSeries($requestsQuery, $range),
            'requestStats' => RequestStats::summary($requestsQuery),
            'exceptionsSummary' => $this->exceptionsSummary($project, $range),
            'jobStats' => JobStats::summary($attemptsQuery),
            'jobsSeries' => JobStats::attemptSeries($attemptsQuery, $range),
            'usersStats' => $this->usersStats($project, $range),
        ]);
    }

    /**
     * @return array{count: int, users_impacted: int}
     */
    private function exceptionsSummary(Project $project, TimeRange $range): array
    {
        $row = $project->exceptions()
            ->where('occurred_at', '>=', $range->since)
            ->selectRaw('count(*) as count')
            ->selectRaw('count(distinct user_external_id) as users_impacted')
            ->first();

        return [
            'count' => (int) ($row->count ?? 0),
            'users_impacted' => (int) ($row->users_impacted ?? 0),
        ];
    }

    /**
     * @return array{
     *     authenticated_requests: int,
     *     guest_requests: int,
     *     authenticated_users: int,
     *     most_active: list<array{external_id: string, name: string|null, requests: int}>,
     *     most_impacted_by_exceptions: list<array{external_id: string, name: string|null, exceptions: int}>,
     * }
     */
    private function usersStats(Project $project, TimeRange $range): array
    {
        $requestUserCounts = $project->requests()
            ->where('occurred_at', '>=', $range->since)
            ->selectRaw('count(*) filter (where user_external_id is not null) as authenticated')
            ->selectRaw('count(*) filter (where user_external_id is null) as guest')
            ->selectRaw('count(distinct user_external_id) as authenticated_users')
            ->first();

        $mostActive = $project->requests()
            ->where('occurred_at', '>=', $range->since)
            ->whereNotNull('user_external_id')
            ->selectRaw('user_external_id, count(*) as requests')
            ->groupBy('user_external_id')
            ->orderByDesc('requests')
            ->limit(5)
            ->get();

        $mostImpacted = $project->exceptions()
            ->where('occurred_at', '>=', $range->since)
            ->whereNotNull('user_external_id')
            ->selectRaw('user_external_id, count(*) as exceptions')
            ->groupBy('user_external_id')
            ->orderByDesc('exceptions')
            ->limit(5)
            ->get();

        $names = $project->monitoredUsers()
            ->whereIn('external_id', $mostActive->pluck('user_external_id')->merge($mostImpacted->pluck('user_external_id')))
            ->pluck('name', 'external_id');

        return [
            'authenticated_requests' => (int) ($requestUserCounts->authenticated ?? 0),
            'guest_requests' => (int) ($requestUserCounts->guest ?? 0),
            'authenticated_users' => (int) ($requestUserCounts->authenticated_users ?? 0),
            'most_active' => $mostActive->map(fn ($row) => [
                'external_id' => $row->user_external_id,
                'name' => $names->get($row->user_external_id),
                'requests' => (int) $row->requests,
            ])->all(),
            'most_impacted_by_exceptions' => $mostImpacted->map(fn ($row) => [
                'external_id' => $row->user_external_id,
                'name' => $names->get($row->user_external_id),
                'exceptions' => (int) $row->exceptions,
            ])->all(),
        ];
    }
}
