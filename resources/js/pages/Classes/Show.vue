<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookMarked,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    Circle,
    ExternalLink,
    FileBarChart,
    FileText,
    ListChecks,
    Pencil,
    Sigma,
    Trash2,
    Upload,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { apercuNotesAccumulees } from '@/actions/App/Http/Controllers/ClasseController';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type Membre = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
};

type Temoin = {
    id: number;
    prenom: string;
    nom: string;
} | null;

type Groupe = {
    id: number;
    numero: number;
    classe_id: number;
    created_by: number;
    membres: Membre[];
    thematiques: Thematique[];
    temoin: Temoin;
};

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    no_da: string | null;
    pivot?: {
        statut_cours: string | null;
    };
};

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Classe = {
    id: number;
    numero: string;
    code: string;
    nom: string | null;
    jour_semaine: string | null;
    plage_horaire: string | null;
    cours_id: number;
    groupes: Groupe[];
    etudiants: Etudiant[];
};

type TypeProjet = {
    id: number;
    nom: string;
    ponderation: number | null;
    is_sommatif: boolean;
};

type EcheancierEtape = {
    id: number;
    semaine: number;
    etape: string;
    is_done: boolean;
    ordre: number;
    etudiant_done: boolean;
};

type Document = {
    id: number;
    nom_original: string;
    url: string;
    type: string;
    taille: number;
};

type Objectif = {
    id: number;
    contenu: string;
    ordre: number;
};

type Reference = {
    id: number;
    nom: string;
    url: string | null;
    ordre: number;
};

type Props = {
    cours: Cours;
    classe: Classe;
    estEnseignant: boolean;
    typesProjets: TypeProjet[];
    echeancierEtapes: EcheancierEtape[];
    documents: Document[];
    objectifs: Objectif[];
    references: Reference[];
};

const props = defineProps<Props>();
const { t } = useI18n();

const page = usePage();

/** Groupe auquel appartient l'étudiant authentifié (null si non trouvé). */
const monGroupe = computed(
    () =>
        props.classe.groupes.find((g) =>
            g.membres.some((m) => m.id === (page.props.auth as any).user.id),
        ) ?? null,
);

// ─── Sections repliables ──────────────────────────────────────────────────────
const ouvert = ref({
    groupes: true,
    notes: true,
    etudiants: true,
    monGroupe: true,
    echeancier: true,
    documents: true,
    objectifs: true,
    references: true,
});

/** Vrai si au moins un TypeProjet a une pondération définie — affiche le bouton notes accumulées. */
const aPonderation = computed(() =>
    props.typesProjets.some((tp) => tp.ponderation !== null && tp.is_sommatif),
);

// ─── Échéancier (étudiants seulement) ─────────────────────────────────────────
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

function toggleEtape(etape: EcheancierEtape) {
    router.patch(
        `/cours/${props.cours.id}/echeancier/${etape.id}/toggle-etudiant`,
        {},
        { preserveScroll: true },
    );
}

// ─── Gestion étudiants de la section ──────────────────────────────────────────
const showAddEtudiantDialog = ref(false);
const showEditEtudiantDialog = ref(false);
const showImportDialog = ref(false);
const editingEtudiantId = ref<number | null>(null);

const addEtudiantForm = useForm({
    prenom: '',
    nom: '',
    no_da: '',
    statut_cours: '',
    email: '',
});

const editEtudiantForm = useForm({
    prenom: '',
    nom: '',
    email: '',
    no_da: '',
    statut_cours: '',
});

const importEtudiantForm = useForm({
    csv: null as File | null,
});

const deleteEtudiantForm = useForm({});

function openAddEtudiant(): void {
    addEtudiantForm.reset();
    showAddEtudiantDialog.value = true;
}

function submitAddEtudiant(): void {
    addEtudiantForm.post(
        `/cours/${props.cours.id}/classes/${props.classe.id}/etudiants`,
        {
            onSuccess: () => {
                showAddEtudiantDialog.value = false;
                addEtudiantForm.reset();
            },
        },
    );
}

