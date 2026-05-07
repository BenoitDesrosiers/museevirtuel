<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    item: NavItem;
    /**
     * 0 = niveau racine (SidebarMenuButton + SidebarMenuItem),
     * 1+ = sous-niveau (SidebarMenuSubButton).
     */
    depth?: number;
}>();

const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();

const depth = props.depth ?? 0;
const hasChildren = !!props.item.children?.length;

/**
 * Détermine si cet item ou l'un de ses descendants correspond à l'URL courante.
 * Utilisé pour ouvrir automatiquement le collapsible au chargement de la page.
 */
function itemIsActive(item: NavItem): boolean {
    if (item.href && isCurrentOrParentUrl(item.href)) return true;
    return item.children?.some(itemIsActive) ?? false;
}

const isActive = props.item.href ? isCurrentUrl(props.item.href) : false;
const open = ref(itemIsActive(props.item));
</script>

<template>
    <!-- ───── Niveau racine : SidebarMenuItem + SidebarMenuButton ───── -->
    <template v-if="depth === 0">
        <SidebarMenuItem>
            <!-- Item avec enfants → collapsible -->
            <Collapsible v-if="hasChildren" v-model:open="open" class="group/collapsible">
                <!--
                    Deux cas :
                    - item.href défini  → icône+texte cliquable (lien) + chevron séparé (toggle)
                    - item.href absent  → toute la ligne est le trigger collapsible
                -->
                <div v-if="item.href" class="flex w-full items-center">
                    <SidebarMenuButton as-child :is-active="isActive" :tooltip="item.title" class="flex-1">
                        <Link :href="item.href">
                            <component :is="item.icon" v-if="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                    <CollapsibleTrigger as-child>
                        <button
                            class="text-sidebar-foreground hover:bg-sidebar-accent flex h-8 w-8 shrink-0 items-center justify-center rounded-md"
                        >
                            <ChevronRight
                                class="h-4 w-4 shrink-0 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                            />
                        </button>
                    </CollapsibleTrigger>
                </div>

                <CollapsibleTrigger v-else as-child>
                    <SidebarMenuButton :is-active="isActive" :tooltip="item.title">
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ item.title }}</span>
                        <ChevronRight
                            class="ml-auto shrink-0 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>

                <CollapsibleContent>
                    <SidebarMenuSub>
                        <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                            <NavCollapsible :item="child" :depth="1" />
                        </SidebarMenuSubItem>
                    </SidebarMenuSub>
                </CollapsibleContent>
            </Collapsible>

            <!-- Item sans enfants → lien simple -->
            <SidebarMenuButton v-else as-child :is-active="isActive" :tooltip="item.title">
                <Link :href="item.href!">
                    <component :is="item.icon" v-if="item.icon" />
                    <span>{{ item.title }}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </template>

    <!-- ───── Sous-niveaux : SidebarMenuSubButton ───── -->
    <template v-else>
        <!-- Sous-item avec enfants → collapsible imbriqué -->
        <Collapsible v-if="hasChildren" v-model:open="open" class="group/collapsible-sub">
            <!--
                Deux cas :
                - item.href défini  → lien cliquable + chevron séparé pour toggler
                - item.href absent  → toute la ligne est le trigger
            -->
            <div v-if="item.href" class="flex w-full items-center">
                <SidebarMenuSubButton as-child :is-active="isActive" class="flex-1">
                    <Link :href="item.href">{{ item.title }}</Link>
                </SidebarMenuSubButton>
                <CollapsibleTrigger as-child>
                    <button
                        class="text-sidebar-foreground hover:bg-sidebar-accent flex h-7 w-6 shrink-0 items-center justify-center rounded-md"
                    >
                        <ChevronRight
                            class="h-4 w-4 shrink-0 transition-transform duration-200 group-data-[state=open]/collapsible-sub:rotate-90"
                        />
                    </button>
                </CollapsibleTrigger>
            </div>

            <CollapsibleTrigger v-else as-child>
                <SidebarMenuSubButton :is-active="isActive">
                    <span>{{ item.title }}</span>
                    <ChevronRight
                        class="ml-auto shrink-0 transition-transform duration-200 group-data-[state=open]/collapsible-sub:rotate-90"
                    />
                </SidebarMenuSubButton>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <SidebarMenuSub :class="depth >= 2 ? 'mx-0 px-1.5' : undefined">
                    <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                        <NavCollapsible :item="child" :depth="depth + 1" />
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </CollapsibleContent>
        </Collapsible>

        <!-- Sous-item sans enfants → lien simple, hauteur auto pour titres longs -->
        <SidebarMenuSubButton v-else as-child :is-active="isActive" class="h-auto min-h-7 whitespace-normal break-words py-1.5 leading-snug">
            <Link :href="item.href!">{{ item.title }}</Link>
        </SidebarMenuSubButton>
    </template>
</template>
