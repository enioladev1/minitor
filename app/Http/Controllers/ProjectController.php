<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Stats\RequestStats;
use App\Services\Stats\TimeRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * The projects landing page: every app this user monitors, each with a
     * 24-hour vitals strip (request volume + error/latency snapshot) so
     * health reads at a glance without opening a single one.
     */
    public function index(Request $request): Response
    {
        $range = new TimeRange('24h');

        $projects = $request->user()->projects()
            ->withCount([
                'requests',
                'issues as open_issues_count' => fn ($query) => $query->where('status', 'open'),
            ])
            ->latest()
            ->get();

        $projects->each(function (Project $project) use ($range) {
            $scoped = $project->requests()->where('occurred_at', '>=', $range->since);

            $project->setAttribute('activity_series', RequestStats::series($scoped, $range));
            $project->setAttribute('activity_stats', RequestStats::summary($scoped));
        });

        return Inertia::render('projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $project = $request->user()->projects()->create($data);

        return to_route('projects.show', ['project' => $project, 'setup' => 1])
            ->with('success', "Project \"{$project->name}\" created.");
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notify_on_new_issue' => ['sometimes', 'boolean'],
        ]);

        $project->update($data);

        return back()->with('success', 'Project renamed.');
    }

    public function regenerateToken(Project $project): RedirectResponse
    {
        $project->regenerateToken();

        return back()->with('success', 'Project key regenerated. Update it in your Laravel app\'s .env.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return to_route('projects.index')->with('success', "Project \"{$project->name}\" deleted.");
    }
}
