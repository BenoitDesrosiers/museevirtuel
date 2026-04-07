<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Grid2x2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import typesProjets from '@/routes/types-projets';

type GrilleResume = { id: number; nom: string } | null;

type SectionType = 'texte' | 'paragraphes' | 'individuel';

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    type: SectionType;
};

const SECTION_TYPE_LABELS: Record<SectionType, string> = {
    texte: 'Texte libre',
    paragraphes: 'Paragraphes',
    individuel: 'Individuel (1 par membre)',
};

type TypeProjet = {
    id: number;
    nom: string;
    description: string | null;
    accessible: boolean;
    grille: GrilleResume;
    sections: Section[];
};

type Props = {
    typesProjets: TypeProjet[];
};

const props = defineProps<Props>();

// ─── Création TypeProjet ──────────────────────────────────────────────────────
const showCreateDialog = ref(false);
const createForm = useForm({
    nom: '',
    description: '',
    sections: [] as { label: string; description: string; type: SectionType }[],
});

function ajouterSectionCreate() {
    createForm.sections.push({ label: '', description: '', type: 'texte' });
}

function supprimerSectionCreate(idx: number) {
    createForm.sections.splice(idx, 1);
}

function creer() {
    createForm.post(typesProjets.store.url(), {
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
        },
    });
}

// ─── Toggle accessible ────────────────────────────────────────────────────────
const toggleForm = useForm({});

function toggleAccessible(tp: TypeProjet) {
    toggleForm.patch(typesProjets.toggleAccessible.url(tp.id));
}

// ─── Suppression TypeProjet ───────────────────────────────────────────────────
const deleteForm = useForm({});

function supprimer(tp: TypeProjet) {
    if (
        !confirm(
            `Supprimer « ${tp.nom} » ? Cette action supprimera également la grille de correction et toutes les sections associées, et ne peut pas être annulée.`,
        )
    ) {
        return;
    }
    deleteForm.delete(typesProjets.destroy.url(tp.id));
}
</script>

