<script setup lang="ts">
import {
    ArrowDown01Icon as ArrowDown,
    ArrowUp01Icon as ArrowUp,
    ArrowUpDownIcon as ArrowUpDown,
    ExternalLinkIcon as ExternalLink,
    Search01Icon as Search,
    UserMultipleIcon as UsersIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/ProjectUserController';
import LineChart from '@/components/charts/LineChart.vue';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import ProjectSettingsDialog from '@/components/ProjectSettingsDialog.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatCompactNumber, formatRelative } from '@/lib/signal';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string; notify_on_new_issue: boolean };
    range: string;
    filters: { q?: string };
    activeUsersSeries: Bucket<'authenticated'>[];
    requestsSeries: Bucket<'authenticated' | 'guest'>[];
    requestsSummary: { total: number; authenticated: number; guest: number };
    authenticatedUsers: number;
    users: Array<{
        external_id: string;
        name: string | null;
        username: string | null;
        success: number;
        client_error: number;
        server_error: number;
        requests: number;
        queued_jobs: number;
        exceptions: number;
        last_seen_at: string;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const activeUsersSeriesSpec = [
    {
        key: 'authenticated' as const,
        label: 'ACTIVE',
        color: 'var(--color-signal-good)',
    },
];

const requestsSeriesSpec = [
    {
        key: 'authenticated' as const,
        label: 'AUTHENTICATED',
        color: 'var(--color-signal-good)',
    },
    {
        key: 'guest' as const,
        label: 'GUEST',
        color: 'var(--color-signal-warn)',
    },
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
    { key: 'success', label: '1/2/3XX' },
    { key: 'client_error', label: '4XX' },
    { key: 'server_error', label: '5XX' },
    { key: 'requests', label: 'Requests' },
    { key: 'queued_jobs', label: 'Queued jobs' },
    { key: 'exceptions', label: 'Exceptions' },
    { key: 'last_seen', label: 'Last seen' },
] as const;

const sortState = ref<{ sort?: string; direction?: string }>({
    sort: 'requests',
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
</script>

<template>
    <Head :title="`${project.name} - Users`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                :title="project.name"
                description="Users"
                variant="small"
            />
            <div class="flex flex-wrap items-center gap-2">
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
                        <p class="text-muted-foreground">AUTHENTICATED USERS</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(authenticatedUsers) }}
                        </p>
                    </div>
                </div>
                <LineChart
                    :data="activeUsersSeries"
                    :series="activeUsersSeriesSpec"
                    :format-value="formatCompactNumber"
                />
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-y-2 text-xs">
                    <div>
                        <p class="text-muted-foreground">REQUESTS</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(requestsSummary.total) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-signal-good">AUTHENTICATED</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(
                                        requestsSummary.authenticated,
                                    )
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-warn">GUEST</p>
                            <p class="font-mono text-foreground">
                                {{ formatCompactNumber(requestsSummary.guest) }}
                            </p>
                        </div>
                    </div>
                </div>
                <StackedBarChart
                    :data="requestsSeries"
                    :series="requestsSeriesSpec"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-lg font-semibold">
                <HugeiconsIcon
                    :icon="UsersIcon"
                    class="size-5 text-muted-foreground"
                />
                {{ users.length }} Users
            </p>
            <div class="relative w-64">
                <HugeiconsIcon
                    :icon="Search"
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search users"
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
                            <th class="py-2 pr-4 pl-4 font-normal">User</th>
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
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.external_id"
                            class="cursor-pointer border-b last:border-b-0 hover:bg-muted/50"
                            @click="
                                router.visit(
                                    show([project.id, user.external_id]),
                                )
                            "
                        >
                            <td class="py-3 pr-4 pl-4">
                                <Link
                                    :href="show([project.id, user.external_id])"
                                    class="flex items-center gap-2 font-mono text-xs"
                                    @click.stop
                                >
                                    <span class="text-muted-foreground">{{
                                        user.external_id
                                    }}</span>
                                    {{
                                        user.name ||
                                        user.username ||
                                        user.external_id
                                    }}
                                </Link>
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ user.success }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    user.client_error > 0
                                        ? 'text-signal-warn'
                                        : ''
                                "
                            >
                                {{ user.client_error }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    user.server_error > 0
                                        ? 'text-signal-bad'
                                        : ''
                                "
                            >
                                {{ user.server_error }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ user.requests }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ user.queued_jobs }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right font-mono text-xs"
                                :class="
                                    user.exceptions > 0 ? 'text-signal-bad' : ''
                                "
                            >
                                {{ user.exceptions }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatRelative(user.last_seen_at) }}
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <Link
                                    :href="show([project.id, user.external_id])"
                                    @click.stop
                                >
                                    <HugeiconsIcon
                                        :icon="ExternalLink"
                                        class="size-3.5 text-muted-foreground"
                                    />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="9"
                            >
                                No users identified yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
