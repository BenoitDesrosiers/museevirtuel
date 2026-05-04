<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Check, GripVertical, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import objectifsRoutes from '@/routes/cours/objectifs';

type Objectif = {
    id: number;
    contenu: string;
    ordre: number;
};

const props = defineProps<{
    coursId: number;
    objectifs: Objectif[];
}>();

// ─── Ajouter un objectif ──────────────────────────────────────────────────────
const showAddForm = ref(false);
const addForm = useForm({ contenu: '' });

/**
 * Soumet le formulaire d'ajout d'un objectif pédagogique.
 */
function submitAdd() {
    addForm.post(objectifsRoutes.store.url(props.coursId), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
}

// ─── Modifier un objectif ─────────────────────────────────────────────────────
const editingId = ref<number | null>(null);
const editForm = useForm({ contenu: '' });

/**
 * Ouvre le mode édition inline pour un objectif.
 */
function openEdit(objectif: Objectif) {
    editingId.value = objectif.id;
    editForm.contenu = objectif.contenu;
}

/**
 * Annule l'édition en cours.
 */
function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

/**
 * Soumet la mise à jour d'un objectif.
 */
function submitEdit(objectif: Objectif) {
    editForm.put(objectifsRoutes.update.url({ cours: props.coursId, objectif: objectif.id }), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            editForm.reset();
        },
    });
}

// ─── Supprimer un objectif ────────────────────────────────────────────────────
const deleteForm = useForm({});

/**
 * Supprime un objectif après confirmation.
 */
function supprimerObjectif(objectif: Objectif) {
    if (!confirm(`Supprimer l'objectif « ${objectif.contenu.slice(0, 50)}… » ?`)) {
        return;
    }

    deleteForm.delete(objectifsRoutes.destroy.url({ cours: props.coursId, objectif: objectif.id }), {
        preserveScroll: true,
    });
}

// ─── Réordonnancement (drag-and-drop simplifié) ───────────────────────────────
const draggingId = ref<number | null>(null);

/**
 * Déplace un objectif vers le haut dans la liste.
 */
function monterObjectif(index: number) {
    if (index === 0) return;

    const ordered = [...props.objectifs].sort((a, b) => a.ordre - b.ordre);
    const ids = ordered.map((o) => o.id);

    // Échanger avec l'élément précédent
    [ids[index - 1], ids[index]] = [ids[index], ids[index - 1]];

    router.patch(
        objectifsRoutes.reorder.url(props.coursId),
        { ordre: ids },
        { preserveScroll: true },
    );
}

/**
 * Déplace un objectif vers le bas dans la liste.
 */
function descendreObjectif(index: number) {
    const ordered = [...props.objectifs].sort((a, b) => a.ordre - b.ordre);
    if (index === ordered.length - 1) return;

    const ids = ordered.map((o) => o.id);

    // Échanger avec l'élément suivant
    [ids[index], ids[index + 1]] = [ids[index + 1], ids[index]];

    router.patch(
        objectifsRoutes.reorder.url(props.coursId),
        { ordre: ids },
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-2">
        <!-- Liste des objectifs -->
        <div v-if="objectifs.length === 0 && !showAddForm" class="py-3 text-center text-sm text-muted-foreground">
            Aucun objectif pédagogique. Ajoutez-en un ci-dessous.
        </div>

        <ol class="space-y-1.5">
            <li
                v-for="(objectif, index) in [...objectifs].sort((a, b) => a.ordre - b.ordre)"
                :key="objectif.id"
                class="flex items-center gap-2 rounded-md border bg-card px-3 py-2"
            >
                <!-- Numéro d'ordre -->
                <span class="w-5 shrink-0 text-right text-xs font-medium text-muted-foreground">
                    {{ index + 1 }}.
                </span>

                <!-- Édition inline -->
                <template v-if="editingId === objectif.id">
                    <Input
                        v-model="editForm.contenu"
                        class="h-7 flex-1 text-sm"
                        @keydown.enter.prevent="submitEdit(objectif)"
                        @keydown.escape="cancelEdit"
                    />
                    <Button size="sm" variant="ghost" class="h-7 w-7 p-0" :disabled="editForm.processing" @click="submitEdit(objectif)">
                        <Check class="h-3.5 w-3.5 text-green-600" />
                    </Button>
                    <Button size="sm" variant="ghost" class="h-7 w-7 p-0" @click="cancelEdit">
                        <X class="h-3.5 w-3.5" />
                    </Button>
                </template>

                <!-- Affichage normal -->
                <template v-else>
                    <span class="flex-1 text-sm">{{ objectif.contenu }}</span>

                    <!-- Boutons de réordonnancement -->
                    <div class="flex shrink-0 flex-col gap-0.5">
                        <button
                            type="button"
                            class="h-3 text-muted-foreground hover:text-foreground disabled:opacity-30"
                            :disabled="index === 0"
                            title="Monter"
                            @click="monterObjectif(index)"
                        >
                            <GripVertical class="h-3 w-3 rotate-90" />
                        </button>
                        <button
                            type="button"
                            class="h-3 text-muted-foreground hover:text-foreground disabled:opacity-30"
                            :disabled="index === objectifs.length - 1"
                            title="Descendre"
                            @click="descendreObjectif(index)"
                        >
                            <GripVertical class="h-3 w-3 -rotate-90" />
                        </button>
                    </div>

                    <Button size="sm" variant="ghost" class="h-7 w-7 p-0" @click="openEdit(objectif)">
                        <Pencil class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                        :disabled="deleteForm.processing"
                        @click="supprimerObjectif(objectif)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </template>
            </li>
        </ol>

        <!-- Formulaire d'ajout inline -->
        <div v-if="showAddForm" class="flex items-center gap-2 rounded-md border bg-card px-3 py-2">
            <Label class="sr-only" for="new-objectif">Nouvel objectif</Label>
            <Input
                id="new-objectif"
                v-model="addForm.contenu"
                class="h-7 flex-1 text-sm"
                placeholder="Ex. Analyser des sources primaires…"
                maxlength="1000"
                @keydown.enter.prevent="submitAdd"
                @keydown.escape="showAddForm = false"
            />
            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" :disabled="addForm.processing || !addForm.contenu.trim()" @click="submitAdd">
                <Check class="h-3.5 w-3.5 text-green-600" />
            </Button>
            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" @click="showAddForm = false; addForm.reset()">
                <X class="h-3.5 w-3.5" />
            </Button>
        </div>
        <InputError :message="addForm.errors.contenu" />

        <!-- Bouton Ajouter -->
        <Button
            v-if="!showAddForm"
            size="sm"
            variant="outline"
            class="w-full"
            @click="showAddForm = true"
        >
            <Plus class="mr-2 h-4 w-4" />
            Ajouter un objectif
        </Button>
    </div>
</template>
