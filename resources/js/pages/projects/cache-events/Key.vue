<script setup lang="ts">
import { ArrowLeft01Icon as ArrowLeft } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    index,
    key as keyShow,
} from '@/actions/App/Http/Controllers/ProjectCacheEventController';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import SignalDot from '@/components/SignalDot.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { SignalTone } from '@/lib/signal';
import {
    formatCompactNumber,
    formatDateTime,
    formatDuration,
} from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

type PaginatedEvents = {
    data: Array<{
        id: number;
        type: string;
        duration_us: number;
        ttl: number;
        occurred_at: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string };
    store: string;
    cacheKey: string;
    range: string;
    filters: { type?: string };
    eventSeries: Bucket<'hits' | 'misses' | 'writes' | 'deletes'>[];
    failureSeries: Bucket<'write_failures' | 'delete_failures'>[];
    cacheStats: {
        total: number;
        hits: number;
        misses: number;
        writes: number;
        deletes: number;
        write_failures: number;
        delete_failures: number;
        failures: number;
    };
    events: PaginatedEvents;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const eventSeriesSpec = [
    {
        key: 'deletes' as const,
        label: 'DELETES',
        color: 'var(--color-signal-bad)',
    },
    {
        key: 'hits' as const,
        label: 'HITS',
        color: 'var(--color-muted-foreground)',
    },
    {
        key: 'misses' as const,
        label: 'MISSES',
        color: 'var(--color-signal-warn)',
    },
    { key: 'writes' as const, label: 'WRITES', color: '#60a5fa' },
];

const failureSeriesSpec = [
    {
        key: 'delete_failures' as const,
        label: 'DELETE',
        color: 'var(--color-signal-bad)',
    },
    {
        key: 'write_failures' as const,
        label: 'WRITE',
        color: 'var(--color-signal-warn)',
    },
];

const typeOptions = [
    { key: undefined, label: 'View all' },
    { key: 'hit', label: 'Hits', count: props.cacheStats.hits },
    { key: 'miss', label: 'Misses', count: props.cacheStats.misses },
    { key: 'write', label: 'Writes', count: props.cacheStats.writes },
    { key: 'delete', label: 'Deletes', count: props.cacheStats.deletes },
];

function typeTone(type: string): SignalTone {
    if (type === 'hit') {
        return 'good';
    }

    if (type.endsWith('failure')) {
        return 'bad';
    }

    if (type === 'miss') {
        return 'warn';
    }

    return 'good';
}

function navigate(overrides: Record<string, unknown>) {
    router.get(
        keyShow(props.project.id, {
            query: { store: props.store, key: props.cacheKey },
        }).url,
        { ...props.filters, range: props.range, ...overrides },
        { preserveState: true, replace: true },
    );
}

function setTypeFilter(type: string | undefined) {
    navigate({ type });
}

function setRange(range: string) {
    navigate({ range });
}
</script>

<template>
    <Head :title="cacheKey" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <Link
            :href="index(project.id)"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <HugeiconsIcon :icon="ArrowLeft" class="size-4" />
            Back to cache
        </Link>

        <div class="flex items-center justify-between gap-4">
            <Heading
                variant="small"
                :description="store"
                :title="cacheKey"
                class="min-w-0"
            />
            <TimeRangeSelector
                :model-value="range"
                @update:model-value="setRange"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">EVENTS</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(cacheStats.total) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-signal-bad">DELETES</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(cacheStats.deletes) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">HITS</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(cacheStats.hits) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">MISSES</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(cacheStats.misses) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">WRITES</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(cacheStats.writes) }}
                            </p>
                        </div>
                    </div>
                </div>
                <StackedBarChart
                    :data="eventSeries"
                    :series="eventSeriesSpec"
                />
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">FAILURES</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(cacheStats.failures) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-signal-bad">DELETE</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(
                                        cacheStats.delete_failures,
                                    )
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">WRITE</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(
                                        cacheStats.write_failures,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <StackedBarChart
                    :data="failureSeries"
                    :series="failureSeriesSpec"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-lg font-semibold">{{ cacheStats.total }} Events</p>
            <div class="flex flex-wrap gap-1">
                <Button
                    v-for="option in typeOptions"
                    :key="option.label"
                    size="sm"
                    :variant="
                        filters.type === option.key ? 'default' : 'outline'
                    "
                    class="gap-1.5"
                    @click="setTypeFilter(option.key)"
                >
                    {{ option.label }}
                    <span
                        v-if="option.count !== undefined && option.count > 0"
                        class="rounded-full bg-background/20 px-1.5 text-xs"
                        >{{ option.count }}</span
                    >
                </Button>
            </div>
        </div>

        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="w-8 py-2 pl-4"></th>
                            <th class="py-2 pr-4 font-normal">Date</th>
                            <th class="py-2 pr-4 font-normal">Type</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                TTL
                            </th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Duration
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="event in events.data"
                            :key="event.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="py-2 pl-4">
                                <SignalDot :tone="typeTone(event.type)" />
                            </td>
                            <td class="py-2 pr-4 text-xs text-muted-foreground">
                                {{ formatDateTime(event.occurred_at) }}
                            </td>
                            <td class="py-2 pr-4 text-xs capitalize">
                                {{ event.type }}
                            </td>
                            <td class="py-2 pr-4 text-right font-mono text-xs">
                                {{ event.ttl > 0 ? `${event.ttl}s` : '—' }}
                            </td>
                            <td class="py-2 pr-4 text-right font-mono text-xs">
                                {{ formatDuration(event.duration_us) }}
                            </td>
                        </tr>
                        <tr v-if="events.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="5"
                            >
                                No events match this filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="events.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in events.links"
                :key="link.label"
                :href="link.url ?? ''"
                :class="
                    cn(
                        'rounded-md px-2.5 py-1 text-xs',
                        link.active
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:bg-muted',
                        !link.url && 'pointer-events-none opacity-40',
                    )
                "
                >{{ paginationLabel(link.label) }}</Link
            >
        </div>
    </div>
</template>
