<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import { index as jobsIndex } from '@/actions/App/Http/Controllers/ProjectJobController';
import ProjectOverviewController from '@/actions/App/Http/Controllers/ProjectOverviewController';
import { index as requestsIndex } from '@/actions/App/Http/Controllers/ProjectRequestController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/ProjectUserController';
import LineChart from '@/components/charts/LineChart.vue';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import ProjectSettingsDialog from '@/components/ProjectSettingsDialog.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    durationTone,
    formatCompactNumber,
    formatDuration,
    toneClasses,
} from '@/lib/signal';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    justCreated: boolean;
    range: string;
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
    exceptionsSummary: { count: number; users_impacted: number };
    jobStats: {
        processed: number;
        failed: number;
        released: number;
        min_us: number;
        max_us: number;
        avg_us: number;
        p95_us: number;
    };
    jobsSeries: Bucket<'processed' | 'failed' | 'released'>[];
    usersStats: {
        authenticated_requests: number;
        guest_requests: number;
        authenticated_users: number;
        most_active: Array<{
            external_id: string;
            name: string | null;
            requests: number;
        }>;
        most_impacted_by_exceptions: Array<{
            external_id: string;
            name: string | null;
            exceptions: number;
        }>;
    };
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

const jobsSeriesSpec = [
    {
        key: 'processed' as const,
        label: 'PROCESSED',
        color: 'var(--color-muted-foreground)',
    },
    {
        key: 'failed' as const,
        label: 'FAILED',
        color: 'var(--color-signal-bad)',
    },
    {
        key: 'released' as const,
        label: 'RELEASED',
        color: 'var(--color-signal-warn)',
    },
];

