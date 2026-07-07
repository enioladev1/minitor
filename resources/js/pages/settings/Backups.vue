<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import BackupController from '@/actions/App/Http/Controllers/Settings/BackupController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/signal';
import { edit } from '@/routes/backups';

const props = defineProps<{
    settings: {
        enabled: boolean;
        s3_endpoint: string | null;
        s3_region: string;
        s3_bucket: string | null;
        s3_path_prefix: string | null;
        retention_count: number;
        has_credentials: boolean;
        last_run_at: string | null;
        last_status: string | null;
        last_error: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Backup settings',
                href: edit(),
            },
        ],
    },
});

// A plain <Form> can only ever point at one action, but "test connection"
// needs to send whatever's currently typed - including fields that
// haven't been saved yet - to a *different* endpoint than "Save". A
// reactive useForm() lets both buttons share the same field state.
const form = useForm({
    enabled: props.settings.enabled,
    s3_bucket: props.settings.s3_bucket ?? '',
    s3_access_key_id: '',
    s3_secret_access_key: '',
    s3_endpoint: props.settings.s3_endpoint ?? '',
    s3_region: props.settings.s3_region,
    s3_path_prefix: props.settings.s3_path_prefix ?? '',
    retention_count: props.settings.retention_count,
});

function save() {
    form.put(BackupController.update().url, { preserveScroll: true });
}

function testConnection() {
    form.post(BackupController.testConnection().url, {
        preserveScroll: true,
    });
}

function runNow() {
    router.post(BackupController.run().url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Backup settings" />

    <h1 class="sr-only">Backup settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Database backups"
            description="Dump the database daily at 00:00 and upload it to an S3-compatible bucket."
        />

        <div
            v-if="settings.last_run_at"
            class="rounded-md border p-3 text-sm"
        >
            <p>
                Last run:
                <span class="font-mono">{{
                    formatDateTime(settings.last_run_at)
                }}</span>
                <span
                    class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        settings.last_status === 'success'
                            ? 'bg-signal-good/15 text-signal-good'
                            : 'bg-signal-bad/15 text-signal-bad'
                    "
                >
                    {{ settings.last_status === 'success' ? 'Success' : 'Failed' }}
                </span>
            </p>
            <p v-if="settings.last_error" class="mt-1 text-muted-foreground">
                {{ settings.last_error }}
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="save">
            <div class="flex items-start gap-3">
                <Checkbox
                    id="enabled"
                    v-model="form.enabled"
                    class="mt-1"
                />
                <div class="space-y-1">
                    <Label for="enabled">Enable daily backups</Label>
                    <p class="text-sm text-muted-foreground">
                        Runs automatically at 00:00 server time once a
                        bucket, access key, and secret key are set below.
                    </p>
                </div>
            </div>
            <InputError :message="form.errors.enabled" />

            <div class="grid gap-2">
                <Label for="s3_bucket">Bucket</Label>
                <Input
                    id="s3_bucket"
                    v-model="form.s3_bucket"
                    placeholder="my-minitor-backups"
                />
                <InputError :message="form.errors.s3_bucket" />
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="s3_access_key_id">Access key ID</Label>
                    <Input
                        id="s3_access_key_id"
                        v-model="form.s3_access_key_id"
                        autocomplete="off"
                        :placeholder="
                            settings.has_credentials
                                ? 'Unchanged'
                                : 'AKIA...'
                        "
                    />
                    <InputError :message="form.errors.s3_access_key_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="s3_secret_access_key">Secret access key</Label>
                    <Input
                        id="s3_secret_access_key"
                        v-model="form.s3_secret_access_key"
                        type="password"
                        autocomplete="off"
                        :placeholder="
                            settings.has_credentials
                                ? 'Unchanged'
                                : 'Secret key'
                        "
                    />
                    <InputError :message="form.errors.s3_secret_access_key" />
                </div>
            </div>
            <p
                v-if="settings.has_credentials"
                class="-mt-4 text-xs text-muted-foreground"
            >
                A key and secret are already saved. Leave these two blank
                to keep them, or fill in both to replace them.
            </p>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="s3_endpoint">Endpoint</Label>
                    <Input
                        id="s3_endpoint"
                        v-model="form.s3_endpoint"
                        placeholder="https://s3.us-east-1.amazonaws.com"
                    />
                    <p class="text-xs text-muted-foreground">
                        Your provider's S3 endpoint - AWS S3, R2, MinIO,
                        Spaces, etc.
                    </p>
                    <InputError :message="form.errors.s3_endpoint" />
                </div>

                <div class="grid gap-2">
                    <Label for="s3_region">Region</Label>
                    <Input
                        id="s3_region"
                        v-model="form.s3_region"
                        placeholder="auto"
                    />
                    <InputError :message="form.errors.s3_region" />
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="s3_path_prefix">Path prefix</Label>
                    <Input
                        id="s3_path_prefix"
                        v-model="form.s3_path_prefix"
                        placeholder="minitor-backups"
                    />
                    <InputError :message="form.errors.s3_path_prefix" />
                </div>

                <div class="grid gap-2">
                    <Label for="retention_count">Keep last N backups</Label>
                    <Input
                        id="retention_count"
                        v-model.number="form.retention_count"
                        type="number"
                        min="1"
                        max="365"
                    />
                    <InputError :message="form.errors.retention_count" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <Button type="submit" :disabled="form.processing"
                    >Save</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="testConnection"
                >
                    Test connection
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="!settings.has_credentials"
                    @click="runNow"
                >
                    Run backup now
                </Button>
            </div>
        </form>
    </div>
</template>
