<script setup lang="ts">
import {
    ArrowLeft01Icon as ArrowLeft,
    LayoutGridIcon as LayoutGrid,
    Settings01Icon as Settings,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import ProjectNav from '@/components/ProjectNav.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Projects',
        href: ProjectController.index(),
        icon: LayoutGrid,
    },
    {
        title: 'Settings',
        href: edit(),
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [];

const page = usePage();
const project = computed(
    () =>
        (page.props.project as { id: number; name: string } | undefined) ??
        null,
);

// Close the mobile sidebar sheet automatically once a nav link finishes
// navigating — otherwise it stays open over the new page.
const { isMobile, setOpenMobile } = useSidebar();
const stopListening = router.on('navigate', () => {
    if (isMobile.value) {
        setOpenMobile(false);
    }
});
onUnmounted(stopListening);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem v-if="project">
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="ProjectController.index()">
                            <HugeiconsIcon
                                :icon="ArrowLeft"
                                class="size-4 shrink-0"
                            />
                            <span class="truncate font-semibold">{{
                                project.name
                            }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem v-else>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="ProjectController.index()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <ProjectNav v-if="project" :project-id="project.id" />
            <NavMain v-else :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
