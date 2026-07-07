<script setup lang="ts">
import {
    ArrowUpRight01Icon as ArrowUpRight,
    BracesIcon as Braces,
    Calendar01Icon as Calendar,
    Database01Icon as Database,
    GlobeIcon as Globe,
    HardDriveIcon as HardDrive,
    LayoutGridIcon as LayoutGrid,
    CheckListIcon as ListChecks,
    Scroll01Icon as ScrollText,
    Alert01Icon as TriangleAlert,
    UserIcon as User,
    Briefcase01Icon as Briefcase,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Link } from '@inertiajs/vue3';
import { index as cacheIndex } from '@/actions/App/Http/Controllers/ProjectCacheEventController';
import { index as commandsIndex } from '@/actions/App/Http/Controllers/ProjectCommandController';
import { index as exceptionsIndex } from '@/actions/App/Http/Controllers/ProjectExceptionController';
import { index as issuesIndex } from '@/actions/App/Http/Controllers/ProjectIssueController';
import { index as jobsIndex } from '@/actions/App/Http/Controllers/ProjectJobController';
import { index as logsIndex } from '@/actions/App/Http/Controllers/ProjectLogController';
import { index as outgoingRequestsIndex } from '@/actions/App/Http/Controllers/ProjectOutgoingRequestController';
import ProjectOverviewController from '@/actions/App/Http/Controllers/ProjectOverviewController';
import { index as queriesIndex } from '@/actions/App/Http/Controllers/ProjectQueryController';
import { index as requestsIndex } from '@/actions/App/Http/Controllers/ProjectRequestController';
import { index as scheduledTasksIndex } from '@/actions/App/Http/Controllers/ProjectScheduledTaskController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/ProjectUserController';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const props = defineProps<{ projectId: number }>();

const { isCurrentUrl } = useCurrentUrl();

const top = [
    {
        title: 'Dashboard',
        href: ProjectOverviewController(props.projectId),
        icon: LayoutGrid,
    },
    { title: 'Issues', href: issuesIndex(props.projectId), icon: ListChecks },
];

const activity = [
    { title: 'Requests', href: requestsIndex(props.projectId), icon: Globe },
    { title: 'Jobs', href: jobsIndex(props.projectId), icon: Briefcase },
    { title: 'Commands', href: commandsIndex(props.projectId), icon: Braces },
    {
        title: 'Scheduled Tasks',
        href: scheduledTasksIndex(props.projectId),
        icon: Calendar,
    },
    {
        title: 'Exceptions',
        href: exceptionsIndex(props.projectId),
        icon: TriangleAlert,
    },
    { title: 'Queries', href: queriesIndex(props.projectId), icon: Database },
    { title: 'Cache', href: cacheIndex(props.projectId), icon: HardDrive },
    {
        title: 'Outgoing Requests',
        href: outgoingRequestsIndex(props.projectId),
        icon: ArrowUpRight,
    },
];

const monitoring = [
    { title: 'Users', href: usersIndex(props.projectId), icon: User },
    { title: 'Logs', href: logsIndex(props.projectId), icon: ScrollText },
];
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem v-for="item in top" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <HugeiconsIcon :icon="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>

    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Activity</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in activity" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <HugeiconsIcon :icon="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>

    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Monitoring</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in monitoring" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <HugeiconsIcon :icon="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
