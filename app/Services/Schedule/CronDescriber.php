<?php

namespace App\Services\Schedule;

use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Turns a raw cron expression (plus Laravel's sub-minute repeat_seconds,
 * which cron itself can't express) into the same "Every 5 minutes" / "At
 * 12:00 AM" style summaries Nightwatch shows, and computes the next due
 * run so the dashboard can show a live countdown.
 */
final class CronDescriber
{
    public static function describe(string $cron, int $repeatSeconds = 0): string
    {
        if ($repeatSeconds > 0) {
            return $repeatSeconds === 1 ? 'Every second' : "Every {$repeatSeconds} seconds";
        }

        $parts = preg_split('/\s+/', trim($cron));

        if (count($parts) !== 5) {
            return $cron;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        if ($minute === '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every minute';
        }

        if (preg_match('/^\*\/(\d+)$/', $minute, $m) && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            $n = (int) $m[1];

            return $n === 1 ? 'Every minute' : "Every {$n} minutes";
        }

        if ($minute === '0' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every hour';
        }

        if ($minute === '0' && preg_match('/^\*\/(\d+)$/', $hour, $m) && $day === '*' && $month === '*' && $weekday === '*') {
            $n = (int) $m[1];

            return $n === 1 ? 'Every hour' : "Every {$n} hours";
        }

        if (is_numeric($minute) && is_numeric($hour) && $day === '*' && $month === '*' && $weekday === '*') {
            return 'At '.Carbon::createFromTime((int) $hour, (int) $minute)->format('g:i A');
        }

        if (is_numeric($minute) && is_numeric($hour) && $day === '*' && $month === '*' && is_numeric($weekday)) {
            $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            return 'At '.Carbon::createFromTime((int) $hour, (int) $minute)->format('g:i A').' on '.($names[(int) $weekday] ?? $weekday);
        }

        if (is_numeric($minute) && is_numeric($hour) && is_numeric($day) && $month === '*' && $weekday === '*') {
            return 'On day '.$day.' at '.Carbon::createFromTime((int) $hour, (int) $minute)->format('g:i A');
        }

        return $cron;
    }

    public static function nextRun(string $cron, ?string $timezone, int $repeatSeconds = 0): ?CarbonInterface
    {
        if ($repeatSeconds > 0) {
            return now()->addSeconds($repeatSeconds);
        }

        try {
            $next = CronExpression::factory($cron)->getNextRunDate('now', 0, false, $timezone ?: 'UTC');

            return Carbon::instance($next);
        } catch (Throwable) {
            return null;
        }
    }
}
