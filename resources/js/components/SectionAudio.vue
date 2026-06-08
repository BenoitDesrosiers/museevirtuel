<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2, Link, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import mediasRoutes from '@/routes/projets/sections/medias';

type RouteParams = {
    cours: number;
    classe: number;
    groupe: number;
    typeProjet: number;
    section: number;
};

type Media = {
    id: number;
    source_type: 'upload' | 'url';
    url: string | null;
    nom_original: string | null;
    url_publique: string | null;
};

const props = defineProps<{
    params: RouteParams;
    medias: Media[];
    readonly?: boolean;
}>();

// ─── Ajout ────────────────────────────────────────────────────────────────────

const showForm = ref(false);
const sourceType = ref<'url' | 'upload'>('url');

const addForm = useForm({
    source_type: 'url' as 'url' | 'upload',
    url: '',
    fichier: null as File | null,
});

/**
 * Soumet le formulaire d'ajout d'un média audio.
 */
function submitAdd(): void {
    addForm.source_type = sourceType.value;

    addForm.post(mediasRoutes.store.url(props.params), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            addForm.reset();
            showForm.value = false;
        },
    });
}

function handleFileChange(e: Event): void {
    const input = e.target as HTMLInputElement;
    addForm.fichier = input.files?.[0] ?? null;
}

// ─── Suppression ──────────────────────────────────────────────────────────────

const deleteForm = useForm({});

/**
 * Supprime un média audio après confirmation.
 */
function supprimerMedia(media: Media): void {
    if (!confirm('Supprimer cet audio ?')) {
        return;
    }

    deleteForm.delete(
        mediasRoutes.destroy.url({ ...props.params, media: media.id }),
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <div class="space-y-3">
        <!-- Liste des audios existants -->
        <div
            v-for="media in medias"
            :key="media.id"
            class="rounded-md border bg-card p-3"
        >
            <div class="flex items-start justify-between gap-2">
                <span class="truncate text-sm font-medium text-foreground">
                    {{ media.nom_original ?? media.url }}
                </span>
                <button
                    v-if="!readonly"
                    type="button"
                    class="shrink-0 text-muted-foreground hover:text-destructive"
                    :disabled="deleteForm.processing"
                    @click="supprimerMedia(media)"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>

            <!-- Player audio HTML5 -->
            <audio
                v-if="media.url_publique"
                :src="media.url_publique"
                controls
                class="mt-2 w-full"
                preload="metadata"
            />
        </div>

        <div
            v-if="medias.length === 0 && readonly"
            class="text-sm text-muted-foreground italic"
        >
            Aucun audio ajouté.
        </div>

        <!-- Formulaire d'ajout -->
        <div v-if="showForm" class="space-y-3 rounded-md border bg-card p-4">
            <!-- Sélecteur source -->
            <div class="flex gap-2">
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm',
                        sourceType === 'url'
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted',
                    ]"
                    @click="sourceType = 'url'"
                >
                    <Link class="h-3.5 w-3.5" /> URL
                </button>
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm',
                        sourceType === 'upload'
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted',
                    ]"
                    @click="sourceType = 'upload'"
                >
                    <Upload class="h-3.5 w-3.5" /> Fichier
                </button>
            </div>

            <!-- Champ URL -->
            <div v-if="sourceType === 'url'">
                <input
                    v-model="addForm.url"
                    type="url"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    placeholder="https://..."
                />
                <InputError :message="addForm.errors.url" />
            </div>

            <!-- Champ fichier -->
            <div v-else>
                <input
                    type="file"
                    accept=".mp3,.wav,.m4a,.aac"
                    class="w-full text-sm"
                    @change="handleFileChange"
                />
                <InputError :message="addForm.errors.fichier" />
            </div>

            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    :disabled="addForm.processing"
                    @click="submitAdd"
                >
                    Ajouter
                </button>
                <button
                    type="button"
                    class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                    @click="
                        showForm = false;
                        addForm.reset();
                    "
                >
                    Annuler
                </button>
            </div>
        </div>

        <!-- Bouton Ajouter -->
        <button
            v-if="!showForm && !readonly"
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground hover:bg-muted"
            @click="showForm = true"
        >
            <Plus class="h-4 w-4" />
            Ajouter un audio
        </button>
    </div>
</template>
