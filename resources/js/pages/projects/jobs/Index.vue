<script setup lang="ts">
import {
    ArrowDown01Icon as ArrowDown,
    ArrowUp01Icon as ArrowUp,
    ArrowUpDownIcon as ArrowUpDown,
    ExternalLinkIcon as ExternalLink,
    Refresh01Icon as RefreshCw,
    Search01Icon as Search,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    job as jobShow,
} from '@/actions/App/Http/Controllers/ProjectJobController';
import LineChart from '@/components/charts/LineChart.vue';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import ProjectSettingsDialog from '@/components/ProjectSettingsDialog.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import UserFilterDropdown from '@/components/UserFilterDropdown.vue';
import {
    durationTone,
    formatCompactNumber,
    formatDuration,
    toneClasses,
} from '@/lib/signal';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    range: string;
    filters: { q?: string; user?: string };
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
    jobs: Array<{
        name: string;
        queued: number;
        processed: number;
        released: number;
        failed: number;
        total: number;
        avg_us: number;
        p95_us: number;
    }>;
    users: Array<{ external_id: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const jobTone = (us: number) =>
    durationTone(us, { warn: 1_000_000, bad: 5_000_000 });

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

function setUser(user: string | undefined) {
    navigate({ user });
}

const sortColumns = [
    { key: 'name', label: 'Jobs', align: 'left' },
    { key: 'queued', label: 'Queued', align: 'right' },
    { key: 'processed', label: 'Processed', align: 'right' },
    { key: 'released', label: 'Released', align: 'right' },
    { key: 'failed', label: 'Failed', align: 'right' },
    { key: 'total', label: 'Total', align: 'right' },
    { key: 'avg', label: 'Avg', align: 'right' },
    { key: 'p95', label: 'P95', align: 'right' },
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
</script>

<template>
    <Head :title="`${project.name} — Jobs`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <Heading :title="project.name" description="Jobs" variant="small" />
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
                <ProjectSettingsDialog :project="project" />
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

        <div class="flex items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-lg font-semibold">
                <HugeiconsIcon
                    :icon="RefreshCw"
                    class="size-5 text-muted-foreground"
                />
                {{ jobs.length }} Jobs
            </p>
            <div class="relative w-64">
                <HugeiconsIcon
                    :icon="Search"
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search jobs"
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
                            <th
                                v-for="col in sortColumns"
                                :key="col.key"
                                class="py-2 pr-4 pl-4 font-normal first:pl-4"
                                :class="col.align === 'right' ? 'text-right' : ''"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-foreground"
                                    :class="col.align === 'right' ? 'justify-end' : ''"
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
                            v-for="job in jobs"
                            :key="job.name"
                            class="cursor-pointer border-b last:border-b-0 hover:bg-muted/50"
                            @click="
                                router.visit(
                                    jobShow(project.id, {
                                        query: { name: job.name },
                                    }),
                                )
                            "
                        >
                            <td class="py-3 pr-4 pl-4">
                                <Link
                                    :href="
                                        jobShow(project.id, {
                                            query: { name: job.name },
                                        })
                                    "
                                    class="font-mono text-xs"
                                    @click.stop
                                    >{{ job.name }}</Link
                                >
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ job.queued }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ job.processed }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    job.released > 0 ? 'text-signal-warn' : ''
                                "
                            >
                                {{ job.released }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="job.failed > 0 ? 'text-signal-bad' : ''"
                            >
                                {{ job.failed }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ job.total }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="toneClasses[jobTone(job.avg_us)]"
                            >
                                {{ formatDuration(job.avg_us) }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="toneClasses[jobTone(job.p95_us)]"
                            >
                                {{ formatDuration(job.p95_us) }}
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <Link
                                    :href="
                                        jobShow(project.id, {
                                            query: { name: job.name },
                                        })
                                    "
                                    @click.stop
                                >
                                    <HugeiconsIcon
                                        :icon="ExternalLink"
                                        class="size-3.5 text-muted-foreground"
                                    />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="jobs.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="9"
                            >
                                No jobs recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
