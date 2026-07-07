<?php

namespace App\Services\Stats;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * Bucketed/aggregate query-call+duration stats, mirroring RequestStats —
 * queries have no success/failure state, just call volume and timing.
 */
final class QueryStats
{
    /**
     * @return list<array{bucket: string, calls: int}>
     */
    public static function callSeries(Builder|Relation $query, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $query)
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw('count(*) as calls')
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'calls' => (int) ($rows->get($bucket)->calls ?? 0),
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
     * @return array{total: int, min_us: int, max_us: int, avg_us: int, p95_us: int, sum_us: int}
     */
    public static function summary(Builder|Relation $query): array
    {
        $row = (clone $query)
            ->selectRaw('count(*) as total')
            ->selectRaw('min(duration_us) as min_us')
            ->selectRaw('max(duration_us) as max_us')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->selectRaw('sum(duration_us) as sum_us')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'min_us' => (int) ($row->min_us ?? 0),
            'max_us' => (int) ($row->max_us ?? 0),
            'avg_us' => (int) round($row->avg_us ?? 0),
            'p95_us' => (int) round($row->p95_us ?? 0),
            'sum_us' => (int) ($row->sum_us ?? 0),
        ];
    }
}
