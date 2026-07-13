<?php

namespace App\Services\Timeline;

use App\Models\CacheEvent;
use App\Models\MinitorRequest;
use App\Models\Query;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the per-request Timeline waterfall: the 7 execution stages
 * (Laravel\Nightwatch\ExecutionStage) as top-level bars, each positioned by
 * the cumulative duration of the stages before it, with the queries/cache
 * events that happened during that stage nested underneath - mirrors
 * Nightwatch's own request Timeline view.
 */
final class RequestTimelineBuilder
{
    /**
     * @var array<string, string>
     */
    private const STAGE_LABELS = [
        'bootstrap' => 'Bootstrap',
        'before_middleware' => 'Middleware',
        'action' => 'Controller',
        'render' => 'Render',
        'after_middleware' => 'Middleware',
        'sending' => 'Sending',
        'terminating' => 'Terminating',
    ];

    /**
     * @return array{total_us: int, stages: list<array{key: string, label: string, detail: ?string, offset_us: int, duration_us: int, children: list<array{type: string, label: string, detail: string, offset_us: int, duration_us: int}>}>}
     */
    public function build(MinitorRequest $request): array
    {
        [$queries, $cacheEvents] = $this->relatedRecords($request);

        $stageDurations = [
            'bootstrap' => (int) $request->bootstrap_us,
            'before_middleware' => (int) $request->before_middleware_us,
            'action' => (int) $request->action_us,
            'render' => (int) $request->render_us,
            'after_middleware' => (int) $request->after_middleware_us,
            'sending' => (int) $request->sending_us,
            'terminating' => (int) $request->terminating_us,
        ];

        $offset = 0;
        $stages = [];

        foreach ($stageDurations as $key => $duration) {
            $stages[] = [
                'key' => $key,
                'label' => self::STAGE_LABELS[$key],
                'detail' => $key === 'action' ? $request->route_action : null,
                'offset_us' => $offset,
                'duration_us' => $duration,
                'children' => $this->childrenForStage($request, $key, $queries, $cacheEvents),
            ];

            $offset += $duration;
        }

        return [
            'total_us' => $offset,
            'stages' => $stages,
        ];
    }

    /**
     * @return array{0: Collection<int, Query>, 1: Collection<int, CacheEvent>}
     */
    private function relatedRecords(MinitorRequest $request): array
    {
        if ($request->trace_id === null) {
            return [collect(), collect()];
        }

        $queries = Query::query()
            ->where('project_id', $request->project_id)
            ->where('trace_id', $request->trace_id)
            ->get();

        $cacheEvents = CacheEvent::query()
            ->where('project_id', $request->project_id)
            ->where('trace_id', $request->trace_id)
            ->get();

        return [$queries, $cacheEvents];
    }

    /**
     * @param  Collection<int, Query>  $queries
     * @param  Collection<int, CacheEvent>  $cacheEvents
     * @return list<array{type: string, label: string, detail: string, offset_us: int, duration_us: int}>
     */
    private function childrenForStage(MinitorRequest $request, string $stage, Collection $queries, Collection $cacheEvents): array
    {
        $children = [];

        foreach ($queries->where('execution_stage', $stage) as $query) {
            $children[] = [
                'type' => 'query',
                'label' => 'Query',
                'detail' => $query->sql,
                'offset_us' => $this->offsetFrom($request, $query->occurred_at),
                'duration_us' => (int) $query->duration_us,
            ];
        }

        foreach ($cacheEvents->where('execution_stage', $stage) as $event) {
            $children[] = [
                'type' => 'cache_'.str_replace('-', '_', $event->type),
                'label' => 'Cache '.str_replace('-', ' ', $event->type),
                'detail' => (string) $event->key,
                'offset_us' => $this->offsetFrom($request, $event->occurred_at),
                'duration_us' => (int) $event->duration_us,
            ];
        }

        usort($children, fn (array $a, array $b) => $a['offset_us'] <=> $b['offset_us']);

        return $children;
    }

    private function offsetFrom(MinitorRequest $request, CarbonInterface $occurredAt): int
    {
        $offset = (int) round($request->occurred_at->diffInMicroseconds($occurredAt, false));

        return max(0, $offset);
    }
}
