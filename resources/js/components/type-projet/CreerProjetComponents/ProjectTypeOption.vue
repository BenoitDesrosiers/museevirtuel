<script setup lang="ts">
type Props = {
    value: 'standard' | 'musee';
    label: string;
    description: string;
    selected: boolean;
};

defineProps<Props>();

const emit = defineEmits<{
    select: [value: 'standard' | 'musee'];
}>();
</script>

<template>
    <button
        type="button"
        :class="[
            'flex flex-col rounded-md border px-4 py-3 text-left text-sm transition-colors',
            selected
                ? 'border-primary bg-primary/5 text-primary'
                : 'border-border text-muted-foreground hover:border-muted-foreground/40',
        ]"
        @click="emit('select', value)"
    >
        <span class="font-semibold">
            {{ label }}
        </span>
        <span class="mt-0.5 text-xs leading-snug text-muted-foreground">
            {{ description }}
        </span>
    </button>
</template>
