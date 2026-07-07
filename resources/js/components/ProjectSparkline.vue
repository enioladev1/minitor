<script setup lang="ts">
import { computed, ref } from 'vue';
import { useChartHover } from '@/composables/useChartHover';
import { formatBucketDateTime, formatCompactNumber } from '@/lib/signal';

/**
 * A chrome-free "vitals strip" for the projects list - the same bucketed
 * request-volume data every detail page charts, just stripped of axes and
 * gridlines so a dozen of these can sit in a card grid without turning into
 * a wall of noise. Still keeps the hover crosshair: a glance chart is still
 * a chart.
 */
const props = withDefaults(
    defineProps<{
        data: Array<{ bucket: string; total: number }>;
        color: string;
        height?: number;
    }>(),
    { height: 40 },
);

const containerRef = ref<HTMLElement | null>(null);
const { hoveredIndex, tooltipX, setHover, clearHover } = useChartHover();

const width = 320;
const margin = 2;
const plotWidth = width - margin * 2;
const plotHeight = props.height - margin * 2;

const maxValue = computed(() => Math.max(1, ...props.data.map((d) => d.total)));
const stepX = computed(() => plotWidth / Math.max(props.data.length - 1, 1));

function pointX(index: number): number {
    return margin + index * stepX.value;
}

function pointY(value: number): number {
    return margin + plotHeight - (value / maxValue.value) * plotHeight;
}

const linePath = computed(() =>
    props.data
        .map((d, i) => `${i === 0 ? 'M' : 'L'}${pointX(i)},${pointY(d.total)}`)
        .join(' '),
);

const areaPath = computed(() => {
    if (props.data.length === 0) {
        return '';
    }

    const baseline = margin + plotHeight;

    return `${linePath.value} L${pointX(props.data.length - 1)},${baseline} L${pointX(0)},${baseline} Z`;
});

function onMove(event: PointerEvent) {
    if (!containerRef.value) {
        return;
    }

    const rect = containerRef.value.getBoundingClientRect();
    const relativeX = ((event.clientX - rect.left) / rect.width) * width;
    const index = Math.round((relativeX - margin) / stepX.value);
    const clamped = Math.min(Math.max(index, 0), props.data.length - 1);
    setHover(clamped, containerRef.value, event.clientX, event.clientY);
}

const hoveredData = computed(() =>
    hoveredIndex.value !== null ? props.data[hoveredIndex.value] : null,
);
</script>

<template>
    <div ref="containerRef" class="relative" @pointerleave="clearHover">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="w-full"
            :height="height"
            preserveAspectRatio="none"
        >
            <defs>
                <linearGradient
                    :id="`spark-fade-${color}`"
                    x1="0"
                    y1="0"
                    x2="0"
                    y2="1"
                >
                    <stop offset="0%" :stop-color="color" stop-opacity="0.25" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>

            <path :d="areaPath" :fill="`url(#spark-fade-${color})`" />
            <path
                :d="linePath"
                fill="none"
                :stroke="color"
                stroke-width="1.5"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <line
                v-if="hoveredIndex !== null"
                :x1="pointX(hoveredIndex)"
                :x2="pointX(hoveredIndex)"
                :y1="margin"
                :y2="margin + plotHeight"
                class="stroke-border"
                stroke-width="1"
            />

            <circle
                v-if="hoveredData"
                :cx="pointX(hoveredIndex!)"
                :cy="pointY(hoveredData.total)"
                r="2.5"
                :fill="color"
                stroke="var(--color-background)"
                stroke-width="1.5"
            />

            <rect
                :x="margin"
                :y="margin"
                :width="plotWidth"
                :height="plotHeight"
                fill="transparent"
                @pointermove="onMove"
                @pointerenter="onMove"
            />
        </svg>

        <div
            v-if="hoveredData"
            class="pointer-events-none absolute z-10 -translate-x-1/2 rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap shadow-md"
            :style="{ left: `${tooltipX}px`, top: '-2px' }"
        >
            <span class="font-mono text-popover-foreground">{{
                formatCompactNumber(hoveredData.total)
            }}</span>
            <span class="text-muted-foreground"
                >· {{ formatBucketDateTime(hoveredData.bucket) }}</span
            >
        </div>
    </div>
</template>
