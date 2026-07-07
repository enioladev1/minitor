<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingest_rejects_missing_or_invalid_token(): void
    {
        $this->postJson('/api/ingest', ['records' => []])->assertStatus(401);

        $this->postJson('/api/ingest', ['records' => []], [
            'Authorization' => 'Bearer not-a-real-token',
        ])->assertStatus(401);
    }

    public function test_ingest_persists_all_four_mvp_record_types(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $records = [
            [
                'v' => 1, 't' => 'request', 'timestamp' => 1750000000.0,
                'method' => 'GET', 'url' => 'https://example.test/hello',
                'route_name' => 'hello', 'route_path' => '/hello', 'route_action' => 'HelloController@show',
                'ip' => '127.0.0.1', 'status_code' => 200, 'duration' => 42_000,
                'request_size' => 0, 'response_size' => 128, 'trace_id' => 'trace-1',
            ],
            [
                'v' => 1, 't' => 'query', 'timestamp' => 1750000000.0,
                'sql' => 'select * from users', 'file' => 'app/Models/User.php', 'line' => 10,
                'duration' => 1_500, 'connection' => 'pgsql', 'connection_type' => 'write',
            ],
            [
                'v' => 3, 't' => 'exception', 'timestamp' => 1750000000.0,
                '_group' => 'abc1234', 'class' => 'RuntimeException', 'file' => 'app/Foo.php', 'line' => 5,
                'message' => 'Something broke', 'code' => 0, 'handled' => false,
                'trace' => '[]', 'php_version' => '8.3.0', 'laravel_version' => '12.0.0',
            ],
            [
                'v' => 1, 't' => 'exception', 'timestamp' => 1750000001.0,
                '_group' => 'abc1234', 'class' => 'RuntimeException', 'file' => 'app/Foo.php', 'line' => 5,
                'message' => 'Something broke again', 'code' => 0, 'handled' => false,
            ],
            [
                'v' => 1, 't' => 'queued-job', 'timestamp' => 1750000000.0,
                'job_id' => 'job-1', 'name' => 'App\\Jobs\\SendEmail', 'connection' => 'redis',
                'queue' => 'default', 'duration' => 250_000,
            ],
        ];

        $response = $this->postJson('/api/ingest', ['records' => $records], [
            'Authorization' => 'Bearer '.$project->token,
        ]);

        $response->assertOk()->assertJson(['status' => 'ok', 'accepted' => 5]);

        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseHas('requests', ['route_path' => '/hello', 'status_code' => 200]);

        $this->assertDatabaseCount('queries', 1);
        $this->assertDatabaseHas('queries', ['connection' => 'pgsql', 'duration_us' => 1500]);

        // Two exception records with the same `_group` fingerprint collapse into one issue.
        $this->assertDatabaseCount('issues', 1);
        $this->assertDatabaseHas('issues', ['fingerprint' => 'abc1234', 'occurrences_count' => 2]);
        $this->assertDatabaseCount('exceptions', 2);

        $this->assertDatabaseCount('queued_jobs', 1);
        $this->assertDatabaseHas('queued_jobs', ['name' => 'App\\Jobs\\SendEmail', 'queue' => 'default']);

        $this->assertNotNull($project->fresh()->last_seen_at);
    }

    public function test_ingest_persists_all_remaining_record_types(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $records = [
            [
                'v' => 1, 't' => 'job-attempt', 'timestamp' => 1750000000.0,
                'job_id' => 'job-1', 'attempt_id' => 'attempt-1', 'attempt' => 2,
                'name' => 'App\\Jobs\\SendEmail', 'connection' => 'redis', 'queue' => 'default',
                'status' => 'processed', 'duration' => 88_000,
            ],
            [
                'v' => 1, 't' => 'command', 'timestamp' => 1750000000.0,
                'class' => 'App\\Console\\Commands\\Cleanup', 'name' => 'app:cleanup',
                'command' => 'php artisan app:cleanup', 'exit_code' => 0, 'duration' => 500_000,
            ],
            [
                'v' => 1, 't' => 'scheduled-task', 'timestamp' => 1750000000.0,
                'name' => 'app:cleanup', 'cron' => '0 * * * *', 'timezone' => 'UTC',
                'status' => 'processed', 'duration' => 120_000,
            ],
            [
                'v' => 1, 't' => 'cache-event', 'timestamp' => 1750000000.0,
                'store' => 'redis', 'key' => 'widgets:1', 'type' => 'hit', 'duration' => 100, 'ttl' => 60,
            ],
            [
                'v' => 1, 't' => 'outgoing-request', 'timestamp' => 1750000000.0,
                'host' => 'api.example.com', 'method' => 'GET', 'url' => 'https://api.example.com/widgets',
                'duration' => 300_000, 'request_size' => 0, 'response_size' => 512, 'status_code' => 200,
            ],
            [
                'v' => 1, 't' => 'log', 'timestamp' => 1750000000.0,
                'level' => 'error', 'message' => 'Something went wrong', 'context' => '{}', 'extra' => '{}',
            ],
            [
                'v' => 1, 't' => 'user', 'timestamp' => 1750000000.0,
                'id' => '42', 'name' => 'Jane Doe', 'username' => 'jane',
            ],
        ];

        $response = $this->postJson('/api/ingest', ['records' => $records], [
            'Authorization' => 'Bearer '.$project->token,
        ]);

        $response->assertOk()->assertJson(['status' => 'ok', 'accepted' => 7]);

        $this->assertDatabaseCount('job_attempts', 1);
        $this->assertDatabaseHas('job_attempts', ['status' => 'processed', 'attempt' => 2]);

        $this->assertDatabaseCount('commands', 1);
        $this->assertDatabaseHas('commands', ['name' => 'app:cleanup', 'exit_code' => 0]);

        $this->assertDatabaseCount('scheduled_tasks', 1);
        $this->assertDatabaseHas('scheduled_tasks', ['name' => 'app:cleanup', 'status' => 'processed']);

        $this->assertDatabaseCount('cache_events', 1);
        $this->assertDatabaseHas('cache_events', ['key' => 'widgets:1', 'type' => 'hit']);

        $this->assertDatabaseCount('outgoing_requests', 1);
        $this->assertDatabaseHas('outgoing_requests', ['host' => 'api.example.com', 'status_code' => 200]);

        $this->assertDatabaseCount('logs', 1);
        $this->assertDatabaseHas('logs', ['level' => 'error']);

        $this->assertDatabaseCount('monitored_users', 1);
        $this->assertDatabaseHas('monitored_users', ['external_id' => '42', 'name' => 'Jane Doe']);
    }

    public function test_ingest_ignores_unknown_record_types(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $this->postJson('/api/ingest', [
            'records' => [['t' => 'some-future-record-type', 'v' => 1]],
        ], ['Authorization' => 'Bearer '.$project->token])->assertOk();

        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('queries', 0);
        $this->assertDatabaseCount('exceptions', 0);
        $this->assertDatabaseCount('queued_jobs', 0);
    }

    public function test_owner_can_view_every_project_dashboard_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('/projects')
            ->assertInertia(fn (Assert $page) => $page->component('projects/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}")
            ->assertInertia(fn (Assert $page) => $page->component('projects/Overview'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/requests")
            ->assertInertia(fn (Assert $page) => $page->component('projects/requests/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/queries")
            ->assertInertia(fn (Assert $page) => $page->component('projects/queries/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/issues")
            ->assertInertia(fn (Assert $page) => $page->component('projects/issues/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/jobs")
            ->assertInertia(fn (Assert $page) => $page->component('projects/jobs/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/commands")
            ->assertInertia(fn (Assert $page) => $page->component('projects/commands/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/scheduled-tasks")
            ->assertInertia(fn (Assert $page) => $page->component('projects/scheduled-tasks/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/exceptions")
            ->assertInertia(fn (Assert $page) => $page->component('projects/exceptions/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/cache")
            ->assertInertia(fn (Assert $page) => $page->component('projects/cache-events/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/outgoing-requests")
            ->assertInertia(fn (Assert $page) => $page->component('projects/outgoing-requests/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/users")
            ->assertInertia(fn (Assert $page) => $page->component('projects/users/Index'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/logs")
            ->assertInertia(fn (Assert $page) => $page->component('projects/logs/Index'));
    }

    public function test_non_owner_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get("/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_owner_can_regenerate_project_token(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $oldToken = $project->token;
        $oldLocalSecret = $project->local_secret;

        $this->actingAs($user)->post("/projects/{$project->id}/regenerate-token")->assertRedirect();

        $fresh = $project->fresh();
        $this->assertNotSame($oldToken, $fresh->token);
        $this->assertNotSame($oldLocalSecret, $fresh->local_secret);
    }

    public function test_project_gets_a_local_secret_generated_alongside_its_token(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $this->assertNotEmpty($project->token);
        $this->assertNotEmpty($project->local_secret);
        $this->assertNotSame($project->token, $project->local_secret);
    }
}
