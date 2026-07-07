<script setup lang="ts">
import {
    ArrowDown01Icon as ArrowDown,
    ArrowUp01Icon as ArrowUp,
    ArrowUpDownIcon as ArrowUpDown,
    Calendar01Icon as Calendar,
    ExternalLinkIcon as ExternalLink,
    Search01Icon as Search,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    task as taskShow,
} from '@/actions/App/Http/Controllers/ProjectScheduledTaskController';
import LineChart from '@/components/charts/LineChart.vue';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import ProjectSettingsDialog from '@/components/ProjectSettingsDialog.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    durationTone,
    formatCompactNumber,
    formatCountdown,
    formatDuration,
    toneClasses,
} from '@/lib/signal';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    range: string;
    filters: { q?: string };
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
    tasks: Array<{
        name: string;
        cron: string | null;
        timezone: string | null;
        repeat_seconds: number;
        schedule: string;
        next_run: string | null;
        processed: number;
        skipped: number;
        failed: number;
        total: number;
        avg_us: number;
        p95_us: number;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

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

const search = ref(props.filters.q ?? '');

function navigate(overrides: Record<string, unknown>) {
    router.get(
        index(props.project.id).url,
        { ...props.filters, range: props.range, ...overrides },
        { preserveState: true, replace: true },
    );
}

watch(search, (value) => navigate({ q: value || undefined }));

function setRange(range: string) {
    navigate({ range });
}

const sortColumns = [
    { key: 'name', label: 'Task' },
    { key: 'processed', label: 'Processed' },
    { key: 'skipped', label: 'Skipped' },
    { key: 'failed', label: 'Failed' },
    { key: 'total', label: 'Total' },
    { key: 'avg', label: 'Avg' },
    { key: 'p95', label: 'P95' },
] as const;

const sortState = ref<{ sort?: string; direction?: string }>({});

function toggleSort(key: string) {
    const direction =
        sortState.value.sort === key && sortState.value.direction === 'desc'
            ? 'asc'
            : 'desc';
    sortState.value = { sort: key, direction };
    navigate({ sort: key, direction });
}

function sortIcon(key: string) {
    if (sortState.value.sort !== key) {
        return ArrowUpDown;
    }

    return sortState.value.direction === 'asc' ? ArrowUp : ArrowDown;
}

function taskLink(task: (typeof props.tasks)[number]) {
    return taskShow(props.project.id, {
        query: {
            name: task.name,
            cron: task.cron ?? '',
            timezone: task.timezone ?? '',
            repeat_seconds: task.repeat_seconds,
        },
    });
}
</script>

<template>
    <Head :title="`${project.name} - Scheduled Tasks`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <Heading
                :title="project.name"
                description="Scheduled tasks"
                variant="small"
            />
            <div class="flex items-center gap-2">
                <TimeRangeSelector
                    :model-value="range"
                    @update:model-value="setRange"
                />
                <ProjectSettingsDialog :project="project" />
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">SCHEDULED TASKS</p>
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

        <div class="flex items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-lg font-semibold">
                <HugeiconsIcon
                    :icon="Calendar"
                    class="size-5 text-muted-foreground"
                />
                {{ tasks.length }} Tasks
            </p>
            <div class="relative w-64">
                <HugeiconsIcon
                    :icon="Search"
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search tasks"
                    class="pl-8"
                />
            </div>
        </div>

        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="py-2 pr-4 pl-4 font-normal">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-foreground"
                                    @click="toggleSort('name')"
                                >
                                    Task
                                    <HugeiconsIcon
                                        :icon="sortIcon('name')"
                                        class="size-3"
                                    />
                                </button>
                            </th>
                            <th class="py-2 pr-4 font-normal">Schedule</th>
                            <th class="py-2 pr-4 font-normal">Next run</th>
                            <th
                                v-for="col in sortColumns.slice(1)"
                                :key="col.key"
                                class="py-2 pr-4 text-right font-normal"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-end gap-1 hover:text-foreground"
                                    @click="toggleSort(col.key)"
                                >
                                    {{ col.label }}
                                    <HugeiconsIcon
                                        :icon="sortIcon(col.key)"
                                        class="size-3"
                                    />
                                </button>
                            </th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="task in tasks"
                            :key="`${task.name}-${task.cron}-${task.repeat_seconds}`"
                            class="cursor-pointer border-b last:border-b-0 hover:bg-muted/50"
                            @click="router.visit(taskLink(task))"
                        >
                            <td class="py-3 pr-4 pl-4">
                                <Link
                                    :href="taskLink(task)"
                                    class="block max-w-xs truncate font-mono text-xs"
                                    @click.stop
                                    >{{ task.name }}</Link
                                >
                            </td>
                            <td class="py-3 pr-4 text-xs text-muted-foreground">
                                {{ task.schedule }}
                            </td>
                            <td
                                class="py-3 pr-4 font-mono text-xs text-muted-foreground"
                            >
                                {{
                                    task.next_run
                                        ? formatCountdown(task.next_run)
                                        : '-'
                                }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ task.processed }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    task.skipped > 0 ? 'text-signal-warn' : ''
                                "
                            >
                                {{ task.skipped }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    task.failed > 0 ? 'text-signal-bad' : ''
                                "
                            >
                                {{ task.failed }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ task.total }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="toneClasses[durationTone(task.avg_us)]"
                            >
                                {{ formatDuration(task.avg_us) }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="toneClasses[durationTone(task.p95_us)]"
                            >
                                {{ formatDuration(task.p95_us) }}
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <Link :href="taskLink(task)" @click.stop>
                                    <HugeiconsIcon
                                        :icon="ExternalLink"
                                        class="size-3.5 text-muted-foreground"
                                    />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="tasks.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="10"
                            >
                                No scheduled tasks recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
