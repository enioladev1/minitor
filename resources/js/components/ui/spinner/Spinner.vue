<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { HugeiconsIcon } from '@hugeicons/vue';
import { Loading03Icon as Loader2Icon } from '@hugeicons/core-free-icons';
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <HugeiconsIcon :icon="Loader2Icon"
    role="status"
    aria-label="Loading"
    :class="cn('size-4 animate-spin', props.class)" />
</template>
