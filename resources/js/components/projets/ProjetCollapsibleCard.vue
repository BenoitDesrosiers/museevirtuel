<script setup lang="ts">
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    title: string;
    collapsed: boolean;
    titleClass?: string;
    contentClass?: string;
}>();

const emit = defineEmits<{
    toggle: [];
}>();
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle
                :class="
                    titleClass ??
                    'text-sm font-medium tracking-wide text-muted-foreground uppercase'
                "
            >
                {{ title }}
            </CardTitle>
            <BoutonTooltip
                :texte="collapsed ? 'Développer' : 'Réduire'"
                @click="emit('toggle')"
            >
                <ChevronUp v-if="!collapsed" class="h-4 w-4" />
                <ChevronDown v-else class="h-4 w-4" />
            </BoutonTooltip>
        </CardHeader>
        <CardContent v-show="!collapsed" :class="contentClass">
            <slot />
        </CardContent>
    </Card>
</template>
