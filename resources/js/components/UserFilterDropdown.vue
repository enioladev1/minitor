<script setup lang="ts">
import {
    CheckIcon as Check,
    ChevronDownIcon as ChevronDown,
    SecurityCheckIcon as ShieldCheck,
    UserMultipleIcon as Users,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

type UserOption = { external_id: string; label: string };

const props = defineProps<{
    modelValue: string | undefined;
    users: UserOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | undefined];
}>();

const search = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.users;
    }

    return props.users.filter(
        (u) =>
            u.label.toLowerCase().includes(term) ||
            u.external_id.includes(term),
    );
});

const currentLabel = computed(() => {
    if (!props.modelValue || props.modelValue === 'all') {
        return 'All Users';
    }

    if (props.modelValue === 'authenticated') {
        return 'Authenticated Users';
    }

    if (props.modelValue === 'guest') {
        return 'Guest Users';
    }

    return (
        props.users.find((u) => u.external_id === props.modelValue)?.label ??
        props.modelValue
    );
});

function select(value: string | undefined) {
    emit('update:modelValue', value);
    search.value = '';
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="gap-1.5">
                <HugeiconsIcon :icon="Users" class="size-4" />
                {{ currentLabel }}
                <HugeiconsIcon :icon="ChevronDown" class="size-3.5" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-72">
            <div class="p-1.5">
                <Input
                    v-model="search"
                    placeholder="Search users..."
                    class="h-8"
                />
            </div>
            <DropdownMenuItem @select="select('authenticated')">
                <HugeiconsIcon :icon="ShieldCheck" class="size-4" />
                Authenticated Users
                <HugeiconsIcon
                    :icon="Check"
                    v-if="modelValue === 'authenticated'"
                    class="ml-auto size-3.5"
                />
            </DropdownMenuItem>
            <DropdownMenuItem @select="select('guest')">
                <HugeiconsIcon :icon="Users" class="size-4" />
                Guest Users
                <HugeiconsIcon
                    :icon="Check"
                    v-if="modelValue === 'guest'"
                    class="ml-auto size-3.5"
                />
            </DropdownMenuItem>
            <DropdownMenuItem @select="select(undefined)">
                All Users
                <HugeiconsIcon
                    :icon="Check"
                    v-if="!modelValue || modelValue === 'all'"
                    class="ml-auto size-3.5"
                />
            </DropdownMenuItem>
            <div class="max-h-64 overflow-y-auto border-t">
                <DropdownMenuItem
                    v-for="user in filtered"
                    :key="user.external_id"
                    @select="select(user.external_id)"
                >
                    <span class="min-w-0 flex-1 truncate">
                        <span class="mr-1.5 text-muted-foreground">{{
                            user.external_id
                        }}</span>
                        {{ user.label }}
                    </span>
                    <HugeiconsIcon
                        :icon="Check"
                        v-if="modelValue === user.external_id"
                        class="ml-auto size-3.5 shrink-0"
                    />
                </DropdownMenuItem>
                <p
                    v-if="filtered.length === 0"
                    class="p-2 text-center text-xs text-muted-foreground"
                >
                    No users found.
                </p>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
