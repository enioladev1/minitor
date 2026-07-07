<script setup lang="ts">
import { ArrowLeft01Icon as ArrowLeft } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    task as taskShow,
} from '@/actions/App/Http/Controllers/ProjectScheduledTaskController';
import LineChart from '@/components/charts/LineChart.vue';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import SignalDot from '@/components/SignalDot.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { SignalTone } from '@/lib/signal';
import {
    durationTone,
    formatCompactNumber,
    formatDateTime,
    formatDuration,
    toneClasses,
} from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

type PaginatedRuns = {
    data: Array<{
        id: number;
        status: string;
        duration_us: number;
        occurred_at: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string };
    name: string;
    cron: string;
    timezone: string | null;
    repeatSeconds: number;
    schedule: string;
    range: string;
    filters: { status?: string; duration?: string };
    statusSeries: Bucket<'processed' | 'skipped' | 'failed'>[];
    durationSeries: Bucket<'avg_us' | 'p95_us'>[];
    taskStats: {
        processed: number;
        skipped: number;
        failed: number;
        total: number;
        min_us: number;
        max_us: number;
        avg_us: number;
        p95_us: number;
    };
    runs: PaginatedRuns;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

function statusTone(status: string): SignalTone {
    if (status === 'failed') {
        return 'bad';
    }

    if (status === 'skipped') {
        return 'warn';
    }

    return 'good';
}

const statusSeriesSpec = [
    {
        key: 'processed' as const,
        label: 'PROCESSED',
        color: 'var(--color-muted-foreground)',
    },
    {
        key: 'skipped' as const,
        label: 'SKIPPED',
        color: 'var(--color-signal-warn)',
    },
    {
        key: 'failed' as const,
        label: 'FAILED',
        color: 'var(--color-signal-bad)',
    },
];

const durationSeriesSpec = [
    {
        key: 'avg_us' as const,
        label: 'AVG',
        color: 'var(--color-muted-foreground)',
    },
    { key: 'p95_us' as const, label: 'P95', color: 'var(--color-signal-warn)' },
];

const statusOptions = [
    { key: undefined, label: 'View all' },
    {
        key: 'skipped' as const,
        label: 'Skipped',
        count: props.taskStats.skipped,
    },
    { key: 'failed' as const, label: 'Failed', count: props.taskStats.failed },
];

const durationOptions = [
    { key: undefined, label: 'View all' },
    { key: 'avg', label: '≥ AVG' },
    { key: 'p95', label: '≥ P95' },
] as const;

function navigate(overrides: Record<string, unknown>) {
    router.get(
        taskShow(props.project.id, {
            query: {
                name: props.name,
                cron: props.cron,
                timezone: props.timezone ?? '',
                repeat_seconds: props.repeatSeconds,
            },
        }).url,
        { ...props.filters, range: props.range, ...overrides },
        { preserveState: true, replace: true },
    );
}

function setStatusFilter(status: string | undefined) {
    navigate({ status });
}

function setDurationFilter(duration: string | undefined) {
    navigate({ duration });
}

function setRange(range: string) {
    navigate({ range });
}
</script>

<template>
    <Head :title="name" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <Link
            :href="index(project.id)"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <HugeiconsIcon :icon="ArrowLeft" class="size-4" />
            Back to scheduled tasks
        </Link>

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <Heading variant="small" :title="name" />
                <span
                    class="rounded-md border px-2 py-1 text-xs font-medium text-muted-foreground uppercase"
                    >{{ schedule }}</span
                >
            </div>
            <TimeRangeSelector
                :model-value="range"
                @update:model-value="setRange"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">RUNS</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(taskStats.total) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-signal-bad">FAILED</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(taskStats.failed) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">PROCESSED</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(taskStats.processed) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">SKIPPED</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(taskStats.skipped) }}
                            </p>
                        </div>
                    </div>
                </div>
                <StackedBarChart
                    :data="statusSeries"
                    :series="statusSeriesSpec"
                />
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">DURATION</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatDuration(taskStats.min_us) }} &ndash;
                            {{ formatDuration(taskStats.max_us) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-muted-foreground">AVG</p>
                            <p
                                class="font-mono"
                                :class="
                                    toneClasses[durationTone(taskStats.avg_us)]
                                "
                            >
                                {{ formatDuration(taskStats.avg_us) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">P95</p>
                            <p
                                class="font-mono"
                                :class="
                                    toneClasses[durationTone(taskStats.p95_us)]
                                "
                            >
                                {{ formatDuration(taskStats.p95_us) }}
                            </p>
                        </div>
                    </div>
                </div>
                <LineChart
                    :data="durationSeries"
                    :series="durationSeriesSpec"
                    :format-value="formatDuration"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-lg font-semibold">{{ taskStats.total }} Runs</p>
            <div class="flex flex-wrap gap-2">
                <div class="flex gap-1">
                    <Button
                        v-for="option in durationOptions"
                        :key="option.label"
                        size="sm"
                        :variant="
                            filters.duration === option.key
                                ? 'default'
                                : 'outline'
                        "
                        @click="setDurationFilter(option.key)"
                    >
                        {{ option.label }}
                    </Button>
                </div>
                <div class="flex gap-1">
                    <Button
                        v-for="option in statusOptions"
                        :key="option.label"
                        size="sm"
                        :variant="
                            filters.status === option.key
                                ? 'default'
                                : 'outline'
                        "
                        class="gap-1.5"
                        @click="setStatusFilter(option.key)"
                    >
                        {{ option.label }}
                        <span
                            v-if="option.count !== undefined"
                            class="rounded-full bg-background/20 px-1.5 text-xs"
                            >{{ option.count }}</span
                        >
                    </Button>
                </div>
            </div>
        </div>

        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="w-8 py-2 pl-4"></th>
                            <th class="py-2 pr-4 font-normal">Date</th>
                            <th class="py-2 pr-4 font-normal">Status</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Duration
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="run in runs.data"
                            :key="run.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="py-2 pl-4">
                                <SignalDot :tone="statusTone(run.status)" />
                            </td>
                            <td class="py-2 pr-4 text-xs text-muted-foreground">
                                {{ formatDateTime(run.occurred_at) }}
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    class="rounded-md border px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="toneClasses[statusTone(run.status)]"
                                >
                                    {{ run.status }}
                                </span>
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[durationTone(run.duration_us)]
                                "
                            >
                                {{ formatDuration(run.duration_us) }}
                            </td>
                        </tr>
                        <tr v-if="runs.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="4"
                            >
                                No runs match this filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="runs.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in runs.links"
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
