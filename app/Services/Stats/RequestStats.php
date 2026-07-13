<?php

namespace App\Services\Stats;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * Bucketed/aggregate request+duration stats, reused by both the project
 * Overview dashboard and the Requests pages - the only difference between
 * callers is which query they hand in (project-wide vs scoped to a route).
 *
 * Callers hand in either a plain Builder or an Eloquent relation (e.g.
 * `$project->requests()->where(...)`), since Laravel's relation proxy
 * returns the relation itself, not the underlying builder, once chained.
 */
final class RequestStats
{
    /**
     * Synthetic method/path pair every request with no matched Laravel
     * route collapses into when grouped - mirrors Nightwatch's own
     * "Unmatched Route" bucket instead of grouping each raw URL on its own.
     */
    public const UNMATCHED_METHOD = 'ANY';

    public const UNMATCHED_PATH = 'Unmatched Route';

    /**
     * Raw SQL expression yielding a route's grouping path: the matched
     * route's path, or the shared "Unmatched Route" sentinel when the
     * request never matched a route (route_path is null/empty).
     */
    public static function routePathExpr(): string
    {
        return "case when coalesce(route_path, '') = '' then '".self::UNMATCHED_PATH."' else route_path end";
    }

    /**
     * Same idea as routePathExpr(), but for method - unmatched requests
     * collapse regardless of their actual HTTP method.
     */
    public static function routeMethodExpr(): string
    {
        return "case when coalesce(route_path, '') = '' then '".self::UNMATCHED_METHOD."' else method end";
    }

    /**
     * Scopes a requests query to "all", "authenticated", "guest", or one
     * specific user's external id - the same filter used across every
     * Requests page's charts and tables.
     */
    public static function applyUserFilter(Builder|Relation $query, ?string $userFilter): Builder|Relation
    {
        return match (true) {
            $userFilter === null || $userFilter === '' || $userFilter === 'all' => $query,
            $userFilter === 'authenticated' => $query->whereNotNull('user_external_id'),
            $userFilter === 'guest' => $query->whereNull('user_external_id'),
            default => $query->where('user_external_id', $userFilter),
        };
    }

    /**
     * @return list<array{bucket: string, success: int, client_error: int, server_error: int}>
     */
    public static function series(Builder|Relation $query, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $query)
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw('count(*) filter (where status_code < 400) as success')
            ->selectRaw('count(*) filter (where status_code >= 400 and status_code < 500) as client_error')
            ->selectRaw('count(*) filter (where status_code >= 500) as server_error')
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'success' => (int) ($rows->get($bucket)->success ?? 0),
            'client_error' => (int) ($rows->get($bucket)->client_error ?? 0),
            'server_error' => (int) ($rows->get($bucket)->server_error ?? 0),
        ])->all();
    }

    /**
     * @return list<array{bucket: string, avg_us: int, p95_us: int}>
     */
    public static function durationSeries(Builder|Relation $query, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $query)
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'avg_us' => (int) round($rows->get($bucket)->avg_us ?? 0),
            'p95_us' => (int) round($rows->get($bucket)->p95_us ?? 0),
        ])->all();
    }

    /**
     * @return array{total: int, success: int, client_error: int, server_error: int, min_us: int, max_us: int, avg_us: int, p95_us: int}
     */
    public static function summary(Builder|Relation $query): array
    {
        $row = (clone $query)
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where status_code < 400) as success')
            ->selectRaw('count(*) filter (where status_code >= 400 and status_code < 500) as client_error')
            ->selectRaw('count(*) filter (where status_code >= 500) as server_error')
            ->selectRaw('min(duration_us) as min_us')
            ->selectRaw('max(duration_us) as max_us')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'success' => (int) ($row->success ?? 0),
            'client_error' => (int) ($row->client_error ?? 0),
            'server_error' => (int) ($row->server_error ?? 0),
            'min_us' => (int) ($row->min_us ?? 0),
            'max_us' => (int) ($row->max_us ?? 0),
            'avg_us' => (int) round($row->avg_us ?? 0),
            'p95_us' => (int) round($row->p95_us ?? 0),
        ];
    }
}
