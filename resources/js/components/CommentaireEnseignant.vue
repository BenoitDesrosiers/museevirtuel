<script setup lang="ts">
import { Loader2, MessageSquare, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

type Commentaire = {
    id: number;
    contenu: string;
};

defineProps<{
    commentaire: Commentaire | null | undefined;
    brouillon: string;
    estReduit: boolean;
    isSaving: boolean;
    estEnseignant: boolean;
    placeholder?: string;
}>();

const emit = defineEmits<{
    toggle: [];
    save: [];
    delete: [];
    'update:brouillon': [value: string];
}>();
</script>

<template>
    <div v-if="estEnseignant || commentaire">
        <div v-if="estEnseignant" class="flex items-start gap-2">
            <button type="button" class="mt-2 shrink-0" @click="emit('toggle')">
                <MessageSquare class="h-4 w-4 text-blue-500" />
            </button>
            <div v-show="!estReduit" class="flex-1 space-y-1">
                <Textarea
                    :model-value="brouillon"
                    :placeholder="placeholder ?? 'Commentaire…'"
                    class="min-h-[60px] text-sm"
                    @update:model-value="
                        (v: string) => emit('update:brouillon', v)
                    "
                />
                <div class="flex gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="isSaving"
                        @click="emit('save')"
                    >
                        <Loader2
                            v-if="isSaving"
                            class="mr-1 h-3 w-3 animate-spin"
                        />
                        Commenter
                    </Button>
                    <Button
                        v-if="commentaire"
                        size="sm"
                        variant="ghost"
                        class="text-destructive"
                        @click="emit('delete')"
                    >
                        <Trash2 class="h-3 w-3" />
                    </Button>
                </div>
            </div>
        </div>
        <div
            v-else-if="commentaire"
            v-show="!estReduit"
            class="flex gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200"
        >
            <button
                type="button"
                class="mt-0.5 shrink-0"
                @click="emit('toggle')"
            >
                <MessageSquare class="h-4 w-4" />
            </button>
            <p>{{ commentaire.contenu }}</p>
        </div>
    </div>
</template>
