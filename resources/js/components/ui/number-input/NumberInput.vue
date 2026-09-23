<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: number | null;
        min?: number;
        max?: number;
        step?: number;
        placeholder?: string;
        disabled?: boolean;
        id?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        step: 1,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: number | null): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
});

/**
 * Incrémente la valeur en respectant la limite max.
 */
function increment(): void {
    if (props.disabled) {
        return;
    }

    const current = Number(modelValue.value ?? props.min ?? 0);
    const next = current + props.step;

    if (props.max !== undefined && next > props.max) {
        return;
    }

    modelValue.value = next;
}

/**
 * Décrémente la valeur en respectant la limite min.
 */
function decrement(): void {
    if (props.disabled) {
        return;
    }

    const current = Number(modelValue.value ?? props.min ?? 0);
    const next = current - props.step;

    if (props.min !== undefined && next < props.min) {
        return;
    }

    modelValue.value = next;
}
</script>

<template>
    <div class="relative w-full">
        <input
            :id="id"
            v-model="modelValue"
            type="number"
            data-slot="number-input"
            :min="min"
            :max="max"
            :step="step"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="
                cn(
                    'h-9 w-full min-w-0 rounded-md border border-input bg-transparent py-1 pr-9 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                    '[appearance:textfield] [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                    props.class,
                )
            "
        />
        <div
            class="absolute inset-y-0 right-0 flex w-9 flex-col overflow-hidden rounded-r-md border-l border-input"
        >
            <button
                type="button"
                tabindex="-1"
                class="flex h-1/2 items-center justify-center text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                :disabled="
                    disabled ||
                    (max !== undefined &&
                        Number(modelValue ?? min ?? 0) >= max)
                "
                @click="increment"
            >
                <ChevronUp class="size-3.5 shrink-0" />
            </button>
            <button
                type="button"
                tabindex="-1"
                class="flex h-1/2 items-center justify-center border-t border-input text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                :disabled="
                    disabled ||
                    (min !== undefined &&
                        Number(modelValue ?? min ?? 0) <= min)
                "
                @click="decrement"
            >
                <ChevronDown class="size-3.5 shrink-0" />
            </button>
        </div>
    </div>
</template>
