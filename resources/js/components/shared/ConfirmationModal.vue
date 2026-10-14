<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    open: boolean;
    title: string;
    description?: string;
    /** Texte mis en avant entre le titre et la description (ex. nom de fichier). */
    emphasis?: string;
    confirmLabel?: string;
    loading?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5 shrink-0 text-destructive" />
                    {{ title }}
                </DialogTitle>
                <p
                    v-if="emphasis"
                    class="break-all text-base font-semibold text-foreground"
                >
                    {{ emphasis }}
                </p>
                <DialogDescription
                    v-if="description"
                    class="whitespace-pre-line"
                >
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="flex gap-2 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="loading"
                    @click="emit('update:open', false)"
                >
                    Annuler
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel ?? 'Oui, supprimer définitivement' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