function setRange(range: string) {
    router.get(
        ProjectOverviewController(props.project.id).url,
        { range },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="project.name" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <Heading :title="project.name" variant="small" />
            <div class="flex items-center gap-2">
                <TimeRangeSelector
                    :model-value="range"
                    @update:model-value="setRange"
                />
                <ProjectSettingsDialog
                    :project="project"
                    :default-open="justCreated"
                />
            </div>
        </div>

        <!-- Activity -->
        <Card>
            <CardHeader class="flex-row items-center justify-between space-y-0">
                <CardTitle class="text-base">Activity</CardTitle>
                <Button as-child variant="outline" size="sm">
                    <Link :href="requestsIndex(project.id)">Requests</Link>
                </Button>
            </CardHeader>
            <CardContent class="grid gap-4 lg:grid-cols-2">
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
                                    {{
                                        formatCompactNumber(
                                            requestStats.success,
                                        )
                                    }}
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
                                {{
                                    formatDuration(requestStats.min_us)
                                }}
                                &ndash;
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
            </CardContent>
        </Card>

        <!-- Application -->
        <Card>
            <CardHeader class="flex-row items-center justify-between space-y-0">
                <CardTitle class="text-base">Application</CardTitle>
                <Button as-child variant="outline" size="sm">
                    <Link :href="jobsIndex(project.id)">Jobs</Link>
                </Button>
            </CardHeader>
            <CardContent class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border p-4">
                    <p class="mb-1 text-xs text-muted-foreground">EXCEPTIONS</p>
                    <p class="text-lg font-semibold">
                        {{
                            exceptionsSummary.count.toLocaleString()
                        }}
                        exception{{
                            exceptionsSummary.count === 1 ? '' : 's'
                        }}
                        reported
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Errors have impacted
                        {{ exceptionsSummary.users_impacted }} user{{
                            exceptionsSummary.users_impacted === 1 ? '' : 's'
                        }}.
                    </p>
                </div>

                <div class="rounded-lg border p-4">
                    <div class="mb-3 flex items-start justify-between text-xs">
                        <p class="text-muted-foreground">JOBS</p>
                        <div class="flex gap-4 text-right">
                            <div>
                                <p class="text-signal-bad">FAILED</p>
                                <p class="font-mono text-foreground">
                                    {{ jobStats.failed }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">PROCESSED</p>
                                <p class="font-mono text-foreground">
                                    {{ jobStats.processed }}
                                </p>
                            </div>
                            <div>
                                <p class="text-signal-warn">RELEASED</p>
                                <p class="font-mono text-foreground">
                                    {{ jobStats.released }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <StackedBarChart
                        :data="jobsSeries"
                        :series="jobsSeriesSpec"
                        :height="110"
                    />
                    <div
                        class="mt-4 flex items-center justify-between border-t pt-3 text-xs"
                    >
                        <p class="text-muted-foreground">
                            JOB DURATION
                            <span class="ml-2 font-mono text-foreground"
                                >{{ formatDuration(jobStats.min_us) }} &ndash;
                                {{ formatDuration(jobStats.max_us) }}</span
                            >
                        </p>
                        <div class="flex gap-4 text-right">
                            <div>
                                <p class="text-muted-foreground">AVG</p>
                                <p class="font-mono text-foreground">
                                    {{ formatDuration(jobStats.avg_us) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-signal-warn">P95</p>
                                <p class="font-mono text-foreground">
                                    {{ formatDuration(jobStats.p95_us) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Users -->
        <Card>
            <CardHeader class="flex-row items-center justify-between space-y-0">
                <CardTitle class="text-base">Users</CardTitle>
                <Button as-child variant="outline" size="sm">
                    <Link :href="usersIndex(project.id)">Users</Link>
                </Button>
            </CardHeader>
            <CardContent class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="mb-1 text-xs text-muted-foreground">EXCEPTIONS</p>
                    <p class="mb-3 text-base font-semibold">
                        {{ exceptionsSummary.users_impacted }} user{{
                            exceptionsSummary.users_impacted === 1 ? '' : 's'
                        }}
                        impacted by exceptions in the last period.
                    </p>
                    <div class="space-y-2">
                        <div
                            v-for="user in usersStats.most_impacted_by_exceptions"
                            :key="user.external_id"
                            class="flex items-center justify-between rounded-md border p-2 text-sm"
                        >
                            <span class="truncate font-mono text-xs">{{
                                user.name || user.external_id
                            }}</span>
                            <span
                                class="rounded-full bg-signal-bad/15 px-2 py-0.5 text-xs font-medium text-signal-bad"
                                >{{ user.exceptions }}</span
                            >
                        </div>
                        <p
                            v-if="
                                usersStats.most_impacted_by_exceptions
                                    .length === 0
                            "
                            class="text-sm text-muted-foreground"
                        >
                            No users impacted.
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <p class="mb-1 text-xs text-muted-foreground">REQUESTS</p>
                    <p class="mb-3 text-base font-semibold">
                        Most active users in this period.
                    </p>
                    <div class="space-y-2">
                        <div
                            v-for="user in usersStats.most_active"
                            :key="user.external_id"
                            class="flex items-center justify-between rounded-md border p-2 text-sm"
                        >
                            <span class="truncate font-mono text-xs">{{
                                user.name || user.external_id
                            }}</span>
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >{{ user.requests }}</span
                            >
                        </div>
                        <p
                            v-if="usersStats.most_active.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No authenticated activity.
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-lg border p-4">
                        <p class="text-xs text-muted-foreground">
                            AUTHENTICATED USERS
                        </p>
                        <p class="font-mono text-xl">
                            {{ usersStats.authenticated_users }}
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <div class="flex items-start justify-between text-xs">
                            <p class="text-muted-foreground">REQUESTS</p>
                            <div class="flex gap-4 text-right">
                                <div>
                                    <p class="text-muted-foreground">
                                        AUTHENTICATED
                                    </p>
                                    <p class="font-mono text-foreground">
                                        {{
                                            formatCompactNumber(
                                                usersStats.authenticated_requests,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">GUEST</p>
                                    <p class="font-mono text-foreground">
                                        {{
                                            formatCompactNumber(
                                                usersStats.guest_requests,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
