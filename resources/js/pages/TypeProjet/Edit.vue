<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import typesProjets from '@/routes/types-projets';

type SectionType = 'texte' | 'paragraphes' | 'individuel';

type SectionFormItem = {
    id?: number;
    label: string;
    description: string;
    type: SectionType;
};

type TypeProjet = {
    id: number;
    nom: string;
    description: string | null;
    sections: { id: number; label: string; description: string | null; ordre: number; type: SectionType }[];
};

type Props = {
    typeProjet: TypeProjet;
};

const SECTION_TYPES: { value: SectionType; label: string; description: string }[] = [
    {
        value: 'texte',
        label: 'Texte libre',
        description: 'Un seul bloc de texte partagé par tout le groupe.',
    },
    {
        value: 'paragraphes',
        label: 'Paragraphes',
        description: 'Le groupe peut ajouter plusieurs paragraphes dynamiquement.',
    },
    {
        value: 'individuel',
        label: 'Individuel',
        description: 'Chaque membre rédige sa propre version de cette section.',
    },
];

const props = defineProps<Props>();

const form = useForm({
    nom: props.typeProjet.nom,
    description: props.typeProjet.description ?? '',
    sections: props.typeProjet.sections.map<SectionFormItem>((s) => ({
        id: s.id,
        label: s.label,
        description: s.description ?? '',
        type: s.type ?? 'texte',
    })),
});

/**
 * Ajoute une nouvelle section vide à la fin de la liste.
 */
function ajouterSection() {
    form.sections.push({ label: '', description: '', type: 'texte' });
}

/**
 * Supprime la section à l'index donné.
 */
function supprimerSection(idx: number) {
    form.sections.splice(idx, 1);
}

/**
 * Soumet le formulaire complet via PUT.
 */
function sauvegarder() {
    form.put(typesProjets.update.url(props.typeProjet.id), {
        onSuccess: () => {
            // Reste sur la page — le flash success s'affiche via Inertia
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="`Modifier — ${props.typeProjet.nom}`" />

        <div class="mx-auto flex max-w-2xl flex-col gap-6 p-6">
            <!-- En-tête -->
            <div>
                <Link
                    :href="typesProjets.index.url()"
                    class="mb-3 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    Retour aux types de projet
                </Link>
                <Heading title="Modifier le type de projet" />
            </div>

            <!-- Informations générales -->
            <Card>
                <CardContent class="grid gap-4 pt-6">
                    <div class="grid gap-2">
                        <Label for="nom">Nom <span class="text-destructive">*</span></Label>
                        <Input id="nom" v-model="form.nom" required />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Description (optionnelle)</Label>
                        <Textarea id="description" v-model="form.description" rows="2" />
                        <InputError :message="form.errors.description" />
                    </div>
                </CardContent>
            </Card>

            <!-- Sections -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold">Sections du projet</h2>
                        <p class="text-xs text-muted-foreground">
                            Définissez les parties que les étudiants devront rédiger et leur mode de saisie.
                        </p>
                    </div>
                    <Button type="button" size="sm" variant="outline" @click="ajouterSection">
                        <Plus class="mr-2 h-3.5 w-3.5" />
                        Ajouter
                    </Button>
                </div>

                <!-- Message vide -->
                <Card v-if="form.sections.length === 0">
                    <CardContent class="py-8 text-center text-sm text-muted-foreground">
                        Aucune section définie. Cliquez sur « Ajouter » pour commencer.
                    </CardContent>
                </Card>

                <!-- Liste des sections -->
                <Card
                    v-for="(section, idx) in form.sections"
                    :key="section.id ?? `new-${idx}`"
                    class="border"
                >
                    <CardContent class="grid gap-4 pt-5">
                        <!-- Numéro + label + supprimer -->
                        <div class="flex items-start gap-2">
                            <GripVertical class="mt-2.5 h-4 w-4 shrink-0 text-muted-foreground/40" />
                            <span class="mt-2 w-5 shrink-0 text-center text-xs font-medium text-muted-foreground">
                                {{ idx + 1 }}
                            </span>
                            <div class="flex-1 space-y-1.5">
                                <Input
                                    v-model="form.sections[idx].label"
                                    placeholder="Titre de la section *"
                                    required
                                />
                                <InputError :message="form.errors[`sections.${idx}.label`]" />
                                <Input
                                    v-model="form.sections[idx].description"
                                    placeholder="Consigne pour les étudiants (optionnelle)"
                                />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="mt-0.5 h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
                                @click="supprimerSection(idx)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>

                        <!-- Sélecteur de type -->
                        <div class="ml-11">
                            <p class="mb-2 text-xs font-medium text-muted-foreground">Mode de saisie</p>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="t in SECTION_TYPES"
                                    :key="t.value"
                                    type="button"
                                    :class="[
                                        'flex flex-col rounded-md border px-3 py-2.5 text-left text-xs transition-colors',
                                        form.sections[idx].type === t.value
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : 'border-border bg-background text-muted-foreground hover:border-muted-foreground/40',
                                    ]"
                                    @click="form.sections[idx].type = t.value"
                                >
                                    <span class="font-medium">{{ t.label }}</span>
                                    <span class="mt-0.5 leading-tight text-muted-foreground/70">
                                        {{ t.description }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Bouton enregistrer -->
            <div class="flex justify-end">
                <Button :disabled="form.processing" @click="sauvegarder">
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer les modifications' }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
