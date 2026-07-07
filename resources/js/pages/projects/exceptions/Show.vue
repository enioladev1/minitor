<script setup lang="ts">
import {
    ArrowLeft01Icon as ArrowLeft,
    CheckIcon as Check,
    Copy01Icon as Copy,
    ExternalLinkIcon as ExternalLink,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { computed } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/ProjectExceptionController';
import { show as requestShow } from '@/actions/App/Http/Controllers/ProjectRequestController';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import StackTrace from '@/components/StackTrace.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import UserFilterDropdown from '@/components/UserFilterDropdown.vue';
import { buildExceptionMarkdown } from '@/lib/exceptionMarkdown';
import { formatCompactNumber, formatDateTime } from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

type PaginatedOccurrences = {
    data: Array<{
        id: number;
        message: string | null;
        handled: boolean;
        occurred_at: string;
        user_external_id: string | null;
        execution_source: string | null;
        execution_preview: string | null;
        source_request_id: number | null;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string };
    issue: {
        id: number;
        class: string;
        file: string;
        line: number;
        first_seen_at: string;
        last_seen_at: string;
    };
    latestOccurrence: {
        message: string | null;
        code: string | null;
        handled: boolean;
        trace: string | null;
        php_version: string | null;
        laravel_version: string | null;
        occurred_at: string;
        execution_source: string | null;
    } | null;
    latestOccurrenceContext: {
        source_request: {
            method: string;
            url: string;
            route_name: string | null;
            status_code: number;
            ip: string | null;
        } | null;
        authenticated_user: {
            external_id: string;
            name: string | null;
            username: string | null;
        } | null;
        queries: Array<{
            sql: string;
            duration_us: number;
            connection: string | null;
        }>;
        logs: Array<{
            level: string;
            message: string | null;
            occurred_at: string;
        }>;
    } | null;
    range: string;
    filters: { user?: string };
    occurrenceSeries: Bucket<'handled' | 'unhandled'>[];
    exceptionStats: { handled: number; unhandled: number; total: number };
    impactedUsers: number;
    occurrences7d: number;
    occurrences24h: number;
    phpVersions: string[];
    laravelVersions: string[];
    servers: string[];
    occurrences: PaginatedOccurrences;
    users: Array<{ external_id: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const { copy, copied } = useClipboard();

const occurrenceSeriesSpec = [
    {
        key: 'handled' as const,
        label: 'HANDLED',
        color: 'var(--color-muted-foreground)',
    },
    {
        key: 'unhandled' as const,
        label: 'UNHANDLED',
        color: 'var(--color-signal-bad)',
    },
];

const title = computed(
    () => props.latestOccurrence?.message || props.issue.class,
);

const markdown = computed(() =>
    buildExceptionMarkdown({
        class: props.issue.class,
        file: props.issue.file,
        line: props.issue.line,
        message: props.latestOccurrence?.message ?? null,
        code: props.latestOccurrence?.code ?? null,
        handled: props.latestOccurrence?.handled ?? false,
        trace: props.latestOccurrence?.trace ?? null,
        occurredAt: props.latestOccurrence?.occurred_at ?? null,
        executionSource: props.latestOccurrence?.execution_source ?? null,
        sourceRequest: props.latestOccurrenceContext?.source_request ?? null,
        authenticatedUser:
            props.latestOccurrenceContext?.authenticated_user ?? null,
        queries: props.latestOccurrenceContext?.queries ?? [],
        logs: props.latestOccurrenceContext?.logs ?? [],
        firstSeenAt: props.issue.first_seen_at,
        lastSeenAt: props.issue.last_seen_at,
        occurrences24h: props.occurrences24h,
        occurrences7d: props.occurrences7d,
        impactedUsers: props.impactedUsers,
    }),
);

function navigate(overrides: Record<string, unknown>) {
    router.get(
        show([props.project.id, props.issue.id]).url,
        { ...props.filters, range: props.range, ...overrides },
        { preserveState: true, replace: true },
    );
}

function setRange(range: string) {
    navigate({ range });
}

function setUser(user: string | undefined) {
    navigate({ user });
}
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <Link
            :href="index(project.id)"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <HugeiconsIcon :icon="ArrowLeft" class="size-4" />
            Back to exceptions
        </Link>

        <div class="flex items-center justify-between">
            <Heading variant="small" description="Exceptions" :title="title" />
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
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-4">
                <dl class="space-y-2 text-sm">
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Last seen
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ formatDateTime(issue.last_seen_at) }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            First seen
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ formatDateTime(issue.first_seen_at) }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            PHP versions
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ phpVersions.join(', ') || '—' }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Laravel versions
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ laravelVersions.join(', ') || '—' }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Impacted users
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">{{ impactedUsers }}</dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Occurrences
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="flex gap-2 text-xs">
                            <span class="text-muted-foreground">7 days</span>
                            <span class="rounded bg-muted px-1.5 font-mono">{{
                                occurrences7d
                            }}</span>
                            <span class="text-muted-foreground">24 hours</span>
                            <span class="rounded bg-muted px-1.5 font-mono">{{
                                occurrences24h
                            }}</span>
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt
                            class="shrink-0 text-xs whitespace-nowrap text-muted-foreground uppercase"
                        >
                            Servers
                        </dt>
                        <span
                            class="mb-1 flex-1 border-b border-dotted border-muted-foreground/30"
                        ></span>
                        <dd class="font-mono text-xs">
                            {{ servers.join(', ') || '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-start justify-between text-xs">
                    <div>
                        <p class="text-muted-foreground">OCCURRENCES</p>
                        <p class="font-mono text-xl text-foreground">
                            {{ formatCompactNumber(exceptionStats.total) }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-right">
                        <div>
                            <p class="text-muted-foreground">HANDLED</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(exceptionStats.handled)
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-signal-bad">UNHANDLED</p>
                            <p class="font-mono text-foreground">
                                {{
                                    formatCompactNumber(
                                        exceptionStats.unhandled,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <StackedBarChart
                    :data="occurrenceSeries"
                    :series="occurrenceSeriesSpec"
                />
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        latestOccurrence?.handled
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-signal-bad text-white'
                    "
                >
                    {{ latestOccurrence?.handled ? 'HANDLED' : 'UNHANDLED' }}
                </span>
                <Button variant="outline" size="sm" @click="copy(markdown)">
                    <HugeiconsIcon
                        :icon="Check"
                        v-if="copied"
                        class="size-3.5"
                    />
                    <HugeiconsIcon :icon="Copy" v-else class="size-3.5" />
                    Copy as Markdown
                </Button>
                <span
                    v-if="latestOccurrence?.laravel_version"
                    class="ml-auto rounded-full border px-2 py-0.5 font-mono text-xs"
                >
                    Laravel {{ latestOccurrence.laravel_version }}
                </span>
                <span
                    v-if="latestOccurrence?.php_version"
                    class="rounded-full border px-2 py-0.5 font-mono text-xs"
                >
                    PHP {{ latestOccurrence.php_version }}
                </span>
            </div>

            <div>
                <p class="text-xl font-semibold">{{ issue.class }}</p>
                <p
                    v-if="latestOccurrence?.message"
                    class="text-sm text-muted-foreground"
                >
                    {{ latestOccurrence.message }}
                </p>
            </div>

            <StackTrace :trace="latestOccurrence?.trace ?? null" />
        </div>

        <p class="text-lg font-semibold">
            {{ occurrences.data.length }} occurrences
        </p>

        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="py-2 pr-4 pl-4 font-normal">Date</th>
                            <th class="py-2 pr-4 font-normal">Source</th>
                            <th class="py-2 pr-4 font-normal">Message</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                User
                            </th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="occurrence in occurrences.data"
                            :key="occurrence.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td
                                class="py-2 pr-4 pl-4 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(occurrence.occurred_at) }}
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    v-if="occurrence.execution_source"
                                    class="inline-flex items-center gap-2"
                                >
                                    <span
                                        class="rounded-md border px-1.5 py-0.5 font-mono text-xs uppercase"
                                        >{{ occurrence.execution_source }}</span
                                    >
                                    <span
                                        class="max-w-[16rem] truncate font-mono text-xs text-muted-foreground"
                                        >{{
                                            occurrence.execution_preview
                                        }}</span
                                    >
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="max-w-md truncate py-2 pr-4 text-xs">
                                {{ occurrence.message }}
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-mono text-xs text-muted-foreground"
                            >
                                {{ occurrence.user_external_id ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 text-right">
                                <Link
                                    v-if="occurrence.source_request_id"
                                    :href="
                                        requestShow([
                                            project.id,
                                            occurrence.source_request_id,
                                        ])
                                    "
                                >
                                    <HugeiconsIcon
                                        :icon="ExternalLink"
                                        class="size-3.5 text-muted-foreground"
                                    />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="occurrences.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="5"
                            >
                                No occurrences match this filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="occurrences.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in occurrences.links"
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
