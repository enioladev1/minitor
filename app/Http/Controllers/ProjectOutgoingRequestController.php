<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Stats\RequestStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectOutgoingRequestController extends Controller
{
    /**
     * The outgoing-requests directory: every distinct host the project has
     * called out to, with aggregate status-class counts and timings - the
     * project-wide "Outgoing Requests" landing page, not a raw per-call feed.
     * Reuses RequestStats since outgoing calls have the same status_code +
     * duration_us shape as inbound requests.
     */
    public function index(Request $request, Project $project): Response
    {
        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);

        $scoped = $project->outgoingRequests()->where('occurred_at', '>=', $range->since);

        $rows = (clone $scoped)
            ->selectRaw('host')
            ->selectRaw('count(*) filter (where status_code < 400) as success')
            ->selectRaw('count(*) filter (where status_code >= 400 and status_code < 500) as client_error')
            ->selectRaw('count(*) filter (where status_code >= 500) as server_error')
            ->selectRaw('count(*) as total')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupBy('host')
            ->get()
            ->map(fn ($row) => [
                'host' => $row->host,
                'success' => (int) $row->success,
                'client_error' => (int) $row->client_error,
                'server_error' => (int) $row->server_error,
                'total' => (int) $row->total,
                'avg_us' => (int) round($row->avg_us ?? 0),
                'p95_us' => (int) round($row->p95_us ?? 0),
            ]);

        if ($search = $request->string('q')->toString()) {
            $rows = $rows->filter(fn ($row) => str_contains(strtolower((string) $row['host']), strtolower($search)))->values();
        }

        $sortColumns = ['host', 'success', 'client_error', 'server_error', 'total', 'avg_us', 'p95_us'];
        $sortMap = ['avg' => 'avg_us', 'p95' => 'p95_us'];
        $sortColumn = $sortMap[$request->string('sort')->toString()] ?? $request->string('sort')->toString();
        $sortColumn = in_array($sortColumn, $sortColumns, true) ? $sortColumn : 'total';
        $descending = $request->string('direction')->toString() !== 'asc';

        $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $descending)->values();

        return Inertia::render('projects/outgoing-requests/Index', [
            'project' => $project,
            'range' => $rangeKey,
            'filters' => $request->only(['q', 'range']),
            'requestsSeries' => RequestStats::series($scoped, $range),
            'durationSeries' => RequestStats::durationSeries($scoped, $range),
            'requestStats' => RequestStats::summary($scoped),
            'hosts' => $rows->all(),
        ]);
    }

    /**
     * Drill into a single host: its own charts, filterable by status class /
     * slow calls, and the individual call rows.
     */
    public function host(Request $request, Project $project): Response
    {
        $host = $request->string('host')->toString();

        $rangeKey = $request->string('range')->toString();
        $rangeKey = TimeRange::isValid($rangeKey) ? $rangeKey : '7d';
        $range = new TimeRange($rangeKey);

        $scoped = $project->outgoingRequests()
            ->where('occurred_at', '>=', $range->since)
            ->where('host', $host);

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

        return Inertia::render('projects/outgoing-requests/Host', [
            'project' => $project,
            'host' => $host,
            'range' => $rangeKey,
            'filters' => $request->only(['status', 'duration', 'range']),
            'requestsSeries' => RequestStats::series($scoped, $range),
            'durationSeries' => RequestStats::durationSeries($scoped, $range),
            'requestStats' => RequestStats::summary($scoped),
            'requests' => $list->orderByDesc('occurred_at')->paginate(50)->withQueryString(),
        ]);
    }
}
