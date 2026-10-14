<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    hint?: string;
    hintWhenChecked?: boolean;
    align?: 'center' | 'start';
};

withDefaults(defineProps<Props>(), {
    hint: '',
    hintWhenChecked: false,
    align: 'start',
});

const checked = defineModel<boolean>('checked', { required: true });
</script>

<template>
    <div
        class="flex gap-3"
        :class="align === 'center' ? 'items-center' : 'items-start'"
    >
        <Checkbox :id="id" v-model:checked="checked" />
        <div class="grid gap-0.5">
            <Label :for="id" class="cursor-pointer">
                {{ label }}
            </Label>
            <p
                v-if="!hintWhenChecked || checked"
                class="text-xs text-muted-foreground"
            >
                {{ hint }}
            </p>
        </div>
    </div>
</template>
