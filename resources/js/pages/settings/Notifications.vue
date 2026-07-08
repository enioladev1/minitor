<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import NotificationController from '@/actions/App/Http/Controllers/Settings/NotificationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/signal';
import { edit } from '@/routes/notifications';

const props = defineProps<{
    settings: {
        enabled: boolean;
        provider: 'smtp' | 'postmark' | 'resend' | 'mailgun' | null;
        from_address: string | null;
        from_name: string | null;
        notify_email: string | null;
        notify_on_reopen: boolean;
        smtp_host: string | null;
        smtp_port: number | null;
        smtp_encryption: 'tls' | 'ssl' | null;
        mailgun_domain: string | null;
        mailgun_endpoint: string | null;
        has_smtp_username: boolean;
        has_smtp_password: boolean;
        has_postmark_token: boolean;
        has_resend_api_key: boolean;
        has_mailgun_secret: boolean;
        has_credentials: boolean;
        last_test_at: string | null;
        last_test_status: string | null;
        last_test_error: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Notification settings',
                href: edit(),
            },
        ],
    },
});

// A plain <Form> can only ever point at one action, but "send test email"
// needs to send whatever's currently typed - including fields that haven't
// been saved yet - to a *different* endpoint than "Save". A reactive
// useForm() lets both buttons share the same field state.
const form = useForm({
    enabled: props.settings.enabled,
    provider: props.settings.provider ?? '',
    from_address: props.settings.from_address ?? '',
    from_name: props.settings.from_name ?? '',
    notify_email: props.settings.notify_email ?? '',
    notify_on_reopen: props.settings.notify_on_reopen,
    smtp_host: props.settings.smtp_host ?? '',
    smtp_port: props.settings.smtp_port ?? '',
    smtp_username: '',
    smtp_password: '',
    smtp_encryption: props.settings.smtp_encryption ?? '',
    postmark_token: '',
    resend_api_key: '',
    mailgun_domain: props.settings.mailgun_domain ?? '',
    mailgun_secret: '',
    mailgun_endpoint: props.settings.mailgun_endpoint ?? 'api.mailgun.net',
});

const providerLabels: Record<string, string> = {
    smtp: 'SMTP',
    postmark: 'Postmark',
    resend: 'Resend',
    mailgun: 'Mailgun',
};

const selectedProviderLabel = computed(
    () => providerLabels[form.provider] ?? 'Choose a provider',
);

function save() {
    form.put(NotificationController.update().url, { preserveScroll: true });
}

