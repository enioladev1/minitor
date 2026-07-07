<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{ modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const ranges = [
    { key: '1h', label: '1H' },
    { key: '24h', label: '24H' },
    { key: '7d', label: '7D' },
    { key: '14d', label: '14D' },
    { key: '30d', label: '30D' },
];
</script>

<template>
    <div class="flex gap-1 rounded-md border p-1">
        <Button
            v-for="range in ranges"
            :key="range.key"
            size="sm"
            :variant="modelValue === range.key ? 'default' : 'ghost'"
            class="h-7 px-2.5 text-xs"
            @click="emit('update:modelValue', range.key)"
        >
            {{ range.label }}
        </Button>
    </div>
</template>
