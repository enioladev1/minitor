<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { HugeiconsIcon } from '@hugeicons/vue';
import { PanelLeftCloseIcon as PanelLeftClose, PanelLeftOpenIcon as PanelLeftOpen } from '@hugeicons/core-free-icons';
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <HugeiconsIcon :icon="PanelLeftOpen" v-if="isMobile || state === 'collapsed'" />
    <HugeiconsIcon :icon="PanelLeftClose" v-else />
    <span class="sr-only">Toggle sidebar</span>
  </Button>
</template>
