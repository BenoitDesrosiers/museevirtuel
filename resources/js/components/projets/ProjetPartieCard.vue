<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    title: string;
    description?: string | null;
    contentClass?: string;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex flex-col gap-0.5">
                <span class="text-base font-semibold">{{ title }}</span>
                <span
                    v-if="description"
                    class="text-sm font-normal text-muted-foreground"
                >
                    {{ description }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent :class="contentClass">
            <slot />
        </CardContent>
    </Card>
</template>
