import { ref } from 'vue';

/**
 * Shared hover/crosshair state for bar and line charts: which bucket index is
 * active, and where to place the tooltip (clamped to the chart's own bounds
 * so it never overflows past the card edge).
 */
export function useChartHover() {
    const hoveredIndex = ref<number | null>(null);
    const tooltipX = ref(0);
    const tooltipY = ref(0);

    function setHover(
        index: number,
        containerEl: HTMLElement | null,
        clientX: number,
        clientY: number,
    ) {
        hoveredIndex.value = index;

        if (!containerEl) {
            return;
        }

        const rect = containerEl.getBoundingClientRect();
        tooltipX.value = Math.min(
            Math.max(clientX - rect.left, 8),
            rect.width - 8,
        );
        tooltipY.value = clientY - rect.top;
    }

    function clearHover() {
        hoveredIndex.value = null;
    }

    return { hoveredIndex, tooltipX, tooltipY, setHover, clearHover };
}
