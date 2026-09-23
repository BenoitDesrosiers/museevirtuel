<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    message?: string;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('min-h-5', props.class)">
        <p v-if="message" class="text-sm text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
