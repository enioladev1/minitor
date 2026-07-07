<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 42 32"
        :class="className"
        v-bind="$attrs"
    >
        <path
            d="M2,26 L9,26 L15,6 L21,26 L27,6 L33,26 L37,26"
            fill="none"
            stroke="currentColor"
            stroke-width="3.4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <circle cx="37" cy="26" r="2.7" fill="currentColor" />
    </svg>
</template>