<template>
    <AppLayout>
        <Head title="Types de projet" />

        <div class="mx-auto flex max-w-3xl flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <Heading
                    title="Types de projet"
                    description="Chaque type de projet définit les sections que les étudiants devront rédiger, ainsi que sa propre grille de correction."
                />
                <Button size="sm" @click="showCreateDialog = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Nouveau type
                </Button>
            </div>

            <!-- Liste vide -->
            <Card v-if="props.typesProjets.length === 0">
                <CardContent class="py-10 text-center text-muted-foreground">
                    Aucun type de projet. Créez-en un pour commencer.
                </CardContent>
            </Card>

            <!-- Liste des types -->
            <Card v-for="tp in props.typesProjets" :key="tp.id">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <CardTitle class="flex items-center gap-2 text-base">
                                {{ tp.nom }}
                                <Badge
                                    :class="
                                        tp.accessible
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'
                                    "
                                    class="text-xs"
                                >
                                    {{ tp.accessible ? 'Accessible' : 'Non accessible' }}
                                </Badge>
                            </CardTitle>
                            <p v-if="tp.description" class="mt-1 text-sm text-muted-foreground">
                                {{ tp.description }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                size="sm"
                                :variant="tp.accessible ? 'outline' : 'secondary'"
                                class="text-xs"
                                :disabled="toggleForm.processing"
                                @click="toggleAccessible(tp)"
                            >
                                <ChevronRight v-if="!tp.accessible" class="mr-1 h-3 w-3" />
                                <ChevronDown v-else class="mr-1 h-3 w-3" />
                                {{ tp.accessible ? 'Masquer' : 'Rendre accessible' }}
                            </Button>

                            <Button size="icon" variant="ghost" class="h-8 w-8" as-child>
                                <Link :href="typesProjets.edit.url(tp.id)" title="Modifier">
                                    <Pencil class="h-4 w-4" />
                                </Link>
                            </Button>

                            <Button
                                size="icon"
                                variant="ghost"
                                class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                :disabled="deleteForm.processing"
                                @click="supprimer(tp)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="flex flex-col gap-4">
                    <!-- Grille associée -->
                    <div class="flex items-center gap-3">
                        <Grid2x2 class="h-4 w-4 text-muted-foreground" />
                        <span class="text-sm text-muted-foreground">Grille de correction :</span>
                        <a
                            :href="typesProjets.grille.edit.url(tp.id)"
                            class="text-sm font-medium text-primary hover:underline"
                        >
                            {{ tp.grille ? tp.grille.nom : 'Configurer la grille' }}
                        </a>
                    </div>

                    <!-- Sections (résumé) -->
                    <div class="border-t pt-3">
                        <p class="mb-2 text-xs font-medium text-muted-foreground">
                            {{ tp.sections.length }} section{{ tp.sections.length !== 1 ? 's' : '' }}
                            <span v-if="tp.sections.length === 0" class="font-normal italic">
                                — introduction par défaut
                            </span>
                        </p>
                        <div v-if="tp.sections.length > 0" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="s in [...tp.sections].sort((a, b) => a.ordre - b.ordre)"
                                :key="s.id"
                                class="rounded-md bg-muted px-2 py-0.5 text-xs"
                            >
                                {{ s.ordre }}. {{ s.label }}
                                <span class="ml-1 text-muted-foreground/60">
                                    ({{ SECTION_TYPE_LABELS[s.type ?? 'texte'] }})
                                </span>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ─── Dialog création ──────────────────────────────────────────────── -->
        <FormDialog
            v-model:open="showCreateDialog"
            title="Nouveau type de projet"
            :is-loading="createForm.processing"
            submit-label="Créer"
            scrollable
            @submit="creer"
        >
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="nom-create">Nom <span class="text-destructive">*</span></Label>
                    <Input
                        id="nom-create"
                        v-model="createForm.nom"
                        placeholder="Ex. : Projet de recherche documentaire"
                        required
                    />
                    <InputError :message="createForm.errors.nom" />
                </div>

                <div class="grid gap-2">
                    <Label for="desc-create">Description (optionnelle)</Label>
                    <Textarea
                        id="desc-create"
                        v-model="createForm.description"
                        placeholder="Notes sur ce type de projet..."
                        rows="2"
                    />
                    <InputError :message="createForm.errors.description" />
                </div>

                <!-- Sections -->
                <div class="grid gap-3 border-t pt-3">
                    <div>
                        <Label>Sections du projet</Label>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Définissez les parties que les étudiants devront rédiger. Sans section, l'introduction en
                            trois parties est utilisée par défaut.
                        </p>
                    </div>

                    <div v-if="createForm.sections.length > 0" class="flex flex-col gap-2">
                        <div
                            v-for="(section, idx) in createForm.sections"
                            :key="idx"
                            class="flex items-start gap-2 rounded-md border bg-muted/30 p-3"
                        >
                            <span class="mt-2 w-5 shrink-0 text-center text-xs text-muted-foreground">
                                {{ idx + 1 }}
                            </span>
                            <div class="flex-1 space-y-1.5">
                                <Input
                                    v-model="createForm.sections[idx].label"
                                    placeholder="Titre de la section *"
                                    required
                                />
                                <InputError :message="createForm.errors[`sections.${idx}.label`]" />
                                <select
                                    v-model="createForm.sections[idx].type"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs ring-offset-background focus:outline-none focus:ring-1 focus:ring-ring"
                                >
                                    <option v-for="(label, val) in SECTION_TYPE_LABELS" :key="val" :value="val">
                                        {{ label }}
                                    </option>
                                </select>
                                <Input
                                    v-model="createForm.sections[idx].description"
                                    placeholder="Consigne pour les étudiants (optionnelle)"
                                />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="mt-0.5 h-7 w-7 shrink-0 text-muted-foreground hover:text-destructive"
                                @click="supprimerSectionCreate(idx)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>

                    <Button type="button" size="sm" variant="outline" @click="ajouterSectionCreate">
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter une section
                    </Button>
                </div>
            </div>
        </FormDialog>

    </AppLayout>
</template>
