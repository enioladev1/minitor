<script lang="ts" setup>
import type { HTMLAttributes } from "vue"
import { HugeiconsIcon } from '@hugeicons/vue';
import { ChevronRightIcon as ChevronRight } from '@hugeicons/core-free-icons';
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <li
    data-slot="breadcrumb-separator"
    role="presentation"
    aria-hidden="true"
    :class="cn('[&>svg]:size-3.5', props.class)"
  >
    <slot>
      <HugeiconsIcon :icon="ChevronRight"  />
    </slot>
  </li>
</template>
