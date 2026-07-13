<script setup lang="ts">
import { ArrowLeft01Icon as ArrowLeft } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import { index } from '@/actions/App/Http/Controllers/ProjectRequestController';
import Heading from '@/components/Heading.vue';
import RequestTimeline from '@/components/RequestTimeline.vue';
import SignalDot from '@/components/SignalDot.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    durationTone,
    formatBytes,
    formatDateTime,
    formatDuration,
    statusTone,
    toneClasses,
} from '@/lib/signal';
import { requestPath } from '@/lib/utils';

type TimelineChild = {
    type: string;
    label: string;
    detail: string;
    offset_us: number;
    duration_us: number;
};

type TimelineStage = {
    key: string;
    label: string;
    detail: string | null;
    offset_us: number;
    duration_us: number;
    children: TimelineChild[];
};

defineProps<{
    project: { id: number; name: string };
    requestRecord: {
        id: number;
        method: string;
        url: string;
        route_name: string | null;
        route_path: string | null;
        route_action: string | null;
        ip: string | null;
        status_code: number;
        duration_us: number;
        request_size: number;
        response_size: number;
        trace_id: string | null;
        occurred_at: string;
    };
    timeline: { total_us: number; stages: TimelineStage[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: ProjectController.index() }],
    },
});
</script>

<template>
    <Head
        :title="`${requestRecord.method} ${requestRecord.route_path || requestPath(requestRecord.url)}`"
    />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <Link
            :href="index(project.id)"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <HugeiconsIcon :icon="ArrowLeft" class="size-4" />
            Back to requests
        </Link>

        <div class="flex items-center gap-3">
            <SignalDot
                :tone="statusTone(requestRecord.status_code)"
                class="size-3"
            />
            <Heading
                variant="small"
                :title="`${requestRecord.method} ${requestRecord.route_path || requestPath(requestRecord.url)}`"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <Card>
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-sm font-normal text-muted-foreground"
                        >Status</CardTitle
                    ></CardHeader
                >
                <CardContent
                    class="font-mono text-xl"
                    :class="toneClasses[statusTone(requestRecord.status_code)]"
                >
                    {{ requestRecord.status_code }}
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-sm font-normal text-muted-foreground"
                        >Duration</CardTitle
                    ></CardHeader
                >
                <CardContent
                    class="font-mono text-xl"
                    :class="
                        toneClasses[durationTone(requestRecord.duration_us)]
                    "
                >
                    {{ formatDuration(requestRecord.duration_us) }}
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-sm font-normal text-muted-foreground"
                        >Request size</CardTitle
                    ></CardHeader
                >
                <CardContent class="font-mono text-xl">{{
                    formatBytes(requestRecord.request_size)
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-sm font-normal text-muted-foreground"
                        >Response size</CardTitle
                    ></CardHeader
                >
                <CardContent class="font-mono text-xl">{{
                    formatBytes(requestRecord.response_size)
                }}</CardContent>
            </Card>
        </div>

        <RequestTimeline :request-record="requestRecord" :timeline="timeline" />

        <Card>
            <CardHeader
                ><CardTitle class="text-base">Details</CardTitle></CardHeader
            >
            <CardContent>
                <dl
                    class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm"
                >
                    <dt class="text-muted-foreground">URL</dt>
                    <dd class="font-mono break-all">{{ requestRecord.url }}</dd>

                    <dt class="text-muted-foreground">Route name</dt>
                    <dd class="font-mono">
                        {{ requestRecord.route_name || '-' }}
                    </dd>

                    <dt class="text-muted-foreground">Action</dt>
                    <dd class="font-mono break-all">
                        {{ requestRecord.route_action || '-' }}
                    </dd>

                    <dt class="text-muted-foreground">IP</dt>
                    <dd class="font-mono">{{ requestRecord.ip || '-' }}</dd>

                    <dt class="text-muted-foreground">Trace ID</dt>
                    <dd class="font-mono">
                        {{ requestRecord.trace_id || '-' }}
                    </dd>

                    <dt class="text-muted-foreground">Occurred at</dt>
                    <dd>{{ formatDateTime(requestRecord.occurred_at) }}</dd>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
