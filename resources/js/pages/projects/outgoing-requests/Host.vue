<script setup lang="ts">
import {
    ArrowLeft01Icon as ArrowLeft,
    GlobeIcon as Globe,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    host as hostShow,
    index,
} from '@/actions/App/Http/Controllers/ProjectOutgoingRequestController';
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
    statusTone,
    toneClasses,
} from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

type PaginatedRequests = {
    data: Array<{
        id: number;
        method: string | null;
        url: string | null;
        status_code: number;
        duration_us: number;
        execution_source: string | null;
        execution_preview: string | null;
        occurred_at: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string };
    host: string;
    range: string;
    filters: { status?: string; duration?: string };
    requestsSeries: Bucket<'success' | 'client_error' | 'server_error'>[];
    durationSeries: Bucket<'avg_us' | 'p95_us'>[];
    requestStats: {
        total: number;
        success: number;
        client_error: number;
        server_error: number;
        min_us: number;
        max_us: number;
        avg_us: number;
        p95_us: number;
    };
    requests: PaginatedRequests;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const requestSeriesSpec = [
    {
        key: 'success' as const,
        label: '1/2/3XX',
        color: 'var(--color-muted-foreground)',
    },
    {
        key: 'client_error' as const,
        label: '4XX',
        color: 'var(--color-signal-warn)',
    },
    {
        key: 'server_error' as const,
        label: '5XX',
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
        key: 'success' as const,
        label: '1/2/3xx',
        count: props.requestStats.success,
    },
    {
        key: 'client_error' as const,
        label: '4xx',
        count: props.requestStats.client_error,
    },
    {
        key: 'server_error' as const,
        label: '5xx',
        count: props.requestStats.server_error,
    },
];

const durationOptions = [
    { key: undefined, label: 'View all' },
    { key: 'avg', label: '≥ AVG' },
    { key: 'p95', label: '≥ P95' },
] as const;

function navigate(overrides: Record<string, unknown>) {
    router.get(
        hostShow(props.project.id, { query: { host: props.host } }).url,
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
    <Head :title="host" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <Link
            :href="index(project.id)"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <HugeiconsIcon :icon="ArrowLeft" class="size-4" />
            Back to outgoing requests
        </Link>

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <HugeiconsIcon
                    :icon="Globe"
                    class="size-5 text-muted-foreground"
                />
                <Heading
                    variant="small"
                    description="Outgoing Requests"
                    :title="host"
                />
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
                        <p class="text-muted-foreground">REQUESTS</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(requestStats.total) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-muted-foreground">1/2/3XX</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(requestStats.success) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">4XX</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(
                                        requestStats.client_error,
                                    )
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-bad">5XX</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(
                                        requestStats.server_error,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <StackedBarChart
                    :data="requestsSeries"
                    :series="requestSeriesSpec"
                />
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">DURATION</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatDuration(requestStats.min_us) }} &ndash;
                            {{ formatDuration(requestStats.max_us) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-muted-foreground">AVG</p>
                            <p
                                class="font-mono"
                                :class="
                                    toneClasses[
                                        durationTone(requestStats.avg_us)
                                    ]
                                "
                            >
                                {{ formatDuration(requestStats.avg_us) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">P95</p>
                            <p
                                class="font-mono"
                                :class="
                                    toneClasses[
                                        durationTone(requestStats.p95_us)
                                    ]
                                "
                            >
                                {{ formatDuration(requestStats.p95_us) }}
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
                {{ requestStats.total }} Requests
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
                            v-if="
                                option.count !== undefined && option.count > 0
                            "
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
                            <th class="py-2 pr-4 pl-4 font-normal">Date</th>
                            <th class="py-2 pr-4 font-normal">Source</th>
                            <th class="py-2 pr-4 font-normal">Method</th>
                            <th class="py-2 pr-4 font-normal">Status</th>
                            <th class="py-2 pr-4 font-normal">URL</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Duration
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="request in requests.data"
                            :key="request.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td
                                class="py-2 pr-4 pl-4 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(request.occurred_at) }}
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    v-if="request.execution_source"
                                    class="inline-flex items-center gap-2"
                                >
                                    <span
                                        class="rounded-md border px-1.5 py-0.5 font-mono text-xs uppercase"
                                        >{{ request.execution_source }}</span
                                    >
                                    <span
                                        class="max-w-[14rem] truncate font-mono text-xs text-muted-foreground"
                                        >{{ request.execution_preview }}</span
                                    >
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs font-medium">
                                {{ request.method }}
                            </td>
                            <td
                                class="py-2 pr-4 font-mono text-xs"
                                :class="
                                    toneClasses[statusTone(request.status_code)]
                                "
                            >
                                {{ request.status_code }}
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    class="inline-flex max-w-md items-center gap-1.5 truncate font-mono text-xs text-muted-foreground"
                                >
                                    <HugeiconsIcon
                                        :icon="Globe"
                                        class="size-3.5 shrink-0"
                                    />
                                    {{ request.url }}
                                </span>
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[
                                        durationTone(request.duration_us)
                                    ]
                                "
                            >
                                {{ formatDuration(request.duration_us) }}
                            </td>
                        </tr>
                        <tr v-if="requests.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="6"
                            >
                                No requests match this filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="requests.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in requests.links"
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
