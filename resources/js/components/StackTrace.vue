<script setup lang="ts">
import {
    ChevronDownIcon as ChevronDown,
    ChevronRightIcon as ChevronRight,
    Folder01Icon as Folder,
    InformationCircleIcon as Info,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { computed, reactive } from 'vue';

type Frame = {
    file: string;
    source: string;
    code: Record<string, string> | null;
};
type Row =
    | { type: 'frame'; key: string; frame: Frame }
    | { type: 'entrypoint'; key: string; frame: Frame }
    | { type: 'group'; key: string; frames: Frame[] };

const props = defineProps<{ trace: string | null }>();

function isVendorFrame(file: string): boolean {
    return file.startsWith('vendor/');
}

function isEntrypoint(file: string): boolean {
    return file.startsWith('public/');
}

const rows = computed<Row[]>(() => {
    if (!props.trace) {
        return [];
    }

    let frames: Frame[];

    try {
        frames = JSON.parse(props.trace);
    } catch {
        return [];
    }

    if (!Array.isArray(frames) || frames.length === 0) {
        return [];
    }

    // The first frame is the exception's own throw location — its file:line
    // is already shown in the issue header, so skip the redundant repeat.
    const working = frames.length > 1 ? frames.slice(1) : frames;

    let entrypoint: Frame | null = null;
    const rest = [...working];

    if (rest.length > 0 && isEntrypoint(rest[rest.length - 1].file)) {
        entrypoint = rest.pop()!;
    }

    const result: Row[] = [];
    let vendorGroup: Frame[] = [];
    let groupIndex = 0;

    function flushGroup() {
        if (vendorGroup.length > 0) {
            result.push({
                type: 'group',
                key: `group-${groupIndex++}`,
                frames: vendorGroup,
            });
            vendorGroup = [];
        }
    }

    rest.forEach((frame, i) => {
        if (isVendorFrame(frame.file)) {
            vendorGroup.push(frame);
        } else {
            flushGroup();
            result.push({ type: 'frame', key: `frame-${i}`, frame });
        }
    });
    flushGroup();

    if (entrypoint) {
        result.push({
            type: 'entrypoint',
            key: 'entrypoint',
            frame: entrypoint,
        });
    }

    return result;
});

const expanded = reactive<Record<string, boolean>>({});

function toggle(key: string) {
    expanded[key] = !expanded[key];
}

function hasCode(frame: Frame): boolean {
    return !!frame.code && Object.keys(frame.code).length > 0;
}

function crashLine(file: string): number | null {
    const match = file.match(/:(\d+)$/);

    return match ? Number(match[1]) : null;
}

function codeLines(frame: Frame): Array<{ line: number; text: string }> {
    if (!frame.code) {
        return [];
    }

    return Object.entries(frame.code)
        .map(([line, text]) => ({ line: Number(line), text }))
        .sort((a, b) => a.line - b.line);
}
</script>

<template>
    <div class="space-y-1 overflow-x-auto rounded-md border">
        <template v-for="row in rows" :key="row.key">
            <button
                v-if="row.type === 'group'"
                type="button"
                class="flex w-full items-center justify-between border-b px-3 py-2 text-left text-xs text-muted-foreground last:border-b-0 hover:bg-muted/50"
                @click="toggle(row.key)"
            >
                <span class="flex items-center gap-2">
                    <HugeiconsIcon :icon="Folder" class="size-3.5" />
                    {{ row.frames.length }} vendor frame{{
                        row.frames.length === 1 ? '' : 's'
                    }}
                </span>
                <HugeiconsIcon
                    :icon="expanded[row.key] ? ChevronDown : ChevronRight"
                    class="size-3.5"
                />
            </button>

            <div
                v-if="row.type === 'group' && expanded[row.key]"
                class="border-b bg-muted/20 last:border-b-0"
            >
                <div
                    v-for="(frame, i) in row.frames"
                    :key="i"
                    class="flex items-center justify-between gap-4 border-b border-dashed px-3 py-1.5 pl-8 text-xs last:border-b-0"
                >
                    <span
                        class="min-w-0 truncate font-mono text-muted-foreground"
                        >{{ frame.source || '—' }}</span
                    >
                    <span class="shrink-0 font-mono text-muted-foreground/70">{{
                        frame.file
                    }}</span>
                </div>
            </div>

            <template v-if="row.type === 'frame'">
                <component
                    :is="hasCode(row.frame) ? 'button' : 'div'"
                    type="button"
                    class="flex w-full items-center justify-between gap-4 border-b px-3 py-2 text-left text-xs last:border-b-0"
                    :class="hasCode(row.frame) ? 'hover:bg-muted/50' : ''"
                    @click="hasCode(row.frame) && toggle(row.key)"
                >
                    <span class="min-w-0 truncate font-mono">{{
                        row.frame.source || '—'
                    }}</span>
                    <span
                        class="flex shrink-0 items-center gap-2 font-mono text-muted-foreground"
                    >
                        {{ row.frame.file }}
                        <HugeiconsIcon
                            v-if="hasCode(row.frame)"
                            :icon="
                                expanded[row.key] ? ChevronDown : ChevronRight
                            "
                            class="size-3.5"
                        />
                    </span>
                </component>

                <div
                    v-if="hasCode(row.frame) && expanded[row.key]"
                    class="border-b bg-muted/20 pl-0 last:border-b-0"
                >
                    <div
                        v-for="line in codeLines(row.frame)"
                        :key="line.line"
                        class="flex gap-3 px-3 py-0.5 font-mono text-xs"
                        :class="
                            line.line === crashLine(row.frame.file)
                                ? 'bg-signal-bad/15 text-signal-bad'
                                : 'text-muted-foreground'
                        "
                    >
                        <span class="w-8 shrink-0 text-right opacity-60">{{
                            line.line
                        }}</span>
                        <span class="min-w-0 whitespace-pre">{{
                            line.text
                        }}</span>
                    </div>
                </div>
            </template>

            <div
                v-if="row.type === 'entrypoint'"
                class="flex items-center justify-between gap-4 px-3 py-2 text-xs text-muted-foreground"
            >
                <span class="flex items-center gap-2">
                    <HugeiconsIcon :icon="Info" class="size-3.5" />
                    Entrypoint
                </span>
                <span class="shrink-0 font-mono">{{ row.frame.file }}</span>
            </div>
        </template>

        <p v-if="rows.length === 0" class="p-3 text-xs text-muted-foreground">
            No stack trace captured.
        </p>
    </div>
</template>
