<script setup lang="ts">
import { computed, ref } from 'vue';
import { useChartHover } from '@/composables/useChartHover';
import { formatBucketDateTime, formatCompactNumber } from '@/lib/signal';

export type LineSeries = { key: string; label: string; color: string };
export type LineDatum = { bucket: string; [key: string]: string | number };

const props = withDefaults(
    defineProps<{
        data: LineDatum[];
        series: LineSeries[];
        height?: number;
        formatValue?: (value: number) => string;
    }>(),
    { height: 160 },
);

const containerRef = ref<HTMLElement | null>(null);
const { hoveredIndex, tooltipX, setHover, clearHover } = useChartHover();

const width = 640;
const marginLeft = 44;
const marginBottom = 22;
const marginTop = 8;
const plotWidth = width - marginLeft - 8;
const plotHeight = props.height - marginTop - marginBottom;

function num(value: string | number | undefined): number {
    return typeof value === 'number' ? value : 0;
}

const maxValue = computed(() => {
    const values = props.data.flatMap((d) =>
        props.series.map((s) => num(d[s.key])),
    );
    const max = Math.max(1, ...values);
    const magnitude = Math.pow(10, Math.floor(Math.log10(max)));

    return Math.ceil(max / magnitude) * magnitude;
});

const gridlines = computed(() =>
    [0, 0.25, 0.5, 0.75, 1].map((f) => Math.round(maxValue.value * f)),
);

const stepX = computed(() => plotWidth / Math.max(props.data.length - 1, 1));

function pointX(index: number): number {
    return marginLeft + index * stepX.value;
}

function pointY(value: number): number {
    return marginTop + plotHeight - (value / maxValue.value) * plotHeight;
}

const linePaths = computed(() =>
    props.series.map((s) => ({
        key: s.key,
        color: s.color,
        d: props.data
            .map(
                (d, i) =>
                    `${i === 0 ? 'M' : 'L'}${pointX(i)},${pointY(num(d[s.key]))}`,
            )
            .join(' '),
    })),
);

function onMove(event: PointerEvent) {
    if (!containerRef.value) {
        return;
    }

    const rect = containerRef.value.getBoundingClientRect();
    const relativeX = ((event.clientX - rect.left) / rect.width) * width;
    const index = Math.round((relativeX - marginLeft) / stepX.value);
    const clamped = Math.min(Math.max(index, 0), props.data.length - 1);
    setHover(clamped, containerRef.value, event.clientX, event.clientY);
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
                    :y1="pointY(gridValue)"
                    :y2="pointY(gridValue)"
                    class="stroke-border"
                    stroke-width="1"
                />
                <text
                    :x="marginLeft - 6"
                    :y="pointY(gridValue) + 3"
                    text-anchor="end"
                    class="fill-muted-foreground text-[9px]"
                >
                    {{ formatCompactNumber(gridValue) }}
                </text>
            </g>

            <line
                v-if="hoveredIndex !== null"
                :x1="pointX(hoveredIndex)"
                :x2="pointX(hoveredIndex)"
                :y1="marginTop"
                :y2="marginTop + plotHeight"
                class="stroke-border"
                stroke-width="1"
            />

            <path
                v-for="line in linePaths"
                :key="line.key"
                :d="line.d"
                fill="none"
                :stroke="line.color"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <g v-if="hoveredData">
                <circle
                    v-for="s in series"
                    :key="s.key"
                    :cx="pointX(hoveredIndex!)"
                    :cy="pointY(num(hoveredData[s.key]))"
                    r="4"
                    :fill="s.color"
                    stroke="var(--color-background)"
                    stroke-width="2"
                />
            </g>

            <rect
                :x="marginLeft"
                :y="marginTop"
                :width="plotWidth"
                :height="plotHeight"
                fill="transparent"
                @pointermove="onMove"
                @pointerenter="onMove"
            />
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
                class="inline-block h-0.5 w-2.5 rounded-full"
                :style="{ backgroundColor: s.color }"
            />
            {{ s.label }}
        </div>
    </div>
</template>
