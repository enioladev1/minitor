<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <p
            v-if="description && variant === 'small'"
            class="mb-0.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
        >
            {{ description }}
        </p>
        <h2
            :class="
                variant === 'small'
                    ? 'text-2xl font-semibold tracking-tight'
                    : 'text-xl font-semibold tracking-tight'
            "
        >
            {{ title }}
        </h2>
        <p
            v-if="description && variant !== 'small'"
            class="text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
    </header>
</template>