function sendTest() {
    form.post(NotificationController.sendTest().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Notification settings" />

    <h1 class="sr-only">Notification settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Email notifications"
            description="Get an email the instant a new issue is detected in one of your projects."
        />

        <div
            v-if="settings.last_test_at"
            class="rounded-md border p-3 text-sm"
        >
            <p>
                Last test:
                <span class="font-mono">{{
                    formatDateTime(settings.last_test_at)
                }}</span>
                <span
                    class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        settings.last_test_status === 'success'
                            ? 'bg-signal-good/15 text-signal-good'
                            : 'bg-signal-bad/15 text-signal-bad'
                    "
                >
                    {{
                        settings.last_test_status === 'success'
                            ? 'Success'
                            : 'Failed'
                    }}
                </span>
            </p>
            <p
                v-if="settings.last_test_error"
                class="mt-1 text-muted-foreground"
            >
                {{ settings.last_test_error }}
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="save">
            <div class="flex items-start gap-3">
                <Checkbox id="enabled" v-model="form.enabled" class="mt-1" />
                <div class="space-y-1">
                    <Label for="enabled">Enable email notifications</Label>
                    <p class="text-sm text-muted-foreground">
                        Sends instantly once a provider and its credentials
                        are set below.
                    </p>
                </div>
            </div>
            <InputError :message="form.errors.enabled" />

            <div class="grid gap-2">
                <Label for="provider">Provider</Label>
                <Select v-model="form.provider">
                    <SelectTrigger id="provider" class="w-full sm:w-64">
                        <SelectValue :placeholder="selectedProviderLabel" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="smtp">SMTP</SelectItem>
                        <SelectItem value="postmark">Postmark</SelectItem>
                        <SelectItem value="resend">Resend</SelectItem>
                        <SelectItem value="mailgun">Mailgun</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.provider" />
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="from_address">From address</Label>
                    <Input
                        id="from_address"
                        v-model="form.from_address"
                        type="email"
                        placeholder="alerts@yourdomain.com"
                    />
                    <InputError :message="form.errors.from_address" />
                </div>

                <div class="grid gap-2">
                    <Label for="from_name">From name</Label>
                    <Input
                        id="from_name"
                        v-model="form.from_name"
                        placeholder="MINITOR"
                    />
                    <InputError :message="form.errors.from_name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notify_email">Send alerts to</Label>
                <Input
                    id="notify_email"
                    v-model="form.notify_email"
                    type="email"
                    placeholder="you@yourdomain.com"
                />
                <p class="text-xs text-muted-foreground">
                    Leave blank to use your account email.
                </p>
                <InputError :message="form.errors.notify_email" />
            </div>

            <div class="flex items-start gap-3">
                <Checkbox
                    id="notify_on_reopen"
                    v-model="form.notify_on_reopen"
                    class="mt-1"
                />
                <div class="space-y-1">
                    <Label for="notify_on_reopen"
                        >Notify on reopened issues</Label
                    >
                    <p class="text-sm text-muted-foreground">
                        Also email when an issue you'd marked resolved
                        happens again, not just brand-new issues.
                    </p>
                </div>
            </div>
            <InputError :message="form.errors.notify_on_reopen" />

            <div v-if="form.provider === 'smtp'" class="space-y-4">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="smtp_host">Host</Label>
                        <Input
                            id="smtp_host"
                            v-model="form.smtp_host"
                            placeholder="smtp.yourprovider.com"
                        />
                        <InputError :message="form.errors.smtp_host" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="smtp_port">Port</Label>
                        <Input
                            id="smtp_port"
                            v-model.number="form.smtp_port"
                            type="number"
                            placeholder="587"
                        />
                        <InputError :message="form.errors.smtp_port" />
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="smtp_username">Username</Label>
                        <Input
                            id="smtp_username"
                            v-model="form.smtp_username"
                            autocomplete="off"
                            :placeholder="
                                settings.has_smtp_username
                                    ? 'Unchanged'
                                    : 'Username'
                            "
                        />
                        <InputError :message="form.errors.smtp_username" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="smtp_password">Password</Label>
                        <Input
                            id="smtp_password"
                            v-model="form.smtp_password"
                            type="password"
                            autocomplete="off"
                            :placeholder="
                                settings.has_smtp_password
                                    ? 'Unchanged'
                                    : 'Password'
                            "
                        />
                        <InputError :message="form.errors.smtp_password" />
                    </div>
                </div>

                <div class="grid gap-2 sm:w-64">
                    <Label for="smtp_encryption">Encryption</Label>
                    <Select v-model="form.smtp_encryption">
                        <SelectTrigger id="smtp_encryption">
                            <SelectValue placeholder="None" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="tls">TLS</SelectItem>
                            <SelectItem value="ssl">SSL</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.smtp_encryption" />
                </div>
            </div>

            <div v-else-if="form.provider === 'postmark'" class="grid gap-2">
                <Label for="postmark_token">Server API token</Label>
                <Input
                    id="postmark_token"
                    v-model="form.postmark_token"
                    type="password"
                    autocomplete="off"
                    :placeholder="
                        settings.has_postmark_token ? 'Unchanged' : 'Token'
                    "
                />
                <InputError :message="form.errors.postmark_token" />
            </div>

            <div v-else-if="form.provider === 'resend'" class="grid gap-2">
                <Label for="resend_api_key">API key</Label>
                <Input
                    id="resend_api_key"
                    v-model="form.resend_api_key"
                    type="password"
                    autocomplete="off"
                    :placeholder="
                        settings.has_resend_api_key
                            ? 'Unchanged'
                            : 're_...'
                    "
                />
                <InputError :message="form.errors.resend_api_key" />
            </div>

            <div v-else-if="form.provider === 'mailgun'" class="space-y-4">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="mailgun_domain">Domain</Label>
                        <Input
                            id="mailgun_domain"
                            v-model="form.mailgun_domain"
                            placeholder="mg.yourdomain.com"
                        />
                        <InputError :message="form.errors.mailgun_domain" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="mailgun_secret">Private API key</Label>
                        <Input
                            id="mailgun_secret"
                            v-model="form.mailgun_secret"
                            type="password"
                            autocomplete="off"
                            :placeholder="
                                settings.has_mailgun_secret
                                    ? 'Unchanged'
                                    : 'key-...'
                            "
                        />
                        <InputError :message="form.errors.mailgun_secret" />
                    </div>
                </div>

                <div class="grid gap-2 sm:w-64">
                    <Label for="mailgun_endpoint">API endpoint</Label>
                    <Input
                        id="mailgun_endpoint"
                        v-model="form.mailgun_endpoint"
                        placeholder="api.mailgun.net"
                    />
                    <p class="text-xs text-muted-foreground">
                        Use api.eu.mailgun.net for EU-region domains.
                    </p>
                    <InputError :message="form.errors.mailgun_endpoint" />
                </div>
            </div>

            <p
                v-if="settings.has_credentials"
                class="text-xs text-muted-foreground"
            >
                Credentials are already saved for this provider. Leave
                secret fields blank to keep them, or fill them in to
                replace them.
            </p>

            <div class="flex flex-wrap items-center gap-4">
                <Button type="submit" :disabled="form.processing"
                    >Save</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="sendTest"
                >
                    Send test email
                </Button>
            </div>
        </form>
    </div>
</template>
