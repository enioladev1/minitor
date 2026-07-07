<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectLogController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $query = $project->logs()->orderByDesc('occurred_at');

        if ($level = $request->string('level')->toString()) {
            $query->where('level', $level);
        }

        return Inertia::render('projects/logs/Index', [
            'project' => $project,
            'logs' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['level']),
        ]);
    }
}
