<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import type { Component } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    title: string;
    icon?: Component;
    count?: string | number;
    open: boolean;
};

const props = defineProps<Props>();

defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between">
            <button
                type="button"
                class="flex cursor-pointer items-center gap-2 text-left select-none"
                @click="$emit('update:open', !props.open)"
            >
                <component :is="icon" v-if="icon" class="h-5 w-5" />
                <CardTitle>{{ title }}</CardTitle>
                <span
                    v-if="count !== undefined"
                    class="text-sm font-normal text-muted-foreground"
                >
                    ({{ count }})
                </span>
                <ChevronDown
                    class="h-4 w-4 text-muted-foreground transition-transform"
                    :class="{ '-rotate-180': open }"
                />
            </button>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </CardHeader>
        <CardContent v-show="open">
            <slot />
        </CardContent>
    </Card>
</template>
