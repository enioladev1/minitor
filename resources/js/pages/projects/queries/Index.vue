<script setup lang="ts">
import {
    ArrowDown01Icon as ArrowDown,
    ArrowUp01Icon as ArrowUp,
    ArrowUpDownIcon as ArrowUpDown,
    Database01Icon as Database,
    ExternalLinkIcon as ExternalLink,
    Search01Icon as Search,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    query as queryShow,
} from '@/actions/App/Http/Controllers/ProjectQueryController';
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
    formatDuration,
    toneClasses,
} from '@/lib/signal';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    range: string;
    filters: { q?: string };
    callSeries: Bucket<'calls'>[];
    durationSeries: Bucket<'avg_us' | 'p95_us'>[];
    queryStats: {
        total: number;
        min_us: number;
        max_us: number;
        avg_us: number;
        p95_us: number;
        sum_us: number;
    };
    queries: Array<{
        group_key: string;
        sql: string;
        connection: string | null;
        connection_type: string | null;
        calls: number;
        sum_us: number;
        avg_us: number;
        p95_us: number;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const callSeriesSpec = [
    {
        key: 'calls' as const,
        label: 'CALLS',
        color: 'var(--color-muted-foreground)',
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
    { key: 'query', label: 'Query', align: 'left' },
    { key: 'connection', label: 'Connection', align: 'left' },
    { key: 'calls', label: 'Calls', align: 'right' },
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
    <Head :title="`${project.name} — Queries`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <Heading
                :title="project.name"
                description="Queries"
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
                        <p class="text-muted-foreground">CALLS</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(queryStats.total) }}
                        </p>
                    </div>
                </div>
                <StackedBarChart :data="callSeries" :series="callSeriesSpec" />
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">DURATION</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatDuration(queryStats.min_us) }} &ndash;
                            {{ formatDuration(queryStats.max_us) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-muted-foreground">AVG</p>
                            <p
                                class="font-mono"
                                :class="
                                    toneClasses[
                                        durationTone(queryStats.avg_us, {
                                            warn: 50_000,
                                            bad: 200_000,
                                        })
                                    ]
                                "
                            >
                                {{ formatDuration(queryStats.avg_us) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">P95</p>
                            <p
                                class="font-mono"
                                :class="
                                    toneClasses[
                                        durationTone(queryStats.p95_us, {
                                            warn: 50_000,
                                            bad: 200_000,
                                        })
                                    ]
                                "
                            >
                                {{ formatDuration(queryStats.p95_us) }}
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
                    :icon="Database"
                    class="size-5 text-muted-foreground"
                />
                {{ queries.length }} Queries
            </p>
            <div class="relative w-64">
                <HugeiconsIcon
                    :icon="Search"
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search queries"
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
                            v-for="row in queries"
                            :key="row.group_key"
                            class="cursor-pointer border-b last:border-b-0 hover:bg-muted/50"
                            @click="
                                router.visit(
                                    queryShow(project.id, {
                                        query: { group: row.group_key },
                                    }),
                                )
                            "
                        >
                            <td class="py-3 pr-4 pl-4">
                                <Link
                                    :href="
                                        queryShow(project.id, {
                                            query: { group: row.group_key },
                                        })
                                    "
                                    class="block max-w-lg truncate font-mono text-xs"
                                    @click.stop
                                >
                                    {{ row.sql }}
                                </Link>
                            </td>
                            <td class="py-3 pr-4 font-mono text-xs">
                                {{ row.connection || '—' }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ row.calls }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[
                                        durationTone(row.sum_us, {
                                            warn: 50_000,
                                            bad: 200_000,
                                        })
                                    ]
                                "
                            >
                                {{ formatDuration(row.sum_us) }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[
                                        durationTone(row.avg_us, {
                                            warn: 50_000,
                                            bad: 200_000,
                                        })
                                    ]
                                "
                            >
                                {{ formatDuration(row.avg_us) }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[
                                        durationTone(row.p95_us, {
                                            warn: 50_000,
                                            bad: 200_000,
                                        })
                                    ]
                                "
                            >
                                {{ formatDuration(row.p95_us) }}
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <Link
                                    :href="
                                        queryShow(project.id, {
                                            query: { group: row.group_key },
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
                        <tr v-if="queries.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="7"
                            >
                                No queries recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
