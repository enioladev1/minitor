<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import {
    durationTone,
    formatDuration,
    statusTone,
    toneDotClasses,
    type SignalTone,
} from '@/lib/signal';
import { requestPath } from '@/lib/utils';

type TimelineChild = {
    type: string;
    label: string;
    detail: string;
    offset_us: number;
    duration_us: number;
};

type TimelineStage = {
    key: string;
    label: string;
    detail: string | null;
    offset_us: number;
    duration_us: number;
    children: TimelineChild[];
};

const props = defineProps<{
    requestRecord: {
        method: string;
        route_path: string | null;
        url: string;
        status_code: number;
    };
    timeline: { total_us: number; stages: TimelineStage[] };
}>();

type Row = {
    id: string;
    depth: number;
    label: string;
    detail: string | null;
    offsetUs: number;
    durationUs: number;
    tone: SignalTone | 'neutral';
};

/**
 * Cache hits/writes read as healthy, misses/deletes draw the eye, failures
 * are bad - queries fall back to the same duration-based tone used
 * everywhere else a query duration shows up in MINITOR. Phases themselves
 * are structural, not a health signal, so they stay neutral.
 */
function childTone(child: TimelineChild): SignalTone {
    if (child.type.endsWith('failure')) {
        return 'bad';
    }

    if (child.type === 'cache_hit' || child.type === 'cache_write') {
        return 'good';
    }

    if (child.type === 'cache_miss' || child.type === 'cache_delete') {
        return 'warn';
    }

    return durationTone(child.duration_us);
}

const rows = computed<Row[]>(() => {
    const requestTone = statusTone(props.requestRecord.status_code);

    const list: Row[] = [
        {
            id: 'request',
            depth: 0,
            label: props.requestRecord.method,
            detail:
                props.requestRecord.route_path ||
                requestPath(props.requestRecord.url),
            offsetUs: 0,
            durationUs: props.timeline.total_us,
            tone: requestTone,
        },
    ];

    for (const stage of props.timeline.stages) {
        if (stage.duration_us === 0 && stage.children.length === 0) {
            continue;
        }

        list.push({
            id: stage.key,
            depth: 1,
            label: stage.label,
            detail: stage.detail,
            offsetUs: stage.offset_us,
            durationUs: stage.duration_us,
            tone: 'neutral',
        });

        stage.children.forEach((child, index) => {
            list.push({
                id: `${stage.key}-${child.type}-${index}`,
                depth: 2,
                label: child.label,
                detail: child.detail,
                offsetUs: child.offset_us,
                durationUs: child.duration_us,
                tone: childTone(child),
            });
        });
    }

    return list;
});

const barToneClasses: Record<SignalTone | 'neutral', string> = {
    ...toneDotClasses,
    neutral: 'bg-foreground/25',
};

function barStyle(row: Row): { left: string; width: string } {
    if (props.timeline.total_us <= 0) {
        return { left: '0%', width: '0%' };
    }

    const left = (row.offsetUs / props.timeline.total_us) * 100;
    const width = Math.max((row.durationUs / props.timeline.total_us) * 100, 0.5);

    return { left: `${left}%`, width: `${width}%` };
}

function tooltip(row: Row): string {
    return row.detail
        ? `${row.label} · ${formatDuration(row.durationUs)} · ${row.detail}`
        : `${row.label} · ${formatDuration(row.durationUs)}`;
}

const TICK_STEPS_MS = [1, 2, 5, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10_000, 25_000];

const totalMs = computed(() => props.timeline.total_us / 1000);

const ticks = computed(() => {
    if (totalMs.value <= 0) {
        return [0];
    }

    const step =
        TICK_STEPS_MS.find((candidate) => totalMs.value / candidate <= 6) ??
        TICK_STEPS_MS[TICK_STEPS_MS.length - 1];

    const values: number[] = [];

    for (let t = 0; t < totalMs.value; t += step) {
        values.push(t);
    }

    values.push(Math.ceil(totalMs.value));

    return values;
});
</script>

<template>
    <Card>
        <CardContent class="p-4">
            <p class="mb-4 text-sm font-semibold">Timeline</p>

            <div class="overflow-x-auto">
                <div class="min-w-[720px]">
                    <!-- Ruler -->
                    <div class="flex">
                        <div class="w-64 shrink-0"></div>
                        <div class="relative h-5 flex-1">
                            <span
                                v-for="ms in ticks"
                                :key="ms"
                                class="absolute -translate-x-1/2 font-mono text-[10px] text-muted-foreground"
                                :style="{ left: `${(ms / totalMs) * 100}%` }"
                            >
                                {{ Math.round(ms) }}ms
                            </span>
                        </div>
                    </div>

                    <div class="divide-y divide-border/60 rounded-md border">
                        <div
                            v-for="row in rows"
                            :key="row.id"
                            class="flex items-center hover:bg-muted/40"
                        >
                            <div
                                class="flex w-64 shrink-0 items-center gap-1.5 py-1.5 pr-2 font-mono text-xs"
                                :style="{ paddingLeft: `${0.75 + row.depth * 0.9}rem` }"
                            >
                                <span
                                    class="size-1.5 shrink-0 rounded-full"
                                    :class="barToneClasses[row.tone]"
                                />
                                <span
                                    class="truncate"
                                    :class="row.depth === 0 ? 'font-semibold' : 'text-muted-foreground'"
                                >
                                    {{ row.label }}
                                    <span v-if="row.detail" class="text-foreground/60">
                                        · {{ row.detail }}
                                    </span>
                                </span>
                                <span class="ml-auto shrink-0 pl-2 text-muted-foreground">
                                    {{ formatDuration(row.durationUs) }}
                                </span>
                            </div>
                            <div class="relative h-7 flex-1 py-1.5 pr-3">
                                <div
                                    class="absolute top-1/2 h-1.5 -translate-y-1/2 rounded-full opacity-80"
                                    :class="barToneClasses[row.tone]"
                                    :style="barStyle(row)"
                                    :title="tooltip(row)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
