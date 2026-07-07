<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { HugeiconsIcon } from '@hugeicons/vue';
import { CheckmarkCircle02Icon as CircleCheckIcon, InformationCircleIcon as InfoIcon, Loading03Icon as Loader2Icon, CancelCircleIcon as OctagonXIcon, Alert01Icon as TriangleAlertIcon, Cancel01Icon as XIcon } from '@hugeicons/core-free-icons';
import { Toaster as Sonner } from "vue-sonner"
import { cn } from "@/lib/utils"

import 'vue-sonner/style.css';

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--border-radius': 'var(--radius)',
    }"
    v-bind="props"
  >
    <template #success-icon>
      <HugeiconsIcon :icon="CircleCheckIcon" class="size-4" />
    </template>
    <template #info-icon>
      <HugeiconsIcon :icon="InfoIcon" class="size-4" />
    </template>
    <template #warning-icon>
      <HugeiconsIcon :icon="TriangleAlertIcon" class="size-4" />
    </template>
    <template #error-icon>
      <HugeiconsIcon :icon="OctagonXIcon" class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <HugeiconsIcon :icon="Loader2Icon" class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <HugeiconsIcon :icon="XIcon" class="size-4" />
    </template>
  </Sonner>
</template>
