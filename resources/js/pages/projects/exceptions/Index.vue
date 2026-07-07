<script setup lang="ts">
import {
    Alert01Icon as AlertTriangle,
    Search01Icon as Search,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/ProjectExceptionController';
import StackedBarChart from '@/components/charts/StackedBarChart.vue';
import Heading from '@/components/Heading.vue';
import ProjectSettingsDialog from '@/components/ProjectSettingsDialog.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import UserFilterDropdown from '@/components/UserFilterDropdown.vue';
import { formatCompactNumber, formatRelative } from '@/lib/signal';

type Bucket<T extends string> = { bucket: string } & Record<T, number>;

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    range: string;
    filters: { q?: string; status?: string; user?: string };
    occurrenceSeries: Bucket<'handled' | 'unhandled'>[];
    exceptionStats: { handled: number; unhandled: number; total: number };
    exceptions: Array<{
        id: number;
        class: string;
        message: string | null;
        handled: boolean;
        last_seen_at: string;
        count: number;
        users: number;
    }>;
    unhandledCount: number;
    handledCount: number;
    users: Array<{ external_id: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

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

const statusOptions = [
    { key: undefined, label: 'View all' },
    { key: 'handled' as const, label: 'Handled', count: props.handledCount },
    {
        key: 'unhandled' as const,
        label: 'Unhandled',
        count: props.unhandledCount,
    },
];

function setStatusFilter(status: string | undefined) {
    navigate({ status });
}
</script>

<template>
    <Head :title="`${project.name} — Exceptions`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <Heading
                :title="project.name"
                description="Exceptions"
                variant="small"
            />
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
                            {{ formatCompactNumber(exceptionStats.handled) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-signal-bad">UNHANDLED</p>
                        <p class="font-mono text-foreground">
                            {{ formatCompactNumber(exceptionStats.unhandled) }}
                        </p>
                    </div>
                </div>
            </div>
            <StackedBarChart
                :data="occurrenceSeries"
                :series="occurrenceSeriesSpec"
            />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-lg font-semibold">
                <HugeiconsIcon
                    :icon="AlertTriangle"
                    class="size-5 text-muted-foreground"
                />
                {{ exceptions.length }} Exceptions
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-64">
                    <HugeiconsIcon
                        :icon="Search"
                        class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search exceptions"
                        class="pl-8"
                    />
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
                            class="rounded-full bg-signal-bad px-1.5 text-xs text-white"
                        >
                            {{ option.count }}
                        </span>
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
                            <th class="py-2 pr-4 pl-4 font-normal">
                                Last seen
                            </th>
                            <th class="py-2 pr-4 font-normal">Exception</th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Count
                            </th>
                            <th class="py-2 pr-4 text-right font-normal">
                                Users
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="exception in exceptions"
                            :key="exception.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td
                                class="py-3 pr-4 pl-4 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatRelative(exception.last_seen_at) }}
                            </td>
                            <td class="py-3 pr-4">
                                <Link
                                    :href="show([project.id, exception.id])"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            exception.handled
                                                ? 'bg-muted text-muted-foreground'
                                                : 'bg-signal-bad text-white'
                                        "
                                    >
                                        {{
                                            exception.handled
                                                ? 'HANDLED'
                                                : 'UNHANDLED'
                                        }}
                                    </span>
                                    <span class="font-mono text-xs">{{
                                        exception.class
                                    }}</span>
                                    <span
                                        class="min-w-0 flex-1 truncate text-xs text-muted-foreground"
                                        >{{ exception.message }}</span
                                    >
                                </Link>
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ exception.count }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ exception.users }}
                            </td>
                        </tr>
                        <tr v-if="exceptions.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="4"
                            >
                                No exceptions recorded. Good sign.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
