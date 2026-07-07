<script setup lang="ts">
import {
    ArrowDown01Icon as ArrowDown,
    ArrowUp01Icon as ArrowUp,
    ArrowUpDownIcon as ArrowUpDown,
    Database01Icon as Database,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    index,
    key as keyShow,
} from '@/actions/App/Http/Controllers/ProjectCacheEventController';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import ProjectSettingsDialog from '@/components/ProjectSettingsDialog.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatCompactNumber } from '@/lib/signal';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    range: string;
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
    keys: Array<{
        store: string | null;
        key: string | null;
        hit_rate: number | null;
        hits: number;
        misses: number;
        writes: number;
        deletes: number;
        failures: number;
        total: number;
    }>;
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

function navigate(overrides: Record<string, unknown>) {
    router.get(
        index(props.project.id).url,
        { range: props.range, ...overrides },
        { preserveState: true, replace: true },
    );
}

function setRange(range: string) {
    navigate({ range });
}

const sortColumns = [
    { key: 'hit_rate', label: 'Hit %' },
    { key: 'hits', label: 'Hits' },
    { key: 'misses', label: 'Misses' },
    { key: 'writes', label: 'Writes' },
    { key: 'deletes', label: 'Deletes' },
    { key: 'failures', label: 'Failures' },
    { key: 'total', label: 'Total' },
] as const;

const sortState = ref<{ sort?: string; direction?: string }>({
    sort: 'total',
    direction: 'desc',
});

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

function formatHitRate(rate: number | null): string {
    return rate === null ? '—' : `${rate}%`;
}
</script>

<template>
    <Head :title="`${project.name} — Cache`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <Heading
                :title="project.name"
                description="Cache"
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

        <p class="flex items-center gap-2 text-lg font-semibold">
            <HugeiconsIcon
                :icon="Database"
                class="size-5 text-muted-foreground"
            />
            {{ keys.length === 100 ? 'Top 100 keys' : `${keys.length} keys` }}
        </p>

        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="py-2 pr-4 pl-4 font-normal">Key</th>
                            <th
                                v-for="col in sortColumns"
                                :key="col.key"
                                class="py-2 pr-4 text-right font-normal"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-foreground"
                                    @click="toggleSort(col.key)"
                                >
                                    {{ col.label }}
                                    <HugeiconsIcon
                                        :icon="sortIcon(col.key)"
                                        class="size-3"
                                    />
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in keys"
                            :key="`${row.store}-${row.key}`"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="py-3 pr-4 pl-4">
                                <Link
                                    :href="
                                        keyShow(project.id, {
                                            query: {
                                                store: row.store ?? '',
                                                key: row.key ?? '',
                                            },
                                        })
                                    "
                                    class="block max-w-md truncate font-mono text-xs"
                                >
                                    {{ row.key || '—' }}
                                </Link>
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ formatHitRate(row.hit_rate) }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ row.hits }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ row.misses }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ row.writes }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ row.deletes }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    row.failures > 0 ? 'text-signal-bad' : ''
                                "
                            >
                                {{ row.failures }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ row.total }}
                            </td>
                        </tr>
                        <tr v-if="keys.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="8"
                            >
                                No cache events recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
