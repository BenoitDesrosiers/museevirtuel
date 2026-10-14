<script setup lang="ts">
import { Info } from 'lucide-vue-next';

type SectionType = 'texte' | 'paragraphes' | 'individuel' | 'entrevue';

type SectionTypeOption = {
    value: SectionType;
    label: string;
    description: string;
};

const props = defineProps<{
    modelValue: SectionType;
    options: SectionTypeOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: SectionType];
    info: [];
}>();
</script>

<template>
    <div class="ml-11">
        <div class="mb-2 flex items-center gap-1">
            <p class="text-xs font-medium text-muted-foreground">
                {{ $t('types_projet.edit.input_mode_label') }}
            </p>
            <button
                type="button"
                class="text-muted-foreground hover:text-foreground"
                @click="emit('info')"
            >
                <Info class="h-3 w-3" />
            </button>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <button
                v-for="option in props.options"
                :key="option.value"
                type="button"
                :class="[
                    'flex flex-col rounded-md border px-3 py-2.5 text-left text-xs transition-colors',
                    props.modelValue === option.value
                        ? 'border-primary bg-primary/5 text-primary'
                        : 'border-border bg-background text-muted-foreground hover:border-muted-foreground/40',
                ]"
                @click="emit('update:modelValue', option.value)"
            >
                <span class="font-medium">{{ option.label }}</span>
                <span class="mt-0.5 leading-tight text-muted-foreground/70">
                    {{ option.description }}
                </span>
            </button>
        </div>
    </div>
</template>
