<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, Check, Download, FileText, Plus, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Classe = {
    id: number;
    code: string;
    cours_id: number;
};

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
    periode_historique: string | null;
};

type Groupe = {
    id: number;
    numero: number;
    created_by: number;
    membres: Etudiant[];
    thematiques: Thematique[];
};

type Document = {
    id: number;
    nom_original: string;
    type: string;
    taille: number;
    url: string;
};

type EcheancierEtape = {
    id: number;
    semaine: number;
    etape: string;
    is_done_etudiant: boolean;
};

type Props = {
    cours: Cours;
    classe: Classe;
    monGroupe: Groupe | null;
    autresEtudiants: Etudiant[];
    thematiques: Thematique[];
    documents: Document[];
    echeancierEtapes: EcheancierEtape[];
};

function formatTaille(octets: number): string {
    if (octets < 1024) {
return `${octets} o`;
}

    if (octets < 1024 * 1024) {
return `${(octets / 1024).toFixed(1)} Ko`;
}

    return `${(octets / (1024 * 1024)).toFixed(1)} Mo`;
}

const props = defineProps<Props>();

// ─── Créer un groupe ──────────────────────────────────────────────────────────
const showCreateDialog = ref(false);
const form = useForm({
    membres: [] as number[],
    thematiques: [] as number[],
});

// Refs découplés de useForm pour tracker les cases à cocher de façon fiable
const membresSelectionnes = ref<number[]>([]);
const thematiquesSelectionnees = ref<number[]>([]);

function openCreate() {
    form.reset();
    membresSelectionnes.value = [];
    thematiquesSelectionnees.value = [];
    showCreateDialog.value = true;
}

function toggleMembre(id: number) {
    const idx = membresSelectionnes.value.indexOf(id);

    if (idx > -1) {
        membresSelectionnes.value.splice(idx, 1);
    } else {
        membresSelectionnes.value.push(id);
    }
}

const thematiquesMax = computed(() => thematiquesSelectionnees.value.length >= 3);

function toggleThematique(id: number) {
    const idx = thematiquesSelectionnees.value.indexOf(id);

    if (idx > -1) {
        thematiquesSelectionnees.value.splice(idx, 1);
    } else if (thematiquesSelectionnees.value.length < 3) {
        thematiquesSelectionnees.value.push(id);
    }
}

function submitCreate() {
    form.transform((data) => ({
        ...data,
        membres: membresSelectionnes.value,
        thematiques: thematiquesSelectionnees.value,
    })).post(`/cours/${props.cours.id}/classes/${props.classe.id}/groupes`, {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
            membresSelectionnes.value = [];
            thematiquesSelectionnees.value = [];
        },
    });
}

// Suppression réservée aux enseignants/admins — le bouton n'est plus affiché ici

// ─── Échéancier (progression personnelle) ─────────────────────────────────────
const echeancierParSemaine = computed(() => {
    const map = new Map<number, EcheancierEtape[]>();

    for (const etape of props.echeancierEtapes) {
        if (!map.has(etape.semaine)) {
            map.set(etape.semaine, []);
        }
        map.get(etape.semaine)!.push(etape);
    }

    return map;
});

const semaines = computed(() =>
    [...echeancierParSemaine.value.keys()].sort((a, b) => a - b),
);

const toggleLoadingEtudiantId = ref<number | null>(null);

