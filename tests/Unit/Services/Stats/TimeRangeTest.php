<?php

namespace Tests\Unit\Services\Stats;

use App\Services\Stats\TimeRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Tests\TestCase;

class TimeRangeTest extends TestCase
{
    public function test_resolve_uses_the_explicit_query_param_when_present(): void
    {
        $request = Request::create('/projects/1/jobs', 'GET', ['range' => '30d']);

        $this->assertSame('30d', TimeRange::resolve($request));
    }

    public function test_resolve_falls_back_to_the_remembered_cookie_when_no_query_param_is_given(): void
    {
        $request = Request::create('/projects/1/commands', 'GET');
        $request->cookies->set('minitor_range', '30d');

        $this->assertSame('30d', TimeRange::resolve($request));
    }

    public function test_resolve_ignores_an_invalid_remembered_cookie(): void
    {
        $request = Request::create('/projects/1/commands', 'GET');
        $request->cookies->set('minitor_range', 'not-a-real-range');

        $this->assertSame('1h', TimeRange::resolve($request));
    }

    public function test_resolve_defaults_to_1h_with_nothing_set(): void
    {
        $request = Request::create('/projects/1/commands', 'GET');

        $this->assertSame('1h', TimeRange::resolve($request));
    }

    public function test_resolve_queues_a_cookie_when_an_explicit_range_is_given(): void
    {
        Cookie::shouldReceive('queue')
            ->once()
            ->with('minitor_range', '24h', 60 * 24 * 365);

        $request = Request::create('/projects/1/jobs', 'GET', ['range' => '24h']);

        TimeRange::resolve($request);
    }
}
