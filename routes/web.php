<?php

use App\Http\Controllers\ProjectCacheEventController;
use App\Http\Controllers\ProjectCommandController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectExceptionController;
use App\Http\Controllers\ProjectIssueController;
use App\Http\Controllers\ProjectJobController;
use App\Http\Controllers\ProjectLogController;
use App\Http\Controllers\ProjectOutgoingRequestController;
use App\Http\Controllers\ProjectOverviewController;
use App\Http\Controllers\ProjectQueryController;
use App\Http\Controllers\ProjectRequestController;
use App\Http\Controllers\ProjectScheduledTaskController;
use App\Http\Controllers\ProjectUserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/projects')->name('dashboard');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::middleware('project.owner')->prefix('projects/{project}')->name('projects.')->group(function () {
        Route::get('/', ProjectOverviewController::class)->name('show');
        Route::patch('/', [ProjectController::class, 'update'])->name('update');
        Route::delete('/', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('regenerate-token', [ProjectController::class, 'regenerateToken'])->name('regenerate-token');

        Route::get('requests', [ProjectRequestController::class, 'index'])->name('requests.index');
        Route::get('requests/route', [ProjectRequestController::class, 'route'])->name('requests.route');
        Route::get('requests/{request}', [ProjectRequestController::class, 'show'])->name('requests.show');

        Route::get('queries', [ProjectQueryController::class, 'index'])->name('queries.index');
        Route::get('queries/query', [ProjectQueryController::class, 'query'])->name('queries.query');

        Route::get('issues', [ProjectIssueController::class, 'index'])->name('issues.index');
        Route::get('issues/{issue}', [ProjectIssueController::class, 'show'])->name('issues.show');
        Route::patch('issues/{issue}/status', [ProjectIssueController::class, 'updateStatus'])->name('issues.update-status');
        Route::post('issues/{issue}/comments', [ProjectIssueController::class, 'storeComment'])->name('issues.comments.store');

        Route::get('jobs', [ProjectJobController::class, 'index'])->name('jobs.index');
        Route::get('jobs/job', [ProjectJobController::class, 'job'])->name('jobs.job');

        Route::get('commands', [ProjectCommandController::class, 'index'])->name('commands.index');
        Route::get('commands/command', [ProjectCommandController::class, 'command'])->name('commands.command');
        Route::get('scheduled-tasks', [ProjectScheduledTaskController::class, 'index'])->name('scheduled-tasks.index');
        Route::get('scheduled-tasks/task', [ProjectScheduledTaskController::class, 'task'])->name('scheduled-tasks.task');
        Route::get('exceptions', [ProjectExceptionController::class, 'index'])->name('exceptions.index');
        Route::get('exceptions/{issue}', [ProjectExceptionController::class, 'show'])->name('exceptions.show');
        Route::get('cache', [ProjectCacheEventController::class, 'index'])->name('cache.index');
        Route::get('cache/key', [ProjectCacheEventController::class, 'key'])->name('cache.key');
        Route::get('outgoing-requests', [ProjectOutgoingRequestController::class, 'index'])->name('outgoing-requests.index');
        Route::get('outgoing-requests/host', [ProjectOutgoingRequestController::class, 'host'])->name('outgoing-requests.host');
        Route::get('users', [ProjectUserController::class, 'index'])->name('users.index');
        Route::get('users/{externalId}', [ProjectUserController::class, 'show'])->name('users.show');
        Route::get('logs', [ProjectLogController::class, 'index'])->name('logs.index');
    });
});

require __DIR__.'/settings.php';
