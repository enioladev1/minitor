<script setup lang="ts">
import { toneDotClasses } from '@/lib/signal';
import type { SignalTone } from '@/lib/signal';

defineProps<{ tone: SignalTone }>();
</script>

<template>
    <span
        class="inline-block size-2 shrink-0 rounded-full"
        :class="toneDotClasses[tone]"
        aria-hidden="true"
    />
</template>
