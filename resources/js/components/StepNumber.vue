<script setup lang="ts">
defineProps<{ n: number }>();
</script>

<template>
    <span
        class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted font-mono text-xs text-muted-foreground"
    >
        {{ n }}
    </span>
</template>
