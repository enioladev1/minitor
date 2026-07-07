<script setup lang="ts">
import {
    CheckIcon as Check,
    Copy01Icon as Copy,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { useClipboard } from '@vueuse/core';
import { Button } from '@/components/ui/button';

const props = defineProps<{ text: string }>();

const { copy, copied } = useClipboard();
</script>

<template>
    <div class="relative min-w-0">
        <pre
            class="overflow-x-auto rounded-md border bg-muted p-3 pr-10 font-mono text-xs"
            >{{ props.text }}</pre>
        <Button
            type="button"
            size="icon"
            variant="ghost"
            class="absolute top-1.5 right-1.5 size-7"
            @click="copy(props.text)"
        >
            <HugeiconsIcon :icon="Check" v-if="copied" class="size-3.5" />
            <HugeiconsIcon :icon="Copy" v-else class="size-3.5" />
            <span class="sr-only">Copy</span>
        </Button>
    </div>
</template>
