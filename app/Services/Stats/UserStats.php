<?php

namespace App\Services\Stats;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * Bucketed stats for the project-wide Users page: how many distinct
 * authenticated users were active, and the authenticated/guest split of
 * all requests - both computed from the requests table's user_external_id.
 */
final class UserStats
{
    /**
     * @return list<array{bucket: string, authenticated: int}>
     */
    public static function activeUsersSeries(Builder|Relation $requestsQuery, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $requestsQuery)
            ->whereNotNull('user_external_id')
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw('count(distinct user_external_id) as authenticated')
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'authenticated' => (int) ($rows->get($bucket)->authenticated ?? 0),
        ])->all();
    }

    /**
     * @return list<array{bucket: string, authenticated: int, guest: int}>
     */
    public static function requestsSeries(Builder|Relation $requestsQuery, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $requestsQuery)
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw('count(*) filter (where user_external_id is not null) as authenticated')
            ->selectRaw('count(*) filter (where user_external_id is null) as guest')
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'authenticated' => (int) ($rows->get($bucket)->authenticated ?? 0),
            'guest' => (int) ($rows->get($bucket)->guest ?? 0),
        ])->all();
    }

    /**
     * @return array{total: int, authenticated: int, guest: int}
     */
    public static function requestsSummary(Builder|Relation $requestsQuery): array
    {
        $row = (clone $requestsQuery)
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where user_external_id is not null) as authenticated')
            ->selectRaw('count(*) filter (where user_external_id is null) as guest')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'authenticated' => (int) ($row->authenticated ?? 0),
            'guest' => (int) ($row->guest ?? 0),
        ];
    }
}
