<?php

namespace App\Services\Stats;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * Bucketed/aggregate cache-event stats, mirroring RequestStats - a cache
 * event's `type` is one of hit/miss/write/delete/write-failure/delete-failure,
 * so this tracks both the event mix and the failure mix separately (they're
 * shown as two distinct charts, same as the real Nightwatch Cache page).
 */
final class CacheStats
{
    /**
     * @return list<array{bucket: string, hits: int, misses: int, writes: int, deletes: int}>
     */
    public static function eventSeries(Builder|Relation $query, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $query)
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw("count(*) filter (where type = 'hit') as hits")
            ->selectRaw("count(*) filter (where type = 'miss') as misses")
            ->selectRaw("count(*) filter (where type = 'write') as writes")
            ->selectRaw("count(*) filter (where type = 'delete') as deletes")
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'hits' => (int) ($rows->get($bucket)->hits ?? 0),
            'misses' => (int) ($rows->get($bucket)->misses ?? 0),
            'writes' => (int) ($rows->get($bucket)->writes ?? 0),
            'deletes' => (int) ($rows->get($bucket)->deletes ?? 0),
        ])->all();
    }

    /**
     * @return list<array{bucket: string, write_failures: int, delete_failures: int}>
     */
    public static function failureSeries(Builder|Relation $query, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $query)
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw("count(*) filter (where type = 'write-failure') as write_failures")
            ->selectRaw("count(*) filter (where type = 'delete-failure') as delete_failures")
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'write_failures' => (int) ($rows->get($bucket)->write_failures ?? 0),
            'delete_failures' => (int) ($rows->get($bucket)->delete_failures ?? 0),
        ])->all();
    }

    /**
     * @return array{total: int, hits: int, misses: int, writes: int, deletes: int, write_failures: int, delete_failures: int, failures: int}
     */
    public static function summary(Builder|Relation $query): array
    {
        $row = (clone $query)
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where type = 'hit') as hits")
            ->selectRaw("count(*) filter (where type = 'miss') as misses")
            ->selectRaw("count(*) filter (where type = 'write') as writes")
            ->selectRaw("count(*) filter (where type = 'delete') as deletes")
            ->selectRaw("count(*) filter (where type = 'write-failure') as write_failures")
            ->selectRaw("count(*) filter (where type = 'delete-failure') as delete_failures")
            ->first();

        $writeFailures = (int) ($row->write_failures ?? 0);
        $deleteFailures = (int) ($row->delete_failures ?? 0);

        return [
            'total' => (int) ($row->total ?? 0),
            'hits' => (int) ($row->hits ?? 0),
            'misses' => (int) ($row->misses ?? 0),
            'writes' => (int) ($row->writes ?? 0),
            'deletes' => (int) ($row->deletes ?? 0),
            'write_failures' => $writeFailures,
            'delete_failures' => $deleteFailures,
            'failures' => $writeFailures + $deleteFailures,
        ];
    }
}
