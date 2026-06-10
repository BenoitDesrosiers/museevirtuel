<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { appearance, updateAppearance } = useAppearance();

const cycleOrder: Appearance[] = ['light', 'dark', 'system'];

/**
 * Passe au mode suivant dans le cycle light → dark → system → light.
 */
function cycleAppearance(): void {
    const current = cycleOrder.indexOf(appearance.value);
    const next = cycleOrder[(current + 1) % cycleOrder.length];
    updateAppearance(next);
}

const appearanceIcon = computed(() => {
    if (appearance.value === 'dark') {
        return Moon;
    }

    if (appearance.value === 'system') {
        return Monitor;
    }

    return Sun;
});

const appearanceLabel = computed(() => {
    if (appearance.value === 'dark') {
        return 'Mode sombre';
    }

    if (appearance.value === 'system') {
        return 'Mode système';
    }

    return 'Mode clair';
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <TooltipProvider :delay-duration="300">
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        @click="cycleAppearance"
                    >
                        <component
                            :is="appearanceIcon"
                            class="h-4 w-4"
                        />
                        <span class="sr-only">{{ appearanceLabel }}</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{{ appearanceLabel }}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </header>
</template>
