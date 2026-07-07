<script setup lang="ts">
import {
    ArrowLeft01Icon as ArrowLeft,
    Folder01Icon as Folder,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    query as queryShow,
} from '@/actions/App/Http/Controllers/ProjectQueryController';
import LineChart from '@/components/charts/LineChart.vue';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
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

type PaginatedCalls = {
    data: Array<{
        id: number;
        occurred_at: string;
        execution_source: string | null;
        file: string | null;
        line: number;
        connection: string | null;
        connection_type: string | null;
        duration_us: number;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string };
    groupKey: string;
    sql: string | null;
    range: string;
    filters: { duration?: string };
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
    calls: PaginatedCalls;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const queryTone = (us: number) =>
    durationTone(us, { warn: 50_000, bad: 200_000 });

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

const durationOptions = [
    { key: undefined, label: 'View all' },
    { key: 'avg', label: '≥ AVG' },
    { key: 'p95', label: '≥ P95' },
] as const;

const KEYWORDS = [
    'select',
    'insert into',
    'update',
    'delete from',
    'from',
    'where',
    'and',
    'or',
    'order by',
    'group by',
    'limit',
    'values',
    'set',
    'join',
    'left join',
    'inner join',
    'on',
];

const sqlHtml = computed(() => {
    if (!props.sql) {
        return '';
    }

    let html = props.sql
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    for (const keyword of KEYWORDS) {
        const pattern = new RegExp(
            `\\b${keyword.replace(' ', '\\s+')}\\b`,
            'gi',
        );
        html = html.replace(
            pattern,
            (match) => `<span class="text-blue-400">${match}</span>`,
        );
    }

    return html;
});

function navigate(overrides: Record<string, unknown>) {
    router.get(
        queryShow(props.project.id, { query: { group: props.groupKey } }).url,
        { ...props.filters, range: props.range, ...overrides },
        { preserveState: true, replace: true },
    );
}

function setDurationFilter(duration: string | undefined) {
    navigate({ duration });
}

function setRange(range: string) {
    navigate({ range });
}
</script>

<template>
    <Head :title="sql ?? 'Query'" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <Link
            :href="index(project.id)"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <HugeiconsIcon :icon="ArrowLeft" class="size-4" />
            Back to queries
        </Link>

        <div class="flex items-center justify-between gap-4">
            <Heading
                variant="small"
                description="Queries"
                :title="sql ?? 'Query'"
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
                                    toneClasses[queryTone(queryStats.avg_us)]
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
                                    toneClasses[queryTone(queryStats.p95_us)]
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

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-4">
                <p class="mb-3 text-base font-semibold">Info</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Total time
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ formatDuration(queryStats.sum_us) }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Avg time
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ formatDuration(queryStats.avg_us) }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            P95
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ formatDuration(queryStats.p95_us) }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Calls
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ queryStats.total }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border p-4">
                <pre
                    class="overflow-x-auto font-mono text-xs whitespace-pre-wrap"
                    v-html="sqlHtml"
                ></pre>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-lg font-semibold">{{ queryStats.total }} Calls</p>
            <div class="flex gap-1">
                <Button
                    v-for="option in durationOptions"
                    :key="option.label"
                    size="sm"
                    :variant="
                        filters.duration === option.key ? 'default' : 'outline'
                    "
                    @click="setDurationFilter(option.key)"
                >
                    {{ option.label }}
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
                            <th class="py-2 pr-4 pl-4 font-normal">Date</th>
                            <th class="py-2 pr-4 font-normal">Source</th>
                            <th class="py-2 pr-4 font-normal">Location</th>
                            <th class="py-2 pr-4 font-normal">Connection</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Duration
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="call in calls.data"
                            :key="call.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td
                                class="py-2 pr-4 pl-4 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(call.occurred_at) }}
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    v-if="call.execution_source"
                                    class="rounded-md border px-1.5 py-0.5 font-mono text-xs uppercase"
                                >
                                    {{ call.execution_source }}
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >-</span
                                >
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    v-if="call.file"
                                    class="inline-flex items-center gap-1.5 font-mono text-xs text-muted-foreground"
                                >
                                    <HugeiconsIcon
                                        :icon="Folder"
                                        class="size-3.5"
                                    />
                                    {{ call.file }}:{{ call.line }}
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >-</span
                                >
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    class="inline-flex items-center gap-1.5 font-mono text-xs"
                                >
                                    {{ call.connection || '-' }}
                                    <span
                                        v-if="call.connection_type"
                                        class="rounded border px-1 py-0.5 text-[10px] uppercase"
                                        >{{ call.connection_type }}</span
                                    >
                                </span>
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[queryTone(call.duration_us)]
                                "
                            >
                                {{ formatDuration(call.duration_us) }}
                            </td>
                        </tr>
                        <tr v-if="calls.data.length === 0">
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

        <div v-if="calls.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in calls.links"
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
