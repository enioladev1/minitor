<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\IssueEvent;
use App\Models\Project;
use App\Services\Exceptions\ExceptionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectIssueController extends Controller
{
    private const SORTABLE = [
        'count' => 'occurrences_count',
        'users' => 'users_impacted',
        'first_seen' => 'first_seen_at',
        'last_seen' => 'last_seen_at',
    ];

    public function index(Request $request, Project $project): Response
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, Issue::STATUSES, true) ? $status : 'open';

        $sortKey = $request->string('sort')->toString();
        $sortColumn = self::SORTABLE[$sortKey] ?? 'last_seen_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $query = $project->issues()
            ->selectRaw('issues.*')
            ->selectRaw(
                '(select count(distinct user_external_id) from exceptions '.
                'where exceptions.issue_id = issues.id and exceptions.user_external_id is not null) as users_impacted'
            )
            ->where('status', $status);

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('class', 'like', "%{$search}%")
                    ->orWhere('last_message', 'like', "%{$search}%");
            });
        }

        $counts = $project->issues()
            ->selectRaw("count(*) filter (where status = 'open') as open")
            ->selectRaw("count(*) filter (where status = 'resolved') as resolved")
            ->selectRaw("count(*) filter (where status = 'ignored') as ignored")
            ->first();

        return Inertia::render('projects/issues/Index', [
            'project' => $project,
            'issues' => $query->orderBy($sortColumn, $direction)->paginate(50)->withQueryString(),
            'filters' => $request->only(['q', 'status', 'sort', 'direction']),
            'counts' => [
                'open' => (int) ($counts->open ?? 0),
                'resolved' => (int) ($counts->resolved ?? 0),
                'ignored' => (int) ($counts->ignored ?? 0),
            ],
        ]);
    }

    public function show(Project $project, Issue $issue): Response
    {
        abort_unless($issue->project_id === $project->id, 404);

        $latestOccurrence = $issue->occurrences()->orderByDesc('occurred_at')->first();

        $impactedUsers = (int) $issue->occurrences()->distinct('user_external_id')->whereNotNull('user_external_id')->count('user_external_id');
        $occurrences7d = $issue->occurrences()->where('occurred_at', '>=', now()->subDays(7))->count();
        $occurrences24h = $issue->occurrences()->where('occurred_at', '>=', now()->subHours(24))->count();

        return Inertia::render('projects/issues/Show', [
            'project' => $project,
            'issue' => $issue,
            'latestOccurrence' => $latestOccurrence,
            'latestOccurrenceContext' => $latestOccurrence
                ? ExceptionContext::build($project, $latestOccurrence)
                : null,
            'impactedUsers' => $impactedUsers,
            'occurrences7d' => $occurrences7d,
            'occurrences24h' => $occurrences24h,
            'occurrences' => $issue->occurrences()->orderByDesc('occurred_at')->paginate(25),
            'events' => $issue->events()->with('user:id,name')->orderBy('created_at')->get(),
        ]);
    }

    public function updateStatus(Request $request, Project $project, Issue $issue): RedirectResponse
    {
        abort_unless($issue->project_id === $project->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Issue::STATUSES)],
        ]);

        $from = $issue->status;
        $issue->update(['status' => $data['status']]);

        if ($from !== $data['status']) {
            IssueEvent::create([
                'issue_id' => $issue->id,
                'user_id' => $request->user()->id,
                'type' => 'status_changed',
                'meta' => ['from' => $from, 'to' => $data['status']],
            ]);
        }

        return back()->with('success', 'Issue '.$data['status'].'.');
    }

    public function storeComment(Request $request, Project $project, Issue $issue): RedirectResponse
    {
        abort_unless($issue->project_id === $project->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        IssueEvent::create([
            'issue_id' => $issue->id,
            'user_id' => $request->user()->id,
            'type' => 'comment',
            'body' => Str::of($data['body'])->trim(),
        ]);

        return back();
    }
}
