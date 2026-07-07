<script setup lang="ts">
import {
    ArrowDown01Icon as ArrowDown,
    ArrowUp01Icon as ArrowUp,
    ArrowUpDownIcon as ArrowUpDown,
    ExternalLinkIcon as ExternalLink,
    GlobeIcon as Globe,
    Search01Icon as Search,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    route as routeShow,
} from '@/actions/App/Http/Controllers/ProjectRequestController';
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
import { cn, paginationLabel } from '@/lib/utils';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

type PaginatedRoutes = {
    data: Array<{
        method: string;
        path: string;
        total: number;
        success: number;
        client_error: number;
        server_error: number;
        avg_us: number;
        p95_us: number;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    range: string;
    filters: { q?: string; user?: string };
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
    routes: PaginatedRoutes;
    users: Array<{ external_id: string; label: string }>;
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
    { key: 'method', label: 'Method', align: 'left' },
    { key: 'path', label: 'Path', align: 'left' },
    { key: 'success', label: '1/2/3XX', align: 'right' },
    { key: 'client_error', label: '4XX', align: 'right' },
    { key: 'server_error', label: '5XX', align: 'right' },
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
    <Head :title="`${project.name} - Requests`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                :title="project.name"
                description="Requests"
                variant="small"
            />
            <div class="flex flex-wrap items-center gap-2">
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
                <div class="mb-3 flex flex-wrap items-start justify-between gap-y-2 text-xs">
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
                <div class="mb-3 flex flex-wrap items-start justify-between gap-y-2 text-xs">
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
            <p class="flex items-center gap-2 text-lg font-semibold">
                <HugeiconsIcon
                    :icon="Globe"
                    class="size-5 text-muted-foreground"
                />
                {{ routes.data.length }} Routes
            </p>
            <div class="relative w-64">
                <HugeiconsIcon
                    :icon="Search"
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search routes"
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
                            v-for="route in routes.data"
                            :key="`${route.method}-${route.path}`"
                            class="cursor-pointer border-b last:border-b-0 hover:bg-muted/50"
                            @click="
                                router.visit(
                                    routeShow(project.id, {
                                        query: {
                                            method: route.method,
                                            path: route.path,
                                        },
                                    }),
                                )
                            "
                        >
                            <td class="py-3 pr-4 pl-4 font-mono text-xs">
                                {{ route.method }}
                            </td>
                            <td class="py-3 pr-4">
                                <Link
                                    :href="
                                        routeShow(project.id, {
                                            query: {
                                                method: route.method,
                                                path: route.path,
                                            },
                                        })
                                    "
                                    class="font-mono text-xs"
                                    @click.stop
                                >
                                    {{ route.path }}
                                </Link>
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ route.success }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    route.client_error > 0
                                        ? 'text-signal-warn'
                                        : ''
                                "
                            >
                                {{ route.client_error }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    route.server_error > 0
                                        ? 'text-signal-bad'
                                        : ''
                                "
                            >
                                {{ route.server_error }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ route.total }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="toneClasses[durationTone(route.avg_us)]"
                            >
                                {{ formatDuration(route.avg_us) }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="toneClasses[durationTone(route.p95_us)]"
                            >
                                {{ formatDuration(route.p95_us) }}
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <Link
                                    :href="
                                        routeShow(project.id, {
                                            query: {
                                                method: route.method,
                                                path: route.path,
                                            },
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
                        <tr v-if="routes.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="9"
                            >
                                No routes recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="routes.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in routes.links"
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
