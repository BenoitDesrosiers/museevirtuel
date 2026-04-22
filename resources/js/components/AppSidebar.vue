<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, LayoutDashboard, LayoutGrid } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
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

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);

const mainNavItems = computed((): NavItem[] => {
    const role = user.value?.role;

    if (role === 'admin') {
        return [
            {
                title: t('sidebar.administration'),
                href: '/administration',
                icon: LayoutGrid,
            },
            {
                title: t('sidebar.teacher_space'),
                href: '/enseignant',
                icon: LayoutDashboard,
            },
        ];
    }

    if (role === 'enseignant') {
        return [
            {
                title: t('sidebar.my_space'),
                href: '/enseignant',
                icon: LayoutDashboard,
            },
        ];
    }

    if (role === 'etudiant') {
        return [
            {
                title: t('sidebar.my_class'),
                href: '/etudiant',
                icon: BookOpen,
            },
        ];
    }

    return [];
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/">
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
