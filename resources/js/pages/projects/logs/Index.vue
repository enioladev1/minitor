<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import { index } from '@/actions/App/Http/Controllers/ProjectLogController';
import Heading from '@/components/Heading.vue';
import ProjectSettingsDialog from '@/components/ProjectSettingsDialog.vue';
import SignalDot from '@/components/SignalDot.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { SignalTone } from '@/lib/signal';
import { formatDateTime } from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type PaginatedLogs = {
    data: Array<{
        id: number;
        level: string;
        message: string | null;
        occurred_at: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    project: { id: number; name: string; token: string; local_secret: string };
    logs: PaginatedLogs;
    filters: { level?: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const levels = [
    undefined,
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug',
] as const;

function levelTone(level: string): SignalTone {
    if (['emergency', 'alert', 'critical', 'error'].includes(level)) {
        return 'bad';
    }

    if (['warning', 'notice'].includes(level)) {
        return 'warn';
    }

    return 'good';
}

function setLevel(level: string | undefined) {
    router.get(
        index(props.project.id).url,
        { level },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`${project.name} - Logs`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Heading :title="project.name" description="Logs" variant="small" />
            <ProjectSettingsDialog :project="project" />
        </div>

        <div class="flex flex-wrap gap-1">
            <Button
                v-for="level in levels"
                :key="level ?? 'all'"
                size="sm"
                :variant="filters.level === level ? 'default' : 'outline'"
                @click="setLevel(level)"
            >
                {{ level ?? 'All' }}
            </Button>
        </div>

        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <tbody>
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="border-b last:border-b-0"
                        >
                            <td class="w-8 py-2 pl-4">
                                <SignalDot :tone="levelTone(log.level)" />
                            </td>
                            <td
                                class="w-24 py-2 pr-4 text-xs text-muted-foreground uppercase"
                            >
                                {{ log.level }}
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs">
                                {{ log.message }}
                            </td>
                            <td
                                class="py-2 pr-4 text-right text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(log.occurred_at) }}
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td
                                class="p-4 text-sm text-muted-foreground"
                                colspan="4"
                            >
                                No logs recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="logs.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in logs.links"
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
