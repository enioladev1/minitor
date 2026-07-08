<script setup lang="ts">
import { Settings01Icon as Settings } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Form, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    update,
    regenerateToken,
    destroy,
} from '@/actions/App/Http/Controllers/ProjectController';
import CodeSnippet from '@/components/CodeSnippet.vue';
import InputError from '@/components/InputError.vue';
import StepNumber from '@/components/StepNumber.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
        project: {
            id: number;
            name: string;
            token: string;
            local_secret: string;
            notify_on_new_issue: boolean;
        };
        defaultOpen?: boolean;
    }>(),
    { defaultOpen: false },
);

const envSnippet = computed(
    () =>
        `NIGHTWATCH_TOKEN=${props.project.local_secret}\n` +
        `MINITOR_SERVER_URL=${window.location.origin}\n` +
        `MINITOR_PROJECT_TOKEN=${props.project.token}`,
);

function confirmRegenerate() {
    if (
        confirm(
            "Regenerate this project's key? The old key will stop working immediately - update every app using it.",
        )
    ) {
        router.post(
            regenerateToken(props.project.id).url,
            {},
            { preserveScroll: true },
        );
    }
}

function toggleNotifyOnNewIssue(checked: boolean | 'indeterminate') {
    router.patch(
        update(props.project.id).url,
        { name: props.project.name, notify_on_new_issue: checked === true },
        { preserveScroll: true },
    );
}

function confirmDelete() {
    if (
        confirm(
            `Delete "${props.project.name}"? This permanently deletes all of its collected data.`,
        )
    ) {
        router.delete(destroy(props.project.id).url);
    }
}
</script>

<template>
    <Dialog :default-open="defaultOpen">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">
                <HugeiconsIcon :icon="Settings" class="size-4" />
                Settings
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ project.name }} settings</DialogTitle>
                <DialogDescription>
                    Manage this project's name, key, and installation.
                </DialogDescription>
            </DialogHeader>

            <div class="min-w-0 space-y-6">
                <ol class="space-y-4">
                    <li class="flex gap-3">
                        <StepNumber :n="1" />
                        <div class="min-w-0 flex-1 space-y-1.5 pt-0.5">
                            <p class="text-sm font-medium">Install the agent</p>
                            <p class="text-sm text-muted-foreground">
                                On the Laravel app you want to monitor:
                            </p>
                            <CodeSnippet
                                text="composer require minitor/agent"
                            />
                        </div>
                    </li>

                    <li class="flex gap-3">
                        <StepNumber :n="2" />
                        <div class="min-w-0 flex-1 space-y-1.5 pt-0.5">
                            <p class="text-sm font-medium">
                                Add these to its .env
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Both values are generated for this project -
                                nothing to invent.
                            </p>
                            <CodeSnippet :text="envSnippet" />
                        </div>
                    </li>

                    <li class="flex gap-3">
                        <StepNumber :n="3" />
                        <div class="min-w-0 flex-1 space-y-1.5 pt-0.5">
                            <p class="text-sm font-medium">Start the agent</p>
                            <p class="text-sm text-muted-foreground">
                                Keep it running (Supervisor, systemd, your
                                process manager):
                            </p>
                            <CodeSnippet text="php artisan minitor:agent" />
                        </div>
                    </li>
                </ol>

                <div class="space-y-3 border-t pt-6">
                    <Form
                        v-bind="update.form(project.id)"
                        :options="{ preserveScroll: true }"
                        class="space-y-2"
                        v-slot="{ errors, processing }"
                    >
                        <Label for="name">Project name</Label>
                        <div class="flex gap-2">
                            <Input
                                id="name"
                                name="name"
                                :default-value="project.name"
                            />
                            <Button
                                type="submit"
                                :disabled="processing"
                                variant="secondary"
                                >Save</Button
                            >
                        </div>
                        <InputError :message="errors.name" />
                    </Form>

                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="notify_on_new_issue"
                            class="mt-1"
                            :model-value="project.notify_on_new_issue"
                            @update:model-value="toggleNotifyOnNewIssue"
                        />
                        <div class="space-y-1">
                            <Label for="notify_on_new_issue"
                                >Email me on new issues</Label
                            >
                            <p class="text-sm text-muted-foreground">
                                Turn off to mute this project without
                                touching your global notification settings.
                            </p>
                        </div>
                    </div>

                    <div
                        class="space-y-2 rounded-md border border-destructive/30 p-3"
                    >
                        <p class="text-sm font-medium text-destructive">
                            Danger zone
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="confirmRegenerate"
                            >
                                Regenerate key
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                @click="confirmDelete"
                            >
                                Delete project
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