function openEditEtudiant(etudiant: Etudiant): void {
    editingEtudiantId.value = etudiant.id;
    editEtudiantForm.prenom = etudiant.prenom;
    editEtudiantForm.nom = etudiant.nom;
    editEtudiantForm.email = etudiant.email;
    editEtudiantForm.no_da = etudiant.no_da ?? '';
    editEtudiantForm.statut_cours = etudiant.pivot?.statut_cours ?? '';
    showEditEtudiantDialog.value = true;
}

function submitEditEtudiant(): void {
    if (!editingEtudiantId.value) {
        return;
    }

    editEtudiantForm.put(
        `/cours/${props.cours.id}/classes/${props.classe.id}/etudiants/${editingEtudiantId.value}`,
        {
            onSuccess: () => {
                showEditEtudiantDialog.value = false;
            },
        },
    );
}

function removeEtudiant(etudiant: Etudiant): void {
    if (
        !confirm(
            t('classes.show.confirm_remove_student', {
                prenom: etudiant.prenom,
                nom: etudiant.nom,
            }),
        )
    ) {
        return;
    }

    deleteEtudiantForm.delete(
        `/cours/${props.cours.id}/classes/${props.classe.id}/etudiants/${etudiant.id}`,
    );
}

function handleImportFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;

    if (input.files && input.files[0]) {
        importEtudiantForm.csv = input.files[0];
    }
}

function submitImportEtudiant(): void {
    importEtudiantForm.post(
        `/cours/${props.cours.id}/classes/${props.classe.id}/etudiants/import`,
        {
            onSuccess: () => {
                showImportDialog.value = false;
                importEtudiantForm.reset();
            },
        },
    );
}

// ─── Supprimer un groupe ───────────────────────────────────────────────────────
const groupeASupprimer = ref<Groupe | null>(null);
const deleteGroupeForm = useForm({});

function confirmDeleteGroupe(groupe: Groupe) {
    groupeASupprimer.value = groupe;
}

