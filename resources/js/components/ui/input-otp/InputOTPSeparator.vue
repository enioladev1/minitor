<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { HugeiconsIcon } from '@hugeicons/vue';
import { MinusSignIcon as MinusIcon } from '@hugeicons/core-free-icons';
import { useForwardProps } from "reka-ui"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()

const forwarded = useForwardProps(props)
</script>

<template>
  <div
    data-slot="input-otp-separator"
    role="separator"
    v-bind="forwarded"
  >
    <slot>
      <HugeiconsIcon :icon="MinusIcon"  />
    </slot>
  </div>
</template>
