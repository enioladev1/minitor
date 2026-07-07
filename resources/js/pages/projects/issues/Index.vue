<script setup lang="ts">
import {
    ArrowDown01Icon as ArrowDown,
    ArrowUp01Icon as ArrowUp,
    ArrowUpDownIcon as ArrowUpDown,
    Search01Icon as Search,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/ProjectIssueController';
import Heading from '@/components/Heading.vue';
import ProjectSettingsDialog from '@/components/ProjectSettingsDialog.vue';
import SignalDot from '@/components/SignalDot.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatRelative } from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type PaginatedIssues = {
    data: Array<{
        id: number;
        class: string;
        file: string;
        line: number;
        last_message: string | null;
        occurrences_count: number;
        users_impacted: number;
        status: string;
        first_seen_at: string;
        last_seen_at: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    issues: PaginatedIssues;
    filters: { q?: string; status?: string; sort?: string; direction?: string };
    counts: { open: number; resolved: number; ignored: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const statusTabs = [
    { key: 'open', label: 'Open' },
    { key: 'resolved', label: 'Resolved' },
    { key: 'ignored', label: 'Ignored' },
] as const;

const search = ref(props.filters.q ?? '');

watch(search, (value) => {
    router.get(
        index(props.project.id).url,
        { ...props.filters, q: value || undefined },
        { preserveState: true, replace: true },
    );
});

function setStatus(status: string) {
    router.get(
        index(props.project.id).url,
        { ...props.filters, status, q: props.filters.q },
        { preserveState: true, replace: true },
    );
}

const sortColumns = [
    { key: 'count', label: 'Count' },
    { key: 'users', label: 'Users' },
    { key: 'first_seen', label: 'First seen' },
    { key: 'last_seen', label: 'Last seen' },
] as const;

function toggleSort(key: string) {
    const direction =
        props.filters.sort === key && props.filters.direction === 'desc'
            ? 'asc'
            : 'desc';
    router.get(
        index(props.project.id).url,
        { ...props.filters, sort: key, direction },
        { preserveState: true, replace: true },
    );
}

function sortIcon(key: string) {
    if (props.filters.sort !== key) {
        return ArrowUpDown;
    }

    return props.filters.direction === 'asc' ? ArrowUp : ArrowDown;
}
</script>

<template>
    <Head :title="`${project.name} - Issues`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <Heading
                :title="project.name"
                description="Issues"
                variant="small"
            />
            <ProjectSettingsDialog :project="project" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-1 rounded-md border p-1">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    :class="
                        cn(
                            'rounded-sm px-3 py-1 text-sm font-medium transition-colors',
                            filters.status === tab.key ||
                                (!filters.status && tab.key === 'open')
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:text-foreground',
                        )
                    "
                    @click="setStatus(tab.key)"
                >
                    {{ tab.label }}
                    <span class="ml-1 text-xs opacity-70">{{
                        counts[tab.key]
                    }}</span>
                </button>
            </div>

            <div class="relative w-64">
                <HugeiconsIcon
                    :icon="Search"
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search issues"
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
                            <th class="w-8 py-2 pl-4"></th>
                            <th class="py-2 pr-4 font-normal">Issue</th>
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
                            v-for="issue in issues.data"
                            :key="issue.id"
                            class="border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="w-8 py-3 pl-4">
                                <SignalDot
                                    :tone="
                                        issue.status === 'open'
                                            ? 'bad'
                                            : issue.status === 'resolved'
                                              ? 'good'
                                              : 'warn'
                                    "
                                />
                            </td>
                            <td class="py-3 pr-4">
                                <Link
                                    :href="show([project.id, issue.id])"
                                    class="block"
                                >
                                    <p class="font-mono text-sm">
                                        {{ issue.class }}
                                    </p>
                                    <p
                                        class="max-w-lg truncate text-xs text-muted-foreground"
                                    >
                                        {{ issue.last_message }}
                                    </p>
                                </Link>
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ issue.occurrences_count }}
                            </td>
                            <td class="py-3 pr-4 text-right font-mono text-xs">
                                {{ issue.users_impacted }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatRelative(issue.first_seen_at) }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatRelative(issue.last_seen_at) }}
                            </td>
                        </tr>
                        <tr v-if="issues.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="6"
                            >
                                No {{ filters.status ?? 'open' }} issues.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="issues.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in issues.links"
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
