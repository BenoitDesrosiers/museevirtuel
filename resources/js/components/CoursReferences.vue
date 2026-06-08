<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, ExternalLink, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import referencesRoutes from '@/routes/cours/references';

type Reference = {
    id: number;
    nom: string;
    url: string | null;
    ordre: number;
};

const props = defineProps<{
    coursId: number;
    references: Reference[];
}>();

// ─── Ajouter une référence ────────────────────────────────────────────────────
const showAddForm = ref(false);
const addForm = useForm({ nom: '', url: '' });

/**
 * Soumet le formulaire d'ajout d'une référence bibliographique.
 */
function submitAdd() {
    addForm.post(referencesRoutes.store.url(props.coursId), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
}

// ─── Modifier une référence ───────────────────────────────────────────────────
const editingId = ref<number | null>(null);
const editForm = useForm({ nom: '', url: '' });

/**
 * Ouvre le mode édition inline pour une référence.
 */
function openEdit(reference: Reference) {
    editingId.value = reference.id;
    editForm.nom = reference.nom;
    editForm.url = reference.url ?? '';
}

/**
 * Annule l'édition en cours.
 */
function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

/**
 * Soumet la mise à jour d'une référence.
 */
function submitEdit(reference: Reference) {
    editForm.put(
        referencesRoutes.update.url({
            cours: props.coursId,
            reference: reference.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
                editForm.reset();
            },
        },
    );
}

// ─── Supprimer une référence ──────────────────────────────────────────────────
const deleteForm = useForm({});

/**
 * Supprime une référence après confirmation.
 */
function supprimerReference(reference: Reference) {
    if (
        !confirm(`Supprimer la référence « ${reference.nom.slice(0, 60)} » ?`)
    ) {
        return;
    }

    deleteForm.delete(
        referencesRoutes.destroy.url({
            cours: props.coursId,
            reference: reference.id,
        }),
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <div class="space-y-2">
        <!-- Liste des références -->
        <div
            v-if="references.length === 0 && !showAddForm"
            class="py-3 text-center text-sm text-muted-foreground"
        >
            Aucune référence bibliographique. Ajoutez-en une ci-dessous.
        </div>

        <ol class="space-y-1.5">
            <li
                v-for="(reference, index) in [...references].sort(
                    (a, b) => a.ordre - b.ordre,
                )"
                :key="reference.id"
                class="flex items-center gap-2 rounded-md border bg-card px-3 py-2"
            >
                <!-- Numéro d'ordre -->
                <span
                    class="w-5 shrink-0 text-right text-xs font-medium text-muted-foreground"
                >
                    {{ index + 1 }}.
                </span>

                <!-- Édition inline -->
                <template v-if="editingId === reference.id">
                    <div class="flex flex-1 flex-col gap-1.5">
                        <Input
                            v-model="editForm.nom"
                            class="h-7 text-sm"
                            placeholder="Nom de la revue"
                            @keydown.escape="cancelEdit"
                        />
                        <Input
                            v-model="editForm.url"
                            class="h-7 text-sm"
                            placeholder="https://…"
                            type="url"
                            @keydown.escape="cancelEdit"
                        />
                        <InputError
                            :message="
                                editForm.errors.nom || editForm.errors.url
                            "
                        />
                    </div>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-7 w-7 p-0"
                        :disabled="editForm.processing"
                        @click="submitEdit(reference)"
                    >
                        <Check class="h-3.5 w-3.5 text-green-600" />
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-7 w-7 p-0"
                        @click="cancelEdit"
                    >
                        <X class="h-3.5 w-3.5" />
                    </Button>
                </template>

                <!-- Affichage normal -->
                <template v-else>
                    <component
                        :is="reference.url ? 'a' : 'span'"
                        v-bind="
                            reference.url
                                ? {
                                      href: reference.url,
                                      target: '_blank',
                                      rel: 'noopener noreferrer',
                                  }
                                : {}
                        "
                        class="flex flex-1 items-center gap-1.5 overflow-hidden"
                        :class="reference.url ? 'group cursor-pointer' : ''"
                    >
                        <span
                            class="truncate text-sm"
                            :class="
                                reference.url ? 'group-hover:underline' : ''
                            "
                            >{{ reference.nom }}</span
                        >
                        <ExternalLink
                            v-if="reference.url"
                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground group-hover:text-primary"
                        />
                    </component>

                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-7 w-7 p-0"
                        @click="openEdit(reference)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                        :disabled="deleteForm.processing"
                        @click="supprimerReference(reference)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </template>
            </li>
        </ol>

        <!-- Formulaire d'ajout inline -->
        <div
            v-if="showAddForm"
            class="space-y-1.5 rounded-md border bg-card px-3 py-2"
        >
            <div class="flex items-start gap-2">
                <div class="flex flex-1 flex-col gap-1.5">
                    <Label class="sr-only" for="new-reference-nom"
                        >Nom de la revue</Label
                    >
                    <Input
                        id="new-reference-nom"
                        v-model="addForm.nom"
                        class="h-7 text-sm"
                        placeholder="Ex. Revue d'histoire de l'Amérique française…"
                        maxlength="255"
                        @keydown.escape="showAddForm = false"
                    />
                    <InputError :message="addForm.errors.nom" />
                    <Label class="sr-only" for="new-reference-url">URL</Label>
                    <Input
                        id="new-reference-url"
                        v-model="addForm.url"
                        class="h-7 text-sm"
                        placeholder="https://…"
                        type="url"
                        maxlength="500"
                        @keydown.enter.prevent="submitAdd"
                        @keydown.escape="showAddForm = false"
                    />
                    <InputError :message="addForm.errors.url" />
                </div>
                <Button
                    size="sm"
                    variant="ghost"
                    class="h-7 w-7 shrink-0 p-0"
                    :disabled="addForm.processing || !addForm.nom.trim()"
                    @click="submitAdd"
                >
                    <Check class="h-3.5 w-3.5 text-green-600" />
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    class="h-7 w-7 shrink-0 p-0"
                    @click="
                        showAddForm = false;
                        addForm.reset();
                    "
                >
                    <X class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>

        <!-- Bouton Ajouter -->
        <Button
            v-if="!showAddForm"
            size="sm"
            variant="outline"
            class="w-full"
            @click="showAddForm = true"
        >
            <Plus class="mr-2 h-4 w-4" />
            Ajouter une référence
        </Button>
    </div>
</template>