function toggleEtapeEtudiant(etape: EcheancierEtape) {
    toggleLoadingEtudiantId.value = etape.id;
    router.patch(
        `/cours/${props.cours.id}/echeancier/${etape.id}/toggle-etudiant`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                toggleLoadingEtudiantId.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout>
        <Head :title="`${$t('classes.groupes.heading')} — ${cours.nom_cours}`" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/cours/${cours.id}/classes`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('classes.groupes.back') }}
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="`${$t('classes.groupes.heading')} — ${cours.nom_cours}`"
                :description="`${cours.code} — Groupe ${cours.groupe} · Section ${classe.code}`"
            />

            <!-- Mon groupe -->
            <template v-if="monGroupe">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            {{ $t('classes.groupes.group_number', { n: monGroupe.numero }) }}
                        </CardTitle>
                        <div class="flex gap-2">
                            <Button size="sm" as-child>
                                <Link :href="`/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${monGroupe.id}`">{{ $t('classes.groupes.access') }}</Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <!-- Membres -->
                        <div>
                            <p class="text-muted-foreground mb-2 text-sm font-medium">{{ $t('groupes.show.members') }}</p>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="membre in monGroupe.membres"
                                    :key="membre.id"
                                    class="bg-muted rounded-full px-3 py-1 text-sm"
                                >
                                    {{ membre.prenom }} {{ membre.nom }}
                                </span>
                            </div>
                        </div>

                        <!-- Thématiques -->
                        <div v-if="monGroupe.thematiques.length > 0">
                            <p class="text-muted-foreground mb-2 text-sm font-medium">{{ $t('groupes.show.thematic') }}</p>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="thematique in monGroupe.thematiques"
                                    :key="thematique.id"
                                    class="bg-primary/10 text-primary rounded-full px-3 py-1 text-sm"
                                >
                                    {{ thematique.nom }}
                                    <span
                                        v-if="thematique.periode_historique"
                                        class="text-muted-foreground ml-1 text-xs"
                                    >
                                        ({{ thematique.periode_historique }})
                                    </span>
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Pas encore de groupe -->
            <template v-else>
                <div class="flex flex-col items-center gap-4 py-12">
                    <p class="text-muted-foreground text-center">
                        {{ $t('classes.groupes.no_group') }}
                    </p>
                    <Button @click="openCreate">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('classes.groupes.create_group') }}
                    </Button>
                </div>
            </template>

            <!-- Documents de la classe -->
            <Card v-if="documents.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        {{ $t('classes.groupes.course_documents') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="doc in documents"
                            :key="doc.id"
                            class="flex items-center justify-between py-3"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <FileText class="text-muted-foreground h-4 w-4 shrink-0" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ doc.nom_original }}</p>
                                    <p class="text-muted-foreground text-xs">{{ formatTaille(doc.taille) }}</p>
                                </div>
                            </div>
                            <a :href="doc.url" target="_blank" download>
                                <Button size="sm" variant="ghost">
                                    <Download class="h-4 w-4" />
                                </Button>
                            </a>
                        </li>
                    </ul>
                </CardContent>
            </Card>
            <!-- Échéancier — progression personnelle -->
            <Card v-if="echeancierEtapes.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        Mon avancement — Échéancier
                        <span class="text-sm font-normal text-muted-foreground">
                            ({{ echeancierEtapes.filter((e) => e.is_done_etudiant).length }}/{{ echeancierEtapes.length }})
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <div v-for="semaine in semaines" :key="semaine">
                            <p class="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                Semaine {{ semaine }}
                            </p>
                            <ul class="space-y-2">
                                <li
                                    v-for="etape in echeancierParSemaine.get(semaine)"
                                    :key="etape.id"
                                    class="flex items-start gap-3"
                                >
                                    <Checkbox
                                        :id="`etudiant-etape-${etape.id}`"
                                        :checked="etape.is_done_etudiant"
                                        :disabled="toggleLoadingEtudiantId === etape.id"
                                        class="mt-0.5 shrink-0"
                                        @click.prevent="toggleEtapeEtudiant(etape)"
                                    />
                                    <label
                                        :for="`etudiant-etape-${etape.id}`"
                                        class="flex-1 cursor-pointer text-sm leading-snug"
                                        :class="etape.is_done_etudiant ? 'text-muted-foreground line-through' : ''"
                                    >
                                        {{ etape.etape }}
                                    </label>
                                    <Check
                                        v-if="etape.is_done_etudiant"
                                        class="h-4 w-4 shrink-0 text-green-500"
                                    />
                                </li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Dialog création de groupe -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ $t('classes.groupes.modal_create_group') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submitCreate">
                    <!-- Membres -->
                    <div v-if="autresEtudiants.length > 0" class="grid gap-2">
                        <Label>{{ $t('classes.groupes.modal_invite_members') }}</Label>
                        <div class="space-y-2">
                            <div
                                v-for="etudiant in autresEtudiants"
                                :key="etudiant.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`membre-${etudiant.id}`"
                                    :checked="membresSelectionnes.includes(etudiant.id)"
                                    @click.prevent="() => toggleMembre(etudiant.id)"
                                />
                                <Label :for="`membre-${etudiant.id}`" class="cursor-pointer font-normal">
                                    {{ etudiant.prenom }} {{ etudiant.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground text-sm">
                        {{ $t('classes.groupes.modal_no_other_students') }}
                    </p>

                    <!-- Thématiques -->
                    <div v-if="thematiques.length > 0" class="grid gap-2">
                        <Label>
                            {{ $t('classes.groupes.modal_thematic') }}
                            <span class="text-muted-foreground text-xs font-normal">
                                {{ $t('classes.groupes.modal_thematic_max') }} {{ thematiquesSelectionnees.length }}/3 sélectionnée{{ thematiquesSelectionnees.length > 1 ? 's' : '' }})
                            </span>
                        </Label>
                        <div class="space-y-2">
                            <div
                                v-for="thematique in thematiques"
                                :key="thematique.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`thematique-${thematique.id}`"
                                    :checked="thematiquesSelectionnees.includes(thematique.id)"
                                    :disabled="thematiquesMax && !thematiquesSelectionnees.includes(thematique.id)"
                                    @click.prevent="() => toggleThematique(thematique.id)"
                                />
                                <Label
                                    :for="`thematique-${thematique.id}`"
                                    class="cursor-pointer font-normal"
                                    :class="{ 'text-muted-foreground': thematiquesMax && !thematiquesSelectionnees.includes(thematique.id) }"
                                >
                                    {{ thematique.nom }}
                                    <span
                                        v-if="thematique.periode_historique"
                                        class="text-muted-foreground text-xs"
                                    >
                                        — {{ thematique.periode_historique }}
                                    </span>
                                </Label>
                            </div>
                        </div>
                        <p v-if="form.errors.thematiques" class="text-destructive text-sm">
                            {{ form.errors.thematiques }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateDialog = false">
                            {{ $t('common.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ $t('classes.groupes.modal_create') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
