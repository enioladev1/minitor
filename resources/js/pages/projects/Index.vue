<script setup lang="ts">
import { PlusSignIcon as Plus } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import ProjectOverviewController from '@/actions/App/Http/Controllers/ProjectOverviewController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProjectSparkline from '@/components/ProjectSparkline.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SignalTone } from '@/lib/signal';
import {
    formatCompactNumber,
    formatDuration,
    formatRelative,
    toneClasses,
} from '@/lib/signal';

type Bucket = {
    bucket: string;
    success: number;
    client_error: number;
    server_error: number;
};

type ProjectSummary = {
    id: number;
    name: string;
    requests_count: number;
    open_issues_count: number;
    last_seen_at: string | null;
    created_at: string;
    activity_series: Bucket[];
    activity_stats: {
        total: number;
        success: number;
        client_error: number;
        server_error: number;
        p95_us: number;
    };
};

defineProps<{
    projects: ProjectSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

type Health = 'good' | 'warn' | 'bad' | 'idle';

const healthColor: Record<Health, string> = {
    good: 'var(--color-signal-good)',
    warn: 'var(--color-signal-warn)',
    bad: 'var(--color-signal-bad)',
    idle: 'var(--color-muted-foreground)',
};

const healthLabel: Record<Health, string> = {
    good: 'Live',
    warn: 'Warnings',
    bad: 'Errors',
    idle: 'No signal',
};

function health(project: ProjectSummary): Health {
    if (project.requests_count === 0) {
        return 'idle';
    }

    if (project.activity_stats.server_error > 0) {
        return 'bad';
    }

    if (project.activity_stats.client_error > 0) {
        return 'warn';
    }

    return 'good';
}

function sparklineData(project: ProjectSummary) {
    return project.activity_series.map((b) => ({
        bucket: b.bucket,
        total: b.success + b.client_error + b.server_error,
    }));
}

const errorTone = (errors: number): SignalTone => (errors > 0 ? 'bad' : 'good');
</script>

<template>
    <Head title="Projects" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <Heading
                title="Projects"
                description="Every Laravel app you're watching, one panel each."
            />

            <Dialog>
                <DialogTrigger as-child>
                    <Button>
                        <HugeiconsIcon :icon="Plus" class="size-4" />
                        New project
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProjectController.store.form()"
                        reset-on-success
                        v-slot="{ errors, processing }"
                    >
                        <DialogHeader>
                            <DialogTitle>New project</DialogTitle>
                            <DialogDescription
                                >Give it a name you'll recognize - usually the
                                app it monitors.</DialogDescription
                            >
                        </DialogHeader>

                        <div class="my-4 space-y-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                placeholder="My Blog"
                                autofocus
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <DialogFooter>
                            <Button type="submit" :disabled="processing"
                                >Create project</Button
                            >
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <div
            v-if="projects.length === 0"
            class="rounded-lg border border-dashed p-12 text-center"
        >
            <svg
                viewBox="0 0 160 32"
                class="mx-auto mb-4 h-8 w-40 text-muted-foreground/30"
            >
                <path
                    d="M0,16 L48,16 L58,4 L68,28 L78,10 L88,16 L160,16"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />
            </svg>
            <p class="text-sm font-medium">No projects yet</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                Create one to get a token you can paste into a monitored app's
                <code class="font-mono text-xs">NIGHTWATCH_TOKEN</code>. Its
                vitals will start appearing here the moment the first request
                comes in.
            </p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="project in projects"
                :key="project.id"
                :href="ProjectOverviewController(project.id)"
                class="block h-full"
            >
                <div
                    class="flex h-full flex-col rounded-lg border p-4 transition-colors hover:border-foreground/30"
                >
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <p class="truncate text-base font-semibold">
                            {{ project.name }}
                        </p>
                        <span
                            class="flex shrink-0 items-center gap-1.5 text-xs font-medium uppercase"
                            :style="{ color: healthColor[health(project)] }"
                        >
                            <span
                                class="size-1.5 rounded-full"
                                :style="{
                                    backgroundColor:
                                        healthColor[health(project)],
                                }"
                            />
                            {{ healthLabel[health(project)] }}
                        </span>
                    </div>

                    <ProjectSparkline
                        :data="sparklineData(project)"
                        :color="healthColor[health(project)]"
                    />

                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <p class="text-muted-foreground">Requests</p>
                            <p class="font-mono text-foreground">
                                {{
                                    project.requests_count > 0
                                        ? formatCompactNumber(
                                              project.activity_stats.total,
                                          )
                                        : '-'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Errors</p>
                            <p
                                class="font-mono"
                                :class="
                                    project.requests_count > 0
                                        ? toneClasses[
                                              errorTone(
                                                  project.activity_stats
                                                      .client_error +
                                                      project.activity_stats
                                                          .server_error,
                                              )
                                          ]
                                        : 'text-foreground'
                                "
                            >
                                {{
                                    project.requests_count > 0
                                        ? formatCompactNumber(
                                              project.activity_stats
                                                  .client_error +
                                                  project.activity_stats
                                                      .server_error,
                                          )
                                        : '-'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">P95</p>
                            <p class="font-mono text-foreground">
                                {{
                                    project.activity_stats.total > 0
                                        ? formatDuration(
                                              project.activity_stats.p95_us,
                                          )
                                        : '-'
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="project.open_issues_count > 0"
                            class="text-signal-bad"
                        >
                            {{ project.open_issues_count }} open issue{{
                                project.open_issues_count === 1 ? '' : 's'
                            }}
                        </span>
                        <span v-else>No open issues</span>
                        <span>{{
                            project.last_seen_at
                                ? formatRelative(project.last_seen_at)
                                : 'Waiting for its first request…'
                        }}</span>
                    </div>
                </div>
            </Link>
        </div>
    </div>
</template>
