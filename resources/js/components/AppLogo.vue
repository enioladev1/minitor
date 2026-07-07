<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';

const { state } = useSidebar();
</script>

<template>
    <img
        v-if="state === 'collapsed'"
        src="/images/favicon.svg"
        alt="MINITOR"
        class="size-8 rounded-md"
    />
    <img
        v-else
        src="/images/logo.png"
        alt="MINITOR"
        class="h-8 w-auto rounded-md"
    />
</template>
