<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
} from '@/components/ui/sidebar';
import NavCollapsible from '@/components/NavCollapsible.vue';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ $t('nav.platform') }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavCollapsible
                v-for="item in items"
                :key="item.title"
                :item="item"
                :depth="0"
            />
        </SidebarMenu>
    </SidebarGroup>
</template>
