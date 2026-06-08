<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    open: boolean;
    title: string;
    isLoading: boolean;
    /** Libellé du bouton de soumission. Défaut : clé i18n common.save */
    submitLabel?: string;
    /** Ajoute max-h-[90vh] overflow-y-auto sur le DialogContent pour les listes longues. */
    scrollable?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            :class="scrollable ? 'max-h-[90vh] overflow-y-auto' : undefined"
        >
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="emit('submit')">
                <slot />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        {{ $t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="isLoading">
                        {{ submitLabel ?? $t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
