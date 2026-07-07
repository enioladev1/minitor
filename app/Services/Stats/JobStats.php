<?php

namespace App\Services\Stats;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * Bucketed/aggregate job-attempt+duration stats, reused by both the project
 * Overview dashboard and the Jobs pages - mirrors RequestStats but scoped to
 * job_attempts rows (processed/released/failed) instead of requests.
 */
final class JobStats
{
    /**
     * @return list<array{bucket: string, processed: int, released: int, failed: int}>
     */
    public static function attemptSeries(Builder|Relation $query, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $query)
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw("count(*) filter (where status = 'processed') as processed")
            ->selectRaw("count(*) filter (where status = 'released') as released")
            ->selectRaw("count(*) filter (where status = 'failed') as failed")
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'processed' => (int) ($rows->get($bucket)->processed ?? 0),
            'released' => (int) ($rows->get($bucket)->released ?? 0),
            'failed' => (int) ($rows->get($bucket)->failed ?? 0),
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
     * @return array{processed: int, released: int, failed: int, total: int, min_us: int, max_us: int, avg_us: int, p95_us: int}
     */
    public static function summary(Builder|Relation $query): array
    {
        $row = (clone $query)
            ->selectRaw("count(*) filter (where status = 'processed') as processed")
            ->selectRaw("count(*) filter (where status = 'released') as released")
            ->selectRaw("count(*) filter (where status = 'failed') as failed")
            ->selectRaw('count(*) as total')
            ->selectRaw('min(duration_us) as min_us')
            ->selectRaw('max(duration_us) as max_us')
            ->selectRaw('avg(duration_us) as avg_us')
            ->selectRaw('percentile_cont(0.95) within group (order by duration_us) as p95_us')
            ->first();

        return [
            'processed' => (int) ($row->processed ?? 0),
            'released' => (int) ($row->released ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'total' => (int) ($row->total ?? 0),
            'min_us' => (int) ($row->min_us ?? 0),
            'max_us' => (int) ($row->max_us ?? 0),
            'avg_us' => (int) round($row->avg_us ?? 0),
            'p95_us' => (int) round($row->p95_us ?? 0),
        ];
    }
}
