<?php

namespace App\Services\Stats;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;

/**
 * Maps a dashboard time-range key to a lookback window and a bucket size,
 * and generates the full list of bucket start times so charts always show
 * a complete, evenly-spaced x-axis (rather than only the buckets that
 * happen to have data).
 */
final class TimeRange
{
    private const RANGES = [
        '1h' => ['hours' => 1, 'bucket_seconds' => 300],      // 5-minute buckets
        '24h' => ['hours' => 24, 'bucket_seconds' => 3_600],     // 1-hour buckets
        '7d' => ['hours' => 24 * 7, 'bucket_seconds' => 21_600],    // 6-hour buckets
        '14d' => ['hours' => 24 * 14, 'bucket_seconds' => 43_200],    // 12-hour buckets
        '30d' => ['hours' => 24 * 30, 'bucket_seconds' => 86_400],    // 1-day buckets
    ];

    private const COOKIE = 'minitor_range';

    public readonly CarbonInterface $since;

    public readonly int $bucketSeconds;

    public function __construct(public readonly string $key)
    {
        $config = self::RANGES[$key] ?? self::RANGES['24h'];

        $this->since = now()->subHours($config['hours']);
        $this->bucketSeconds = $config['bucket_seconds'];
    }

    public static function isValid(string $key): bool
    {
        return isset(self::RANGES[$key]);
    }

    /**
     * Resolves the range key to use for this request: an explicit `?range=`
     * query param wins (and is remembered for a year via cookie so every
     * other page defaults to it too), otherwise falls back to the
     * previously remembered range, otherwise '1h'.
     */
    public static function resolve(Request $request): string
    {
        $requested = $request->string('range')->toString();

        if (self::isValid($requested)) {
            Cookie::queue(self::COOKIE, $requested, 60 * 24 * 365);

            return $requested;
        }

        $remembered = $request->cookie(self::COOKIE);
        $remembered = is_string($remembered) ? $remembered : '';

        return self::isValid($remembered) ? $remembered : '1h';
    }

    /**
     * @return list<string> bucket start times, formatted as ISO 8601, ascending
     */
    public function buckets(): array
    {
        $buckets = [];
        $cursor = (int) floor($this->since->timestamp / $this->bucketSeconds) * $this->bucketSeconds;
        $end = (int) now()->timestamp;

        while ($cursor <= $end) {
            $buckets[] = Carbon::createFromTimestamp($cursor)->toIso8601String();
            $cursor += $this->bucketSeconds;
        }

        return $buckets;
    }

    /**
     * Raw SQL expression that truncates a timestamp column to this range's
     * bucket size, for use in selectRaw()/groupBy(). Postgres-only (relies
     * on extract(epoch from ...) + to_timestamp).
     */
    public function bucketExpression(string $column): string
    {
        return "to_timestamp(floor(extract(epoch from {$column}) / {$this->bucketSeconds}) * {$this->bucketSeconds})";
    }
}
