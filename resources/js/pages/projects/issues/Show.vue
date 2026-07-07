<script setup lang="ts">
import {
    ArrowLeft01Icon as ArrowLeft,
    CheckIcon as Check,
    Copy01Icon as Copy,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { computed } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import {
    index,
    storeComment,
    updateStatus,
} from '@/actions/App/Http/Controllers/ProjectIssueController';
import Heading from '@/components/Heading.vue';
import SignalDot from '@/components/SignalDot.vue';
import StackTrace from '@/components/StackTrace.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { buildExceptionMarkdown } from '@/lib/exceptionMarkdown';
import { formatDateTime, formatRelative } from '@/lib/signal';
import { cn, paginationLabel } from '@/lib/utils';

type PaginatedOccurrences = {
    data: Array<{
        id: number;
        message: string | null;
        code: string | null;
        handled: boolean;
        trace: string | null;
        occurred_at: string;
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
        occurrences_count: number;
        status: string;
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
    impactedUsers: number;
    occurrences7d: number;
    occurrences24h: number;
    occurrences: PaginatedOccurrences;
    events: Array<{
        id: number;
        type: string;
        body: string | null;
        meta: { from?: string; to?: string; reason?: string } | null;
        created_at: string;
        user: { id: number; name: string } | null;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});

const { copy, copied } = useClipboard();

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

function setStatus(status: string) {
    router.patch(
        updateStatus([props.project.id, props.issue.id]).url,
        { status },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="issue.class" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <Link
            :href="index(project.id)"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <HugeiconsIcon :icon="ArrowLeft" class="size-4" />
            Back to issues
        </Link>

        <div
            v-if="issue.status !== 'open'"
            :class="
                cn(
                    'rounded-md border p-3 text-sm',
                    issue.status === 'resolved'
                        ? 'border-signal-good/30 bg-signal-good/10'
                        : 'border-signal-warn/30 bg-signal-warn/10',
                )
            "
        >
            <p
                :class="
                    cn(
                        'font-medium',
                        issue.status === 'resolved'
                            ? 'text-signal-good'
                            : 'text-signal-warn',
                    )
                "
            >
                {{ issue.status === 'resolved' ? 'Resolved' : 'Ignored' }}
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="flex items-center gap-3">
                    <SignalDot
                        :tone="
                            issue.status === 'open'
                                ? 'bad'
                                : issue.status === 'resolved'
                                  ? 'good'
                                  : 'warn'
                        "
                        class="size-3"
                    />
                    <Heading
                        variant="small"
                        :title="issue.class"
                        :description="`${issue.file}:${issue.line}`"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            latestOccurrence?.handled
                                ? 'bg-muted text-muted-foreground'
                                : 'bg-signal-bad/15 text-signal-bad'
                        "
                    >
                        {{
                            latestOccurrence?.handled ? 'HANDLED' : 'UNHANDLED'
                        }}
                    </span>
                    <span
                        v-if="latestOccurrence?.laravel_version"
                        class="rounded-full border px-2 py-0.5 font-mono text-xs"
                    >
                        Laravel {{ latestOccurrence.laravel_version }}
                    </span>
                    <span
                        v-if="latestOccurrence?.php_version"
                        class="rounded-full border px-2 py-0.5 font-mono text-xs"
                    >
                        PHP {{ latestOccurrence.php_version }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        class="ml-auto"
                        @click="copy(markdown)"
                    >
                        <HugeiconsIcon
                            :icon="Check"
                            v-if="copied"
                            class="size-3.5"
                        />
                        <HugeiconsIcon :icon="Copy" v-else class="size-3.5" />
                        Copy as Markdown
                    </Button>
                </div>

                <p
                    v-if="latestOccurrence?.message"
                    class="text-sm text-muted-foreground"
                >
                    {{ latestOccurrence.message }}
                </p>

                <StackTrace :trace="latestOccurrence?.trace ?? null" />

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >{{
                                issue.occurrences_count
                            }}
                            occurrences</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="space-y-3 p-0">
                        <div
                            v-for="occurrence in occurrences.data"
                            :key="occurrence.id"
                            class="flex items-center justify-between gap-3 border-t px-4 py-2.5 text-xs first:border-t-0"
                        >
                            <span class="text-muted-foreground">{{
                                formatDateTime(occurrence.occurred_at)
                            }}</span>
                            <span class="min-w-0 flex-1 truncate">{{
                                occurrence.message
                            }}</span>
                            <span class="shrink-0 text-muted-foreground">{{
                                occurrence.handled ? 'Handled' : 'Unhandled'
                            }}</span>
                        </div>
                        <p
                            v-if="occurrences.data.length === 0"
                            class="p-4 text-sm text-muted-foreground"
                        >
                            No occurrences recorded.
                        </p>
                    </CardContent>
                </Card>

                <div
                    v-if="occurrences.links.length > 3"
                    class="flex flex-wrap gap-1"
                >
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

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Activity</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="text-sm text-muted-foreground">
                            <span class="font-medium text-foreground"
                                >MINITOR</span
                            >
                            created the issue &middot;
                            {{ formatRelative(issue.first_seen_at) }}
                        </div>

                        <div
                            v-for="event in events"
                            :key="event.id"
                            class="text-sm"
                        >
                            <template v-if="event.type === 'status_changed'">
                                <span class="text-muted-foreground">
                                    <span class="font-medium text-foreground">{{
                                        event.user?.name ?? 'System'
                                    }}</span>
                                    marked the issue as
                                    <span class="font-medium">{{
                                        event.meta?.to
                                    }}</span>
                                    &middot;
                                    {{ formatRelative(event.created_at) }}
                                </span>
                            </template>
                            <template v-else>
                                <p class="mb-1 text-muted-foreground">
                                    <span class="font-medium text-foreground">{{
                                        event.user?.name ?? 'Someone'
                                    }}</span>
                                    commented &middot;
                                    {{ formatRelative(event.created_at) }}
                                </p>
                                <p class="rounded-md border p-2.5">
                                    {{ event.body }}
                                </p>
                            </template>
                        </div>

                        <Form
                            v-bind="storeComment.form([project.id, issue.id])"
                            reset-on-success
                            class="space-y-2"
                            v-slot="{ processing }"
                        >
                            <Textarea
                                name="body"
                                placeholder="Add a comment..."
                                rows="3"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="processing"
                                >Comment</Button
                            >
                        </Form>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">Manage</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p class="text-xs text-muted-foreground">Status</p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="status in [
                                    'open',
                                    'resolved',
                                    'ignored',
                                ]"
                                :key="status"
                                size="sm"
                                :variant="
                                    issue.status === status
                                        ? 'default'
                                        : 'outline'
                                "
                                class="capitalize"
                                @click="setStatus(status)"
                            >
                                {{ status }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >First seen</span
                            >
                            <span>{{
                                formatRelative(issue.first_seen_at)
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Last seen</span>
                            <span>{{
                                formatRelative(issue.last_seen_at)
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Occurrences</span
                            >
                            <span>{{ issue.occurrences_count }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
