<script setup lang="ts">
import { computed, ref } from 'vue';
import { useChartHover } from '@/composables/useChartHover';
import { formatBucketDateTime, formatCompactNumber } from '@/lib/signal';

export type BarSeries = { key: string; label: string; color: string };
export type BarDatum = { bucket: string; [key: string]: string | number };

const props = withDefaults(
    defineProps<{
        data: BarDatum[];
        series: BarSeries[];
        height?: number;
        formatValue?: (value: number) => string;
    }>(),
    { height: 160 },
);

const containerRef = ref<HTMLElement | null>(null);
const { hoveredIndex, tooltipX, setHover, clearHover } = useChartHover();

const width = 640;
const marginLeft = 40;
const marginBottom = 22;
const marginTop = 8;
const plotWidth = width - marginLeft - 8;
const plotHeight = props.height - marginTop - marginBottom;

function num(value: string | number | undefined): number {
    return typeof value === 'number' ? value : 0;
}

const totals = computed(() =>
    props.data.map((d) =>
        props.series.reduce((sum, s) => sum + num(d[s.key]), 0),
    ),
);

const maxTotal = computed(() => {
    const max = Math.max(1, ...totals.value);
    // Round up to a "nice" ceiling so gridlines land on clean numbers.
    const magnitude = Math.pow(10, Math.floor(Math.log10(max)));

    return Math.ceil(max / magnitude) * magnitude;
});

const gridlines = computed(() =>
    [0, 0.25, 0.5, 0.75, 1].map((f) => Math.round(maxTotal.value * f)),
);

const bandWidth = computed(() => plotWidth / Math.max(props.data.length, 1));
const barWidth = computed(() => Math.min(24, bandWidth.value - 2));

function barX(index: number): number {
    return (
        marginLeft +
        index * bandWidth.value +
        (bandWidth.value - barWidth.value) / 2
    );
}

function scaleY(value: number): number {
    return (value / maxTotal.value) * plotHeight;
}

function topRoundedRectPath(
    x: number,
    y: number,
    w: number,
    h: number,
    r: number,
): string {
    if (h <= 0) {
        return '';
    }

    const radius = Math.min(r, w / 2, h);

    return `M${x},${y + h} L${x},${y + radius} Q${x},${y} ${x + radius},${y} L${x + w - radius},${y} Q${x + w},${y} ${x + w},${y + radius} L${x + w},${y + h} Z`;
}

const bars = computed(() =>
    props.data.map((d, index) => {
        const baseline = marginTop + plotHeight;
        let cumulative = 0;
        const segments = props.series
            .map((s) => ({ key: s.key, color: s.color, value: num(d[s.key]) }))
            .filter((s) => s.value > 0);

        return segments.map((segment, segmentIndex) => {
            const segmentHeight = Math.max(
                scaleY(segment.value) - 2,
                segment.value > 0 ? 1 : 0,
            );
            const y =
                baseline -
                cumulative -
                scaleY(segment.value) +
                (segmentIndex === 0 ? 2 : 0);
            cumulative += scaleY(segment.value);
            const isTopmost = segmentIndex === segments.length - 1;
            const x = barX(index);

            return {
                key: `${index}-${segment.key}`,
                color: segment.color,
                path: isTopmost
                    ? topRoundedRectPath(x, y, barWidth.value, segmentHeight, 4)
                    : `M${x},${y + segmentHeight} L${x},${y} L${x + barWidth.value},${y} L${x + barWidth.value},${y + segmentHeight} Z`,
            };
        });
    }),
);

function onHover(event: PointerEvent, index: number) {
    setHover(index, containerRef.value, event.clientX, event.clientY);
}

const hoveredData = computed(() =>
    hoveredIndex.value !== null ? props.data[hoveredIndex.value] : null,
);
const format = computed(() => props.formatValue ?? formatCompactNumber);
</script>

<template>
    <div ref="containerRef" class="relative" @pointerleave="clearHover">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="w-full"
            :height="height"
            preserveAspectRatio="none"
        >
            <g v-for="(gridValue, i) in gridlines" :key="i">
                <line
                    :x1="marginLeft"
                    :x2="width - 8"
                    :y1="marginTop + plotHeight - scaleY(gridValue)"
                    :y2="marginTop + plotHeight - scaleY(gridValue)"
                    class="stroke-border"
                    stroke-width="1"
                />
                <text
                    :x="marginLeft - 6"
                    :y="marginTop + plotHeight - scaleY(gridValue) + 3"
                    text-anchor="end"
                    class="fill-muted-foreground text-[9px]"
                >
                    {{ formatCompactNumber(gridValue) }}
                </text>
            </g>

            <g v-for="(segments, index) in bars" :key="index">
                <path
                    v-for="segment in segments"
                    :key="segment.key"
                    :d="segment.path"
                    :fill="segment.color"
                />
                <rect
                    :x="barX(index)"
                    :y="marginTop"
                    :width="barWidth"
                    :height="plotHeight"
                    fill="transparent"
                    class="cursor-pointer"
                    @pointermove="onHover($event, index)"
                    @pointerenter="onHover($event, index)"
                />
            </g>
        </svg>

        <div
            v-if="hoveredData"
            class="pointer-events-none absolute z-10 -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap shadow-md"
            :style="{ left: `${tooltipX}px`, top: '4px' }"
        >
            <p class="mb-1 font-medium text-popover-foreground">
                {{ formatBucketDateTime(hoveredData.bucket) }}
            </p>
            <div
                v-for="s in series"
                :key="s.key"
                class="flex items-center gap-1.5 text-muted-foreground"
            >
                <span
                    class="inline-block h-0.5 w-2.5 rounded-full"
                    :style="{ backgroundColor: s.color }"
                />
                <span class="font-mono text-popover-foreground">{{
                    format(num(hoveredData[s.key]))
                }}</span>
                <span>{{ s.label }}</span>
            </div>
        </div>
    </div>

    <div
        v-if="series.length > 1"
        class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
    >
        <div v-for="s in series" :key="s.key" class="flex items-center gap-1.5">
            <span
                class="inline-block size-2 rounded-full"
                :style="{ backgroundColor: s.color }"
            />
            {{ s.label }}
        </div>
    </div>
</template>
