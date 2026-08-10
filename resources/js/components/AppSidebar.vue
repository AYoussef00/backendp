<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    FolderGit2,
    Globe,
    LayoutGrid,
    ListTodo,
    Server,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Servers',
        href: '/servers',
        icon: Server,
    },
    {
        title: 'Websites',
        href: '/websites',
        icon: Globe,
    },
    {
        title: 'Jobs',
        href: '/jobs',
        icon: ListTodo,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Activity',
        href: '/jobs',
        icon: Activity,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs',
        icon: FolderGit2,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
