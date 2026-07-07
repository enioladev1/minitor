<script setup lang="ts">
import {
    ArrowLeft01Icon as ArrowLeft,
    Search01Icon as Search,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import { index as exceptionsIndex } from '@/actions/App/Http/Controllers/ProjectExceptionController';
import { index as jobsIndex } from '@/actions/App/Http/Controllers/ProjectJobController';
import { index as logsIndex } from '@/actions/App/Http/Controllers/ProjectLogController';
import {
    index as requestsIndex,
    show as requestShow,
} from '@/actions/App/Http/Controllers/ProjectRequestController';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/ProjectUserController';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import SignalDot from '@/components/SignalDot.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    durationTone,
    formatCompactNumber,
    formatDateTime,
    formatDuration,
    formatRelative,
    statusTone,
    toneClasses,
} from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

type PaginatedRequests = {
    data: Array<{
        id: number;
        method: string;
        route_path: string | null;
        url: string;
        status_code: number;
        duration_us: number;
        occurred_at: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string };
    user: {
        external_id: string;
        name: string | null;
        username: string | null;
        last_seen_at: string;
    };
    range: string;
    filters: { q?: string; status?: string; duration?: string };
    requestsSeries: Bucket<'success' | 'client_error' | 'server_error'>[];
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
    topRoutes: Array<{ method: string; path: string; total: number }>;
    slowestRoutes: Array<{ method: string; path: string; p95_us: number }>;
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

const search = ref(props.filters.q ?? '');

function navigate(overrides: Record<string, unknown>) {
    router.get(
        show([props.project.id, props.user.external_id]).url,
        { ...props.filters, range: props.range, ...overrides },
        { preserveState: true, replace: true },
    );
}

watch(search, (value) => navigate({ q: value || undefined }));

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

function setStatusFilter(status: string | undefined) {
    navigate({ status });
}

function setDurationFilter(duration: string | undefined) {
    navigate({ duration });
}

const userFilterParams = { user: props.user.external_id };
</script>

<template>
    <Head :title="user.name || user.username || user.external_id" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <Link
            :href="index(project.id)"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <HugeiconsIcon :icon="ArrowLeft" class="size-4" />
            Back to users
        </Link>

        <Heading
            variant="small"
            description="Users"
            :title="user.name || user.username || user.external_id"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_1.2fr]">
            <div class="rounded-lg border p-4">
                <p class="mb-3 text-base font-semibold">Info</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            ID
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ user.external_id }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Username
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ user.username || user.name || '-' }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Last seen
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="text-xs">
                            {{ formatRelative(user.last_seen_at) }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <p class="mb-2 text-xs text-muted-foreground uppercase">
                        Filter by
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child size="sm" variant="outline">
                            <Link
                                :href="
                                    requestsIndex(project.id, {
                                        query: userFilterParams,
                                    })
                                "
                                >Requests</Link
                            >
                        </Button>
                        <Button as-child size="sm" variant="outline">
                            <Link
                                :href="
                                    jobsIndex(project.id, {
                                        query: userFilterParams,
                                    })
                                "
                                >Jobs</Link
                            >
                        </Button>
                        <Button as-child size="sm" variant="outline">
                            <Link
                                :href="
                                    exceptionsIndex(project.id, {
                                        query: userFilterParams,
                                    })
                                "
                                >Exceptions</Link
                            >
                        </Button>
                        <Button as-child size="sm" variant="outline">
                            <Link :href="logsIndex(project.id)">Logs</Link>
                        </Button>
                    </div>
                </div>
            </div>

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
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-4">
                <p class="mb-3 text-base font-semibold">Top Routes</p>
                <div class="space-y-2">
                    <div
                        v-for="route in topRoutes"
                        :key="`${route.method}-${route.path}`"
                        class="flex items-center justify-between gap-3 text-xs"
                    >
                        <span class="min-w-0 flex-1 truncate font-mono">
                            <span class="mr-1.5 font-medium">{{
                                route.method
                            }}</span>
                            {{ route.path }}
                        </span>
                        <span
                            class="shrink-0 font-mono text-muted-foreground"
                            >{{ route.total }}</span
                        >
                    </div>
                    <p
                        v-if="topRoutes.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        No routes recorded.
                    </p>
                </div>
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-base font-semibold">Slowest Routes</p>
                    <span
                        class="rounded-md border px-1.5 py-0.5 text-xs text-muted-foreground"
                        >P95</span
                    >
                </div>
                <div class="space-y-2">
                    <div
                        v-for="route in slowestRoutes"
                        :key="`${route.method}-${route.path}`"
                        class="flex items-center justify-between gap-3 text-xs"
                    >
                        <span class="min-w-0 flex-1 truncate font-mono">
                            <span class="mr-1.5 font-medium">{{
                                route.method
                            }}</span>
                            {{ route.path }}
                        </span>
                        <span
                            class="shrink-0 font-mono"
                            :class="toneClasses[durationTone(route.p95_us)]"
                            >{{ formatDuration(route.p95_us) }}</span
                        >
                    </div>
                    <p
                        v-if="slowestRoutes.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        No routes recorded.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-lg font-semibold">
                {{ requestStats.total }} Requests
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-56">
                    <HugeiconsIcon
                        :icon="Search"
                        class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search requests"
                        class="pl-8"
                    />
                </div>
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
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="w-8 py-2 pl-4"></th>
                            <th class="py-2 pr-4 font-normal">Method</th>
                            <th class="py-2 pr-4 font-normal">URL</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Status
                            </th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Duration
                            </th>
                            <th class="py-2 pr-4 text-right font-normal">
                                When
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="request in requests.data"
                            :key="request.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="py-2 pl-4">
                                <SignalDot
                                    :tone="statusTone(request.status_code)"
                                />
                            </td>
                            <td class="py-2 pr-4">
                                <Link
                                    :href="
                                        requestShow([project.id, request.id])
                                    "
                                    class="font-mono text-xs"
                                    >{{ request.method }}</Link
                                >
                            </td>
                            <td class="py-2 pr-4">
                                <Link
                                    :href="
                                        requestShow([project.id, request.id])
                                    "
                                    class="block max-w-md truncate font-mono text-xs"
                                >
                                    {{ request.route_path || request.url }}
                                </Link>
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-mono text-xs"
                                :class="
                                    toneClasses[statusTone(request.status_code)]
                                "
                            >
                                {{ request.status_code }}
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
                            <td
                                class="py-2 pr-4 text-right text-xs text-muted-foreground"
                            >
                                {{ formatDateTime(request.occurred_at) }}
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
