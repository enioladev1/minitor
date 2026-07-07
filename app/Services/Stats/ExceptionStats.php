<?php

namespace App\Services\Stats;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * Bucketed/aggregate exception-occurrence stats, mirroring RequestStats —
 * exceptions have no duration, so this only tracks handled/unhandled counts.
 */
final class ExceptionStats
{
    /**
     * @return list<array{bucket: string, handled: int, unhandled: int}>
     */
    public static function occurrenceSeries(Builder|Relation $query, TimeRange $range): array
    {
        $bucketExpr = $range->bucketExpression('occurred_at');

        $rows = (clone $query)
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw('count(*) filter (where handled) as handled')
            ->selectRaw('count(*) filter (where not handled) as unhandled')
            ->groupByRaw($bucketExpr)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket)->toIso8601String());

        return collect($range->buckets())->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'handled' => (int) ($rows->get($bucket)->handled ?? 0),
            'unhandled' => (int) ($rows->get($bucket)->unhandled ?? 0),
        ])->all();
    }

    /**
     * @return array{handled: int, unhandled: int, total: int}
     */
    public static function summary(Builder|Relation $query): array
    {
        $row = (clone $query)
            ->selectRaw('count(*) filter (where handled) as handled')
            ->selectRaw('count(*) filter (where not handled) as unhandled')
            ->selectRaw('count(*) as total')
            ->first();

        return [
            'handled' => (int) ($row->handled ?? 0),
            'unhandled' => (int) ($row->unhandled ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }
}