function executeDeleteGroupe() {
    if (!groupeASupprimer.value) {
        return;
    }

    deleteGroupeForm.delete(
        `/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${groupeASupprimer.value.id}`,
        {
            onSuccess: () => {
                groupeASupprimer.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout>
        <Head :title="`${classe.code} — ${cours.nom_cours}`" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        :href="estEnseignant ? `/cours/${cours.id}` : '/cours'"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('classes.show.back_to_cours') }}
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="classe.nom ?? `Classe ${classe.numero}`"
                :description="`${cours.code} · ${classe.numero}${classe.jour_semaine || classe.plage_horaire ? ` · ${[classe.jour_semaine, classe.plage_horaire].filter(Boolean).join(' ')}` : ''} · ${cours.nom_cours}`"
            />

            <!-- Groupes dans la section — enseignant seulement -->
            <Card v-if="estEnseignant">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.groupes = !ouvert.groupes"
                    >
                        <Users class="h-5 w-5" />
                        <CardTitle>{{
                            $t('classes.show.groups_title')
                        }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground"
                            >({{ classe.groupes.length }})</span
                        >
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.groupes }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.groupes">
                    <div
                        v-if="classe.groupes.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('classes.show.no_groups') }}
                    </div>

                    <div
                        v-else
                        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="groupe in classe.groupes"
                            :key="groupe.id"
                            class="flex flex-col gap-3 rounded-lg border p-4"
                        >
                            <!-- En-tête du groupe -->
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium">
                                        {{
                                            $t('classes.groupes.group_number', {
                                                n: groupe.numero,
                                            })
                                        }}
                                    </p>
                                    <p
                                        v-if="groupe.temoin"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Témoin : {{ groupe.temoin.prenom }}
                                        {{ groupe.temoin.nom }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <BoutonTooltip
                                        texte="Accéder au détail de ce groupe"
                                        size="sm"
                                        variant="outline"
                                        as-child
                                    >
                                        <Link
                                            :href="`/cours/${cours.id}/classes/${classe.id}/groupes/${groupe.id}`"
                                        >
                                            <ExternalLink class="h-4 w-4" />
                                        </Link>
                                    </BoutonTooltip>
                                    <BoutonTooltip
                                        texte="Supprimer ce groupe"
                                        size="sm"
                                        variant="destructive"
                                        @click="confirmDeleteGroupe(groupe)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </BoutonTooltip>
                                </div>
                            </div>

                            <!-- Membres -->
                            <div>
                                <p
                                    class="mb-1 text-xs font-medium text-muted-foreground"
                                >
                                    {{ $t('groupes.show.members') }}
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="membre in groupe.membres"
                                        :key="membre.id"
                                        class="rounded-full bg-muted px-2 py-0.5 text-xs"
                                    >
                                        {{ membre.prenom }} {{ membre.nom }}
                                    </span>
                                    <span
                                        v-if="groupe.membres.length === 0"
                                        class="text-xs text-muted-foreground"
                                    >
                                        —
                                    </span>
                                </div>
                            </div>

                            <!-- Thématiques -->
                            <div v-if="groupe.thematiques.length > 0">
                                <p
                                    class="mb-1 text-xs font-medium text-muted-foreground"
                                >
                                    {{ $t('groupes.show.thematic') }}
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="th in groupe.thematiques"
                                        :key="th.id"
                                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                    >
                                        {{ th.nom }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Mon groupe — étudiant seulement -->
            <Card v-if="!estEnseignant && monGroupe">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.monGroupe = !ouvert.monGroupe"
                    >
                        <Users class="h-5 w-5" />
                        <CardTitle>{{
                            $t('classes.show.groups_title')
                        }}</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.monGroupe }"
                        />
                    </button>
                    <BoutonTooltip
                        texte="Accéder au détail de ce groupe"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link
                            :href="`/cours/${cours.id}/classes/${classe.id}/groupes/${monGroupe.id}`"
                        >
                            <ExternalLink class="h-4 w-4" />
                        </Link>
                    </BoutonTooltip>
                </CardHeader>
                <CardContent v-show="ouvert.monGroupe">
                    <div class="flex flex-col gap-3">
                        <!-- Numéro + témoin -->
                        <div>
                            <p class="text-sm font-medium">
                                {{
                                    $t('classes.groupes.group_number', {
                                        n: monGroupe.numero,
                                    })
                                }}
                            </p>
                            <p
                                v-if="monGroupe.temoin"
                                class="text-xs text-muted-foreground"
                            >
                                Témoin : {{ monGroupe.temoin.prenom }}
                                {{ monGroupe.temoin.nom }}
                            </p>
                        </div>

                        <!-- Membres -->
                        <div>
                            <p
                                class="mb-1 text-xs font-medium text-muted-foreground"
                            >
                                {{ $t('groupes.show.members') }}
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="membre in monGroupe.membres"
                                    :key="membre.id"
                                    class="rounded-full bg-muted px-2 py-0.5 text-xs"
                                >
                                    {{ membre.prenom }} {{ membre.nom }}
                                </span>
                                <span
                                    v-if="monGroupe.membres.length === 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    —
                                </span>
                            </div>
                        </div>

                        <!-- Thématiques -->
                        <div v-if="monGroupe.thematiques.length > 0">
                            <p
                                class="mb-1 text-xs font-medium text-muted-foreground"
                            >
                                {{ $t('groupes.show.thematic') }}
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="th in monGroupe.thematiques"
                                    :key="th.id"
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                >
                                    {{ th.nom }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Aperçu notes par TypeProjet — enseignant seulement -->
            <Card v-if="estEnseignant && typesProjets.length > 0">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.notes = !ouvert.notes"
                    >
                        <FileBarChart class="h-5 w-5" />
                        <CardTitle>Aperçu des notes</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.notes }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.notes" class="flex flex-col gap-4">
                    <!-- Notes par type de projet -->
                    <div class="flex flex-wrap gap-2">
                        <BoutonTooltip
                            v-for="tp in typesProjets"
                            :key="tp.id"
                            :texte="`Voir l'aperçu des notes — ${tp.nom}`"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="`/cours/${cours.id}/classes/${classe.id}/types-projets/${tp.id}/apercu-notes`"
                            >
                                <FileBarChart class="mr-2 h-4 w-4" />
                                {{ tp.nom }}
                                <span
                                    v-if="tp.ponderation !== null"
                                    class="ml-1 text-xs text-muted-foreground"
                                >
                                    ({{ tp.ponderation }} %)
                                </span>
                            </Link>
                        </BoutonTooltip>
                    </div>

                    <!-- Bouton notes accumulées — visible si au moins un TypeProjet a une pondération -->
                    <div v-if="aPonderation" class="border-t pt-3">
                        <BoutonTooltip
                            texte="Voir le total pondéré de tous les types de projets"
                            variant="default"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="
                                    apercuNotesAccumulees({ cours, classe }).url
                                "
                            >
                                <Sigma class="mr-2 h-4 w-4" />
                                Notes accumulées
                            </Link>
                        </BoutonTooltip>
                    </div>
                </CardContent>
            </Card>

            <!-- Étudiants de la section — enseignant seulement -->
            <Card v-if="estEnseignant">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.etudiants = !ouvert.etudiants"
                    >
                        <CardTitle>{{ $t('classes.show.students') }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground"
                            >({{ classe.etudiants.length }})</span
                        >
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.etudiants }"
                        />
                    </button>
                    <div v-if="estEnseignant" class="flex gap-2">
                        <BoutonTooltip
                            texte="Importer une liste d'étudiants depuis un fichier CSV"
                            size="sm"
                            variant="outline"
                            @click="showImportDialog = true"
                        >
                            <Upload class="mr-2 h-4 w-4" />
                            {{ $t('classes.show.import_csv') }}
                        </BoutonTooltip>
                        <Button size="sm" @click="openAddEtudiant">
                            {{ $t('classes.show.add_student') }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent v-show="ouvert.etudiants">
                    <div
                        v-if="classe.etudiants.length === 0"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('classes.show.no_students') }}
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pr-4 pb-3 font-medium">
                                        {{ $t('classes.show.table_header_da') }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t('classes.show.table_header_name')
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'classes.show.table_header_first_name',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'classes.show.table_header_email',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'classes.show.table_header_status',
                                            )
                                        }}
                                    </th>
                                    <th
                                        v-if="estEnseignant"
                                        class="pb-3 font-medium"
                                    >
                                        {{
                                            $t(
                                                'classes.show.table_header_actions',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="etudiant in classe.etudiants"
                                    :key="etudiant.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-mono text-xs">
                                        {{ etudiant.no_da ?? '—' }}
                                    </td>
                                    <td class="py-3 pr-4 font-medium">
                                        {{ etudiant.nom }}
                                    </td>
                                    <td class="py-3 pr-4">
                                        {{ etudiant.prenom }}
                                    </td>
                                    <td
                                        class="py-3 pr-4 text-xs text-muted-foreground"
                                    >
                                        {{ etudiant.email }}
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span
                                            v-if="etudiant.pivot?.statut_cours"
                                            class="rounded bg-muted px-2 py-0.5 text-xs"
                                        >
                                            {{ etudiant.pivot.statut_cours }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td v-if="estEnseignant" class="py-3">
                                        <div class="flex gap-2">
                                            <BoutonTooltip
                                                texte="Modifier les informations de cet étudiant"
                                                size="sm"
                                                variant="outline"
                                                @click="
                                                    openEditEtudiant(etudiant)
                                                "
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                texte="Retirer cet étudiant de la classe"
                                                size="sm"
                                                variant="destructive"
                                                @click="
                                                    removeEtudiant(etudiant)
                                                "
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </BoutonTooltip>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <!-- Objectifs du cours — étudiants seulement -->
            <Card v-if="!estEnseignant">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.objectifs = !ouvert.objectifs"
                    >
                        <ListChecks class="h-5 w-5" />
                        <CardTitle>Objectifs du cours</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.objectifs }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.objectifs">
                    <div
                        v-if="objectifs.length === 0"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Aucun objectif défini pour ce cours.
                    </div>
                    <ol v-else class="flex flex-col gap-2">
                        <li
                            v-for="objectif in objectifs"
                            :key="objectif.id"
                            class="flex items-start gap-3 text-sm"
                        >
                            <span
                                class="mt-0.5 shrink-0 font-mono text-xs text-muted-foreground"
                            >
                                {{ objectif.ordre }}.
                            </span>
                            <span>{{ objectif.contenu }}</span>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <!-- Références bibliographiques — étudiants seulement -->
            <Card v-if="!estEnseignant">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.references = !ouvert.references"
                    >
                        <BookMarked class="h-5 w-5" />
                        <CardTitle>Références bibliographiques</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.references }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.references">
                    <div
                        v-if="references.length === 0"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Aucune référence bibliographique pour ce cours.
                    </div>
                    <ol v-else class="space-y-1.5">
                        <li
                            v-for="(reference, index) in [...references].sort(
                                (a, b) => a.ordre - b.ordre,
                            )"
                            :key="reference.id"
                            class="flex items-center gap-2 rounded-md border bg-card px-3 py-2"
                        >
                            <span
                                class="w-5 shrink-0 text-right text-xs font-medium text-muted-foreground"
                            >
                                {{ index + 1 }}.
                            </span>
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
                                :class="
                                    reference.url ? 'group cursor-pointer' : ''
                                "
                            >
                                <span
                                    class="truncate text-sm"
                                    :class="
                                        reference.url
                                            ? 'group-hover:underline'
                                            : ''
                                    "
                                    >{{ reference.nom }}</span
                                >
                                <ExternalLink
                                    v-if="reference.url"
                                    class="h-3.5 w-3.5 shrink-0 text-muted-foreground group-hover:text-primary"
                                />
                            </component>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <!-- Échéancier du cours — étudiants seulement -->
            <Card v-if="!estEnseignant">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.echeancier = !ouvert.echeancier"
                    >
                        <CalendarDays class="h-5 w-5" />
                        <CardTitle>{{
                            $t('cours.classes.schedule_title')
                        }}</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.echeancier }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.echeancier">
                    <div
                        v-if="echeancierEtapes.length === 0"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('cours.classes.schedule_empty') }}
                    </div>
                    <div v-else class="flex flex-col gap-4">
                        <div v-for="semaine in semaines" :key="semaine">
                            <p
                                class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {{
                                    $t('cours.classes.schedule_week', {
                                        n: semaine,
                                    })
                                }}
                            </p>
                            <ul class="flex flex-col gap-1">
                                <li
                                    v-for="etape in echeancierParSemaine.get(
                                        semaine,
                                    )"
                                    :key="etape.id"
                                    class="flex items-start gap-2"
                                >
                                    <button
                                        class="mt-0.5 shrink-0 text-muted-foreground transition-colors hover:text-primary"
                                        :class="{
                                            'text-primary': etape.etudiant_done,
                                        }"
                                        @click="toggleEtape(etape)"
                                    >
                                        <CheckCircle2
                                            v-if="etape.etudiant_done"
                                            class="h-4 w-4"
                                        />
                                        <Circle v-else class="h-4 w-4" />
                                    </button>
                                    <span
                                        class="text-sm"
                                        :class="{
                                            'text-muted-foreground line-through':
                                                etape.is_done,
                                            'font-medium': etape.etudiant_done,
                                        }"
                                    >
                                        {{ etape.etape }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Documents du cours — étudiants seulement -->
            <Card v-if="!estEnseignant">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.documents = !ouvert.documents"
                    >
                        <FileText class="h-5 w-5" />
                        <CardTitle>Documents du cours</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.documents }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.documents">
                    <div
                        v-if="documents.length === 0"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Aucun document disponible pour l'instant.
                    </div>
                    <div v-else class="flex flex-col divide-y">
                        <a
                            v-for="doc in documents"
                            :key="doc.id"
                            :href="doc.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="-mx-6 flex items-center gap-3 px-6 py-3 transition-colors hover:bg-muted/50"
                        >
                            <FileText
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                            <span class="min-w-0 flex-1 truncate text-sm">{{
                                doc.nom_original
                            }}</span>
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ doc.type.toUpperCase() }}
                            </span>
                        </a>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Confirmer suppression groupe -->
        <ConfirmationModal
            :open="groupeASupprimer !== null"
            :title="
                $t('classes.groupes.confirm_delete_group', {
                    numero: groupeASupprimer?.numero ?? '',
                })
            "
            :is-loading="deleteGroupeForm.processing"
            @update:open="
                (v) => {
                    if (!v) groupeASupprimer = null;
                }
            "
            @confirm="executeDeleteGroupe"
        />

        <Dialog v-model:open="showAddEtudiantDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        $t('classes.show.modal_add_student')
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitAddEtudiant">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="add-prenom">{{
                                $t('classes.show.modal_first_name')
                            }}</Label>
                            <Input
                                id="add-prenom"
                                v-model="addEtudiantForm.prenom"
                            />
                            <InputError
                                :message="addEtudiantForm.errors.prenom"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="add-nom">{{
                                $t('classes.show.modal_name')
                            }}</Label>
                            <Input id="add-nom" v-model="addEtudiantForm.nom" />
                            <InputError :message="addEtudiantForm.errors.nom" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-da">{{
                            $t('classes.show.modal_da_number')
                        }}</Label>
                        <Input id="add-da" v-model="addEtudiantForm.no_da" />
                        <InputError :message="addEtudiantForm.errors.no_da" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-statut">{{
                            $t('classes.show.modal_course_status')
                        }}</Label>
                        <Input
                            id="add-statut"
                            v-model="addEtudiantForm.statut_cours"
                        />
                        <InputError
                            :message="addEtudiantForm.errors.statut_cours"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-email">{{
                            $t('classes.show.modal_email')
                        }}</Label>
                        <Input
                            id="add-email"
                            v-model="addEtudiantForm.email"
                            type="email"
                        />
                        <InputError :message="addEtudiantForm.errors.email" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddEtudiantDialog = false"
                        >
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="addEtudiantForm.processing"
                        >
                            {{ $t('classes.show.modal_add') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showEditEtudiantDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        $t('classes.show.modal_edit_student')
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEditEtudiant">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>{{
                                $t('classes.show.modal_first_name')
                            }}</Label>
                            <Input v-model="editEtudiantForm.prenom" />
                            <InputError
                                :message="editEtudiantForm.errors.prenom"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{ $t('classes.show.modal_name') }}</Label>
                            <Input v-model="editEtudiantForm.nom" />
                            <InputError
                                :message="editEtudiantForm.errors.nom"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('classes.show.modal_email') }}</Label>
                        <Input v-model="editEtudiantForm.email" type="email" />
                        <InputError :message="editEtudiantForm.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('classes.show.modal_da_number') }}</Label>
                        <Input v-model="editEtudiantForm.no_da" />
                        <InputError :message="editEtudiantForm.errors.no_da" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            $t('classes.show.modal_course_status')
                        }}</Label>
                        <Input v-model="editEtudiantForm.statut_cours" />
                        <InputError
                            :message="editEtudiantForm.errors.statut_cours"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditEtudiantDialog = false"
                        >
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="editEtudiantForm.processing"
                        >
                            {{ $t('classes.show.modal_save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showImportDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        $t('classes.show.modal_import_csv')
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitImportEtudiant">
                    <p class="text-sm text-muted-foreground">
                        {{ $t('classes.show.modal_csv_format') }}
                        <code>;</code>) :
                    </p>
                    <code class="block rounded bg-muted p-3 text-xs">
                        {{ $t('classes.show.modal_csv_fields') }}
                    </code>
                    <div class="grid gap-2">
                        <Label for="csv-file">{{
                            $t('classes.show.modal_csv_file')
                        }}</Label>
                        <Input
                            id="csv-file"
                            type="file"
                            accept=".csv,.txt"
                            @change="handleImportFileChange"
                        />
                        <InputError :message="importEtudiantForm.errors.csv" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showImportDialog = false"
                        >
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="
                                importEtudiantForm.processing ||
                                !importEtudiantForm.csv
                            "
                        >
                            {{ $t('classes.show.modal_import') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
