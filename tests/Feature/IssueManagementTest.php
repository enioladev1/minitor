<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueManagementTest extends TestCase
{
    use RefreshDatabase;

    private function ingestException(Project $project, string $fingerprint, string $message = 'Broke'): void
    {
        $this->postJson('/api/ingest', [
            'records' => [[
                'v' => 3, 't' => 'exception', 'timestamp' => (float) now()->timestamp,
                '_group' => $fingerprint, 'class' => 'RuntimeException', 'file' => 'app/Foo.php', 'line' => 5,
                'message' => $message, 'code' => 0, 'handled' => false,
            ]],
        ], ['Authorization' => 'Bearer '.$project->token])->assertOk();
    }

    public function test_new_issues_default_to_open_status(): void
    {
        $project = Project::factory()->for(User::factory())->create();
        $this->ingestException($project, 'fp1');

        $this->assertDatabaseHas('issues', ['fingerprint' => 'fp1', 'status' => 'open']);
    }

    public function test_owner_can_resolve_and_ignore_an_issue(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->ingestException($project, 'fp1');
        $issue = Issue::where('fingerprint', 'fp1')->firstOrFail();

        $this->actingAs($user)
            ->patch("/projects/{$project->id}/issues/{$issue->id}/status", ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame('resolved', $issue->fresh()->status);
        $this->assertDatabaseHas('issue_events', [
            'issue_id' => $issue->id,
            'type' => 'status_changed',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch("/projects/{$project->id}/issues/{$issue->id}/status", ['status' => 'ignored'])
            ->assertRedirect();

        $this->assertSame('ignored', $issue->fresh()->status);
    }

    public function test_resolved_issue_reopens_automatically_when_it_recurs(): void
    {
        $project = Project::factory()->for(User::factory())->create();
        $this->ingestException($project, 'fp1');
        $issue = Issue::where('fingerprint', 'fp1')->firstOrFail();
        $issue->update(['status' => 'resolved']);

        $this->ingestException($project, 'fp1', 'Broke again');

        $this->assertSame('open', $issue->fresh()->status);
        $this->assertDatabaseHas('issue_events', [
            'issue_id' => $issue->id,
            'type' => 'status_changed',
            'user_id' => null,
        ]);
    }

    public function test_ignored_issue_does_not_reopen_when_it_recurs(): void
    {
        $project = Project::factory()->for(User::factory())->create();
        $this->ingestException($project, 'fp1');
        $issue = Issue::where('fingerprint', 'fp1')->firstOrFail();
        $issue->update(['status' => 'ignored']);

        $this->ingestException($project, 'fp1', 'Broke again');

        $this->assertSame('ignored', $issue->fresh()->status);
    }

    public function test_owner_can_comment_on_an_issue(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->ingestException($project, 'fp1');
        $issue = Issue::where('fingerprint', 'fp1')->firstOrFail();

        $this->actingAs($user)
            ->post("/projects/{$project->id}/issues/{$issue->id}/comments", ['body' => 'Looking into this.'])
            ->assertRedirect();

        $this->assertDatabaseHas('issue_events', [
            'issue_id' => $issue->id,
            'type' => 'comment',
            'body' => 'Looking into this.',
            'user_id' => $user->id,
        ]);
    }

    public function test_issues_index_filters_by_status_and_search(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->ingestException($project, 'fp1', 'Database connection failed');
        $this->ingestException($project, 'fp2', 'Something else');
        Issue::where('fingerprint', 'fp2')->update(['status' => 'resolved']);

        $response = $this->actingAs($user)->get("/projects/{$project->id}/issues?status=open");
        $response->assertInertia(fn ($page) => $page
            ->component('projects/issues/Index')
            ->where('issues.data.0.class', 'RuntimeException')
            ->has('issues.data', 1)
        );

        $response = $this->actingAs($user)->get("/projects/{$project->id}/issues?status=resolved");
        $response->assertInertia(fn ($page) => $page->has('issues.data', 1));

        $response = $this->actingAs($user)->get("/projects/{$project->id}/issues?q=Database");
        $response->assertInertia(fn ($page) => $page->has('issues.data', 1));
    }

    public function test_issues_index_returns_status_counts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->ingestException($project, 'fp1');
        $this->ingestException($project, 'fp2');
        Issue::where('fingerprint', 'fp2')->update(['status' => 'resolved']);

        $this->actingAs($user)
            ->get("/projects/{$project->id}/issues")
            ->assertInertia(fn ($page) => $page
                ->where('counts.open', 1)
                ->where('counts.resolved', 1)
                ->where('counts.ignored', 0)
            );
    }
}
