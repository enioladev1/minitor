<script setup lang="ts">
import { ArrowLeft01Icon as ArrowLeft } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    job as jobShow,
} from '@/actions/App/Http/Controllers/ProjectJobController';
import LineChart from '@/components/charts/LineChart.vue';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import SignalDot from '@/components/SignalDot.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import UserFilterDropdown from '@/components/UserFilterDropdown.vue';
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

type PaginatedAttempts = {
    data: Array<{
        id: number;
        name: string;
        connection: string | null;
        queue: string | null;
        attempt: number;
        status: string;
        duration_us: number;
        occurred_at: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string };
    name: string;
    range: string;
    filters: { status?: string; duration?: string; user?: string };
    attemptsSeries: Bucket<'processed' | 'released' | 'failed'>[];
    durationSeries: Bucket<'avg_us' | 'p95_us'>[];
    jobStats: {
        processed: number;
        released: number;
        failed: number;
        total: number;
        min_us: number;
        max_us: number;
        avg_us: number;
        p95_us: number;
    };
    attempts: PaginatedAttempts;
    users: Array<{ external_id: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const jobTone = (us: number) =>
    durationTone(us, { warn: 1_000_000, bad: 5_000_000 });

function attemptTone(status: string): SignalTone {
    if (status === 'failed') {
        return 'bad';
    }

    if (status === 'released') {
        return 'warn';
    }

    return 'good';
}

const attemptSeriesSpec = [
    {
        key: 'processed' as const,
        label: 'PROCESSED',
        color: 'var(--color-muted-foreground)',
    },
    {
        key: 'released' as const,
        label: 'RELEASED',
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
        key: 'processed' as const,
        label: 'Processed',
        count: props.jobStats.processed,
    },
    {
        key: 'released' as const,
        label: 'Released',
        count: props.jobStats.released,
    },
    { key: 'failed' as const, label: 'Failed', count: props.jobStats.failed },
];

const durationOptions = [
    { key: undefined, label: 'View all' },
    { key: 'avg', label: '≥ AVG' },
    { key: 'p95', label: '≥ P95' },
] as const;

function navigate(overrides: Record<string, unknown>) {
    router.get(
        jobShow(props.project.id, { query: { name: props.name } }).url,
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

function setUser(user: string | undefined) {
    navigate({ user });
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
            Back to jobs
        </Link>

        <div class="flex items-center justify-between">
            <Heading variant="small" :title="name" />
            <div class="flex items-center gap-2">
                <UserFilterDropdown
                    :model-value="filters.user"
                    :users="users"
                    @update:model-value="setUser"
                />
                <TimeRangeSelector
                    :model-value="range"
                    @update:model-value="setRange"
                />
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">ATTEMPTS</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(jobStats.total) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-signal-bad">FAILED</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(jobStats.failed) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">PROCESSED</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(jobStats.processed) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">RELEASED</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(jobStats.released) }}
                            </p>
                        </div>
                    </div>
                </div>
                <StackedBarChart
                    :data="attemptsSeries"
                    :series="attemptSeriesSpec"
                />
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">DURATION</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatDuration(jobStats.min_us) }} &ndash;
                            {{ formatDuration(jobStats.max_us) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-muted-foreground">AVG</p>
                            <p
                                class="font-mono"
                                :class="toneClasses[jobTone(jobStats.avg_us)]"
                            >
                                {{ formatDuration(jobStats.avg_us) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">P95</p>
                            <p
                                class="font-mono"
                                :class="toneClasses[jobTone(jobStats.p95_us)]"
                            >
                                {{ formatDuration(jobStats.p95_us) }}
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
            <p class="text-lg font-semibold">{{ jobStats.total }} Attempts</p>
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
                            <th class="py-2 pr-4 font-normal">Connection</th>
                            <th class="py-2 pr-4 font-normal">Queue</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Attempt
                            </th>
                            <th class="py-2 pr-4 font-normal">Status</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Duration
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="attempt in attempts.data"
                            :key="attempt.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="py-2 pl-4">
                                <SignalDot
                                    :tone="attemptTone(attempt.status)"
                                />
                            </td>
                            <td class="py-2 pr-4 text-xs text-muted-foreground">
                                {{ formatDateTime(attempt.occurred_at) }}
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs">
                                {{ attempt.connection || '—' }}
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs">
                                {{ attempt.queue || 'default' }}
                            </td>
                            <td class="py-2 pr-4 text-right text-xs">
                                {{ attempt.attempt }}
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    class="rounded-md border px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="
                                        toneClasses[attemptTone(attempt.status)]
                                    "
                                >
                                    {{ attempt.status }}
                                </span>
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[jobTone(attempt.duration_us)]
                                "
                            >
                                {{ formatDuration(attempt.duration_us) }}
                            </td>
                        </tr>
                        <tr v-if="attempts.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="7"
                            >
                                No attempts match this filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="attempts.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in attempts.links"
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
