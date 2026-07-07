<script setup lang="ts">
import { ArrowLeft01Icon as ArrowLeft } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    command as commandShow,
    index,
} from '@/actions/App/Http/Controllers/ProjectCommandController';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import LineChart from '@/components/charts/LineChart.vue';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import SignalDot from '@/components/SignalDot.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    durationTone,
    formatCompactNumber,
    formatDateTime,
    formatDuration,
    toneClasses,
} from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

type PaginatedCommands = {
    data: Array<{
        id: number;
        name: string;
        command: string | null;
        exit_code: number;
        duration_us: number;
        occurred_at: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string };
    name: string;
    range: string;
    filters: { status?: string; duration?: string };
    callSeries: Bucket<'successful' | 'unsuccessful'>[];
    durationSeries: Bucket<'avg_us' | 'p95_us'>[];
    commandStats: {
        successful: number;
        unsuccessful: number;
        total: number;
        min_us: number;
        max_us: number;
        avg_us: number;
        p95_us: number;
    };
    commands: PaginatedCommands;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const callSeriesSpec = [
    {
        key: 'successful' as const,
        label: 'SUCCESSFUL',
        color: 'var(--color-muted-foreground)',
    },
    {
        key: 'unsuccessful' as const,
        label: 'UNSUCCESSFUL',
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
        key: 'successful' as const,
        label: 'Successful',
        count: props.commandStats.successful,
    },
    {
        key: 'failed' as const,
        label: 'Failed',
        count: props.commandStats.unsuccessful,
    },
];

const durationOptions = [
    { key: undefined, label: 'View all' },
    { key: 'avg', label: '≥ AVG' },
    { key: 'p95', label: '≥ P95' },
] as const;

function navigate(overrides: Record<string, unknown>) {
    router.get(
        commandShow(props.project.id, { query: { name: props.name } }).url,
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
            Back to commands
        </Link>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Heading variant="small" :title="name" />
            <TimeRangeSelector
                :model-value="range"
                @update:model-value="setRange"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-4">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-y-2 text-xs">
                    <div>
                        <p class="text-muted-foreground">CALLS</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(commandStats.total) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-signal-bad">UNSUCCESSFUL</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(
                                        commandStats.unsuccessful,
                                    )
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">SUCCESSFUL</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(commandStats.successful)
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <StackedBarChart :data="callSeries" :series="callSeriesSpec" />
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-y-2 text-xs">
                    <div>
                        <p class="text-muted-foreground">DURATION</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatDuration(commandStats.min_us) }} &ndash;
                            {{ formatDuration(commandStats.max_us) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-muted-foreground">AVG</p>
                            <p
                                class="font-mono"
                                :class="
                                    toneClasses[
                                        durationTone(commandStats.avg_us)
                                    ]
                                "
                            >
                                {{ formatDuration(commandStats.avg_us) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">P95</p>
                            <p
                                class="font-mono"
                                :class="
                                    toneClasses[
                                        durationTone(commandStats.p95_us)
                                    ]
                                "
                            >
                                {{ formatDuration(commandStats.p95_us) }}
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
            <p class="text-lg font-semibold">
                {{ commandStats.total }} Commands
            </p>
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
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="w-8 py-2 pl-4"></th>
                            <th class="py-2 pr-4 font-normal">Date</th>
                            <th class="py-2 pr-4 font-normal">Command</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Exit code
                            </th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Duration
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in commands.data"
                            :key="entry.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="py-2 pl-4">
                                <SignalDot
                                    :tone="
                                        entry.exit_code === 0 ? 'good' : 'bad'
                                    "
                                />
                            </td>
                            <td class="py-2 pr-4 text-xs text-muted-foreground">
                                {{ formatDateTime(entry.occurred_at) }}
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs">
                                {{ entry.command || entry.name }}
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-mono text-xs"
                                :class="
                                    entry.exit_code === 0
                                        ? ''
                                        : 'text-signal-bad'
                                "
                            >
                                {{ entry.exit_code }}
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[durationTone(entry.duration_us)]
                                "
                            >
                                {{ formatDuration(entry.duration_us) }}
                            </td>
                        </tr>
                        <tr v-if="commands.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="5"
                            >
                                No calls match this filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="commands.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in commands.links"
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
