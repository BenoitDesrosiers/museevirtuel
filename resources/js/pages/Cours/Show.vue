<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    Calendar,
    Check,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    Copy,
    Download,
    FileText,
    Grid2x2,
    Pencil,
    Plus,
    Trash2,
    Upload,
    Users,
    Video,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import CoursObjectifs from '@/components/CoursObjectifs.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import VisioSession from '@/components/VisioSession.vue';
import { Badge } from '@/components/ui/badge';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import typesProjetsRoutes from '@/routes/types-projets';

type Classe = {
    id: number;
    numero: string;
    code: string;
    nom: string | null;
    jour_semaine: string | null;
    plage_horaire: string | null;
    cours_id: number;
    etudiants_count: number;
    groupes_count: number;
};

type Document = {
    id: number;
    nom_original: string;
    type: string;
    taille: number;
    url: string;
};

type Cours = {
    id: number;
    nom_cours: string;
    description: string | null;
    code: string;
    groupe: string;
    annee: number;
    session: 'hiver' | 'ete' | 'automne';
    is_verrouille: boolean;
};

type EcheancierEtape = {
    id: number;
    semaine: number;
    periode: number | null;
    etape: string;
    is_done: boolean;
    ordre: number;
};

type Objectif = {
    id: number;
    contenu: string;
    ordre: number;
};

type GrilleResume = { id: number; nom: string } | null;

type TypeProjetSection = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
};

type TypeProjet = {
    id: number;
    nom: string;
    description: string | null;
    accessible: boolean;
    grille: GrilleResume;
    sections: TypeProjetSection[];
};

type VisioConference = {
    id: number;
    cours_id: number;
    groupe_id: number | null;
    jitsi_room: string;
    titre: string;
    scheduled_at: string | null;
    started_at: string | null;
    ended_at: string | null;
    recording_url: string | null;
    animateur: { id: number; prenom: string; nom: string };
};

type Props = {
    cours: Cours;
    classes: Classe[];
    documents: Document[];
    echeancierEtapes: EcheancierEtape[];
    objectifs: Objectif[];
    typesProjets: TypeProjet[];
    visioConferences: VisioConference[];
};

const props = defineProps<Props>();
const { t } = useI18n();

// ─── Sections repliables ──────────────────────────────────────────────────────
const ouvert = ref({
    classes: true,
    documents: true,
    objectifs: true,
    typesProjets: true,
    echeancier: true,
    visios: true,
});

// ─── Créer une classe ─────────────────────────────────────────────────────────
const showCreateClasseDialog = ref(false);
const createClasseForm = useForm({
    numero: '',
    nom: '',
    jour_semaine: '',
    plage_horaire: '',
});

function submitCreateClasse() {
    createClasseForm.post(`/cours/${props.cours.id}/classes`, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateClasseDialog.value = false;
            createClasseForm.reset();
        },
    });
}

// ─── Échéancier ───────────────────────────────────────────────────────────────
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

const toggleLoadingId = ref<number | null>(null);

function toggleEtape(etape: EcheancierEtape) {
    toggleLoadingId.value = etape.id;
    router.patch(
        `/cours/${props.cours.id}/echeancier/${etape.id}/toggle`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                toggleLoadingId.value = null;
            },
        },
    );
}

const showAddEtapeDialog = ref(false);
const addEtapeForm = useForm({ semaine: 1, periode: null as number | null, etape: '' });

function submitAddEtape() {
    addEtapeForm.post(`/cours/${props.cours.id}/echeancier`, {
        preserveScroll: true,
        onSuccess: () => {
            showAddEtapeDialog.value = false;
            addEtapeForm.reset();
        },
    });
}

const editingEtape = ref<EcheancierEtape | null>(null);
const editEtapeForm = useForm({ etape: '', periode: null as number | null });

function openEditEtape(etape: EcheancierEtape) {
    editingEtape.value = etape;
    editEtapeForm.etape = etape.etape;
    editEtapeForm.periode = etape.periode ?? null;
}

function submitEditEtape() {
    if (!editingEtape.value) return;

    editEtapeForm.put(
        `/cours/${props.cours.id}/echeancier/${editingEtape.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                editingEtape.value = null;
                editEtapeForm.reset();
            },
        },
    );
}

function handleClasseDialogUpdate(isOpen: boolean) {
    if (!isOpen) classeASupprimer.value = null;
}

const deleteEtapeForm = useForm({});

function deleteEtape(etape: EcheancierEtape) {
    deleteEtapeForm.delete(
        `/cours/${props.cours.id}/echeancier/${etape.id}`,
        {
            preserveScroll: true,
        },
    );
}

const showViderEcheancierModal = ref(false);
const destroyAllForm = useForm({});

function destroyAllEtapes() {
    destroyAllForm.delete(`/cours/${props.cours.id}/echeancier`, {
        preserveScroll: true,
        onSuccess: () => {
            showViderEcheancierModal.value = false;
        },
    });
}

// ─── Supprimer une classe ─────────────────────────────────────────────────────
const classeASupprimer = ref<Classe | null>(null);
const deleteClasseForm = useForm({});

function confirmDeleteClasse(classe: Classe) {
    classeASupprimer.value = classe;
}

function executeDeleteClasse() {
    if (!classeASupprimer.value) {
        return;
    }

    deleteClasseForm.delete(
        `/cours/${props.cours.id}/classes/${classeASupprimer.value.id}`,
        {
            onSuccess: () => {
                classeASupprimer.value = null;
            },
        },
    );
}

// ─── Documents ────────────────────────────────────────────────────────────────
const docFileInput = ref<HTMLInputElement | null>(null);
const docForm = useForm({ document: null as File | null });

function handleDocChange(e: Event) {
    const input = e.target as HTMLInputElement;

    if (input.files && input.files[0]) {
        docForm.document = input.files[0];
        docForm.post(`/cours/${props.cours.id}/documents`, {
            onSuccess: () => {
                docForm.reset();

                if (docFileInput.value) {
                    docFileInput.value.value = '';
                }
            },
        });
    }
}

const deleteDocForm = useForm({});

function removeDocument(doc: Document) {
    if (
        !confirm(
            t('classes.show.confirm_delete_document', {
                nom: doc.nom_original,
            }),
        )
    ) {
        return;
    }

    deleteDocForm.delete(`/cours/${props.cours.id}/documents/${doc.id}`);
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} o`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} Ko`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}

// ─── Types de projet ──────────────────────────────────────────────────────────
const toggleTpForm = useForm({});

function toggleAccessibleTp(tp: TypeProjet) {
    toggleTpForm.patch(typesProjetsRoutes.toggleAccessible.url({ cours: props.cours.id, typeProjet: tp.id }), {
        preserveScroll: true,
    });
}

const deleteTpForm = useForm({});

function supprimerTp(tp: TypeProjet) {
    if (!confirm(`Supprimer « ${tp.nom} » ? Cette action supprimera également la grille de correction associée et ne peut pas être annulée.`)) {
        return;
    }
    deleteTpForm.delete(typesProjetsRoutes.destroy.url({ cours: props.cours.id, typeProjet: tp.id }), {
        preserveScroll: true,
    });
}

// ─── Visioconférences ─────────────────────────────────────────────────────────
const showVisioDialog = ref(false);
const visioForm = useForm({
    titre: '',
    groupe_id: null as number | null,
    scheduled_at: '',
});

function submitVisio() {
    visioForm.post(`/cours/${props.cours.id}/visio`, {
        preserveScroll: true,
        onSuccess: () => {
            showVisioDialog.value = false;
            visioForm.reset();
        },
    });
}

// ─── Transfert de cours ────────────────────────────────────────────────────────
const showTransfertDialog = ref(false);
const transfertForm = useForm({
    annee: props.cours.annee as number,
    session: props.cours.session as 'hiver' | 'ete' | 'automne',
});

function ouvrirTransfert() {
    transfertForm.annee = props.cours.annee;
    transfertForm.session = props.cours.session;
    showTransfertDialog.value = true;
}

function submitTransfert() {
    transfertForm.post(`/cours/${props.cours.id}/transferer`, {
        onSuccess: () => {
            showTransfertDialog.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="`${cours.code} — ${cours.nom_cours}`" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Breadcrumb retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link href="/enseignant">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('classes.show.back') }}
                    </Link>
                </Button>
            </div>

            <!-- En-tête du cours -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <Heading
                        :title="`${cours.code} — Groupe ${cours.groupe}`"
                        :description="cours.nom_cours"
                    />
                    <div class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span>{{ { hiver: 'Hiver', ete: 'Été', automne: 'Automne' }[cours.session] }} {{ cours.annee }}</span>
                        <span v-if="cours.description">· {{ cours.description }}</span>
                    </div>
                </div>
                <Button variant="outline" size="sm" @click="ouvrirTransfert">
                    <Copy class="mr-2 h-4 w-4" />
                    Transférer
                </Button>
            </div>

            <!-- Classes du cours -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.classes = !ouvert.classes"
                    >
                        <Users class="h-5 w-5" />
                        <CardTitle>{{ $t('cours.show.classes_title') }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">({{ classes.length }})</span>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.classes }"
                        />
                    </button>
                    <Button size="sm" @click="showCreateClasseDialog = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Classe
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.classes">
                    <div v-if="classes.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                        {{ $t('cours.show.no_classes') }}
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="classe in classes"
                            :key="classe.id"
                            class="flex flex-col gap-3 rounded-lg border p-4"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ classe.nom ?? `Classe ${classe.numero}` }}
                                    </p>
                                    <p class="font-mono text-xs text-muted-foreground">{{ classe.code }} · {{ classe.numero }}</p>
                                    <p v-if="classe.jour_semaine || classe.plage_horaire" class="text-xs text-muted-foreground">
                                        {{ [classe.jour_semaine, classe.plage_horaire].filter(Boolean).join(' · ') }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <BoutonTooltip
                                        texte="Accéder au détail de cette section (groupes et étudiants)"
                                        size="sm"
                                        variant="outline"
                                        as-child
                                    >
                                        <Link :href="`/cours/${cours.id}/classes/${classe.id}`">
                                            {{ $t('cours.show.classes_see') }}
                                        </Link>
                                    </BoutonTooltip>
                                    <BoutonTooltip
                                        texte="Supprimer cette section"
                                        size="sm"
                                        variant="destructive"
                                        @click="confirmDeleteClasse(classe)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </BoutonTooltip>
                                </div>
                            </div>

                            <!-- Compteurs -->
                            <div class="flex gap-4 text-xs text-muted-foreground">
                                <span>{{ classe.etudiants_count }} étudiant{{ classe.etudiants_count !== 1 ? 's' : '' }}</span>
                                <span>{{ classe.groupes_count }} groupe{{ classe.groupes_count !== 1 ? 's' : '' }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Documents du cours -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.documents = !ouvert.documents"
                    >
                        <FileText class="h-5 w-5" />
                        <CardTitle>{{ $t('classes.show.documents_title') }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">({{ documents.length }})</span>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.documents }"
                        />
                    </button>
                    <div>
                        <input
                            ref="docFileInput"
                            type="file"
                            accept=".pdf,.doc,.docx"
                            class="hidden"
                            @change="handleDocChange"
                        />
                        <Button size="sm" :disabled="docForm.processing" @click="docFileInput?.click()">
                            <Upload class="mr-2 h-4 w-4" />
                            {{ $t('classes.show.add_document') }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent v-show="ouvert.documents">
                    <p v-if="docForm.errors.document" class="mb-3 text-sm text-destructive">
                        {{ docForm.errors.document }}
                    </p>

                    <div v-if="documents.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                        {{ $t('classes.show.no_documents') }}
                    </div>

                    <div v-else class="flex flex-col divide-y">
                        <div
                            v-for="doc in documents"
                            :key="doc.id"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <FileText class="h-5 w-5 shrink-0 text-muted-foreground" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ doc.nom_original }}</p>
                                    <p class="text-xs text-muted-foreground uppercase">
                                        {{ doc.type }} · {{ formatSize(doc.taille) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <BoutonTooltip
                                    texte="Télécharger ce document"
                                    size="sm"
                                    variant="outline"
                                    as-child
                                >
                                    <a :href="doc.url" target="_blank" download>
                                        <Download class="h-4 w-4" />
                                    </a>
                                </BoutonTooltip>
                                <BoutonTooltip
                                    texte="Supprimer ce document"
                                    size="sm"
                                    variant="destructive"
                                    @click="removeDocument(doc)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </BoutonTooltip>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Objectifs pédagogiques -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.objectifs = !ouvert.objectifs"
                    >
                        <BookOpen class="h-5 w-5" />
                        <CardTitle>Objectifs pédagogiques</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">({{ objectifs.length }})</span>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.objectifs }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.objectifs">
                    <CoursObjectifs :cours-id="cours.id" :objectifs="objectifs" />
                </CardContent>
            </Card>

            <!-- Types de projet -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.typesProjets = !ouvert.typesProjets"
                    >
                        <ClipboardList class="h-5 w-5" />
                        <CardTitle>Types de projet</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">({{ typesProjets.length }})</span>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.typesProjets }"
                        />
                    </button>
                    <Button size="sm" as-child>
                        <Link :href="typesProjetsRoutes.create.url(cours.id)">
                            <Plus class="mr-2 h-4 w-4" />
                            Nouveau type
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.typesProjets">
                    <div v-if="typesProjets.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                        Aucun type de projet. Créez-en un pour commencer.
                    </div>
                    <div v-else class="flex flex-col divide-y">
                        <div
                            v-for="tp in typesProjets"
                            :key="tp.id"
                            class="flex items-start justify-between gap-3 py-3"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium">{{ tp.nom }}</span>
                                    <Badge
                                        :class="tp.accessible
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'"
                                        class="text-xs"
                                    >
                                        {{ tp.accessible ? 'Accessible' : 'Non accessible' }}
                                    </Badge>
                                </div>
                                <p v-if="tp.description" class="mt-0.5 text-xs text-muted-foreground">
                                    {{ tp.description }}
                                </p>
                                <div class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Grid2x2 class="h-3.5 w-3.5" />
                                    <span>Grille :</span>
                                    <Link
                                        :href="typesProjetsRoutes.grille.edit.url({ cours: cours.id, typeProjet: tp.id })"
                                        class="text-primary hover:underline"
                                    >
                                        {{ tp.grille ? tp.grille.nom : 'Configurer la grille' }}
                                    </Link>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <BoutonTooltip
                                    size="sm"
                                    :variant="tp.accessible ? 'outline' : 'secondary'"
                                    :texte="tp.accessible ? 'Masquer ce type de projet aux étudiants' : 'Rendre ce type de projet accessible aux étudiants'"
                                    class="h-7 text-xs"
                                    :disabled="toggleTpForm.processing"
                                    @click="toggleAccessibleTp(tp)"
                                >
                                    <ChevronRight v-if="!tp.accessible" class="mr-1 h-3 w-3" />
                                    <ChevronDown v-else class="mr-1 h-3 w-3" />
                                    {{ tp.accessible ? 'Masquer' : 'Rendre accessible' }}
                                </BoutonTooltip>
                                <BoutonTooltip
                                    texte="Modifier ce type de projet"
                                    size="icon"
                                    variant="ghost"
                                    class="h-7 w-7"
                                    as-child
                                >
                                    <Link :href="typesProjetsRoutes.edit.url({ cours: cours.id, typeProjet: tp.id })">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Link>
                                </BoutonTooltip>
                                <BoutonTooltip
                                    texte="Supprimer ce type de projet"
                                    size="icon"
                                    variant="ghost"
                                    class="h-7 w-7 text-muted-foreground hover:text-destructive"
                                    :disabled="deleteTpForm.processing"
                                    @click="supprimerTp(tp)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </BoutonTooltip>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Échéancier -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.echeancier = !ouvert.echeancier"
                    >
                        <Calendar class="h-5 w-5" />
                        <CardTitle>Échéancier</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">({{ echeancierEtapes.length }} étapes)</span>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.echeancier }"
                        />
                    </button>
                    <div class="flex gap-2">
                        <Button
                            v-if="echeancierEtapes.length > 0"
                            size="sm"
                            variant="outline"
                            class="text-destructive hover:text-destructive"
                            @click="showViderEcheancierModal = true"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Vider l'échéancier
                        </Button>
                        <Button size="sm" @click="showAddEtapeDialog = true">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter une étape
                        </Button>
                    </div>
                </CardHeader>
                <CardContent v-show="ouvert.echeancier">
                    <div v-if="echeancierEtapes.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                        Aucune étape dans l'échéancier.
                    </div>
                    <div v-else class="space-y-6">
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
                                        :id="`etape-${etape.id}`"
                                        :checked="etape.is_done"
                                        :disabled="toggleLoadingId === etape.id"
                                        class="mt-0.5 shrink-0"
                                        @click.prevent="toggleEtape(etape)"
                                    />
                                    <template v-if="editingEtape?.id === etape.id">
                                        <div class="flex flex-1 flex-wrap items-center gap-2">
                                            <Input
                                                v-model="editEtapeForm.etape"
                                                class="h-7 flex-1 text-sm"
                                                @keydown.enter.prevent="submitEditEtape"
                                                @keydown.escape="editingEtape = null"
                                            />
                                            <select
                                                v-model="editEtapeForm.periode"
                                                class="h-7 rounded-md border border-input bg-background px-2 text-xs focus:outline-none"
                                            >
                                                <option :value="null">—</option>
                                                <option :value="1">P1</option>
                                                <option :value="2">P2</option>
                                            </select>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 w-7 p-0"
                                                :disabled="editEtapeForm.processing"
                                                @click="submitEditEtape"
                                            >
                                                <Check class="h-4 w-4 text-green-600" />
                                            </Button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <label
                                            :for="`etape-${etape.id}`"
                                            class="flex flex-1 cursor-pointer items-baseline gap-2 text-sm leading-snug"
                                            :class="etape.is_done ? 'text-muted-foreground line-through' : ''"
                                        >
                                            {{ etape.etape }}
                                            <span
                                                v-if="etape.periode"
                                                class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground no-underline"
                                            >P{{ etape.periode }}</span>
                                        </label>
                                        <div class="flex shrink-0 gap-1">
                                            <BoutonTooltip
                                                texte="Modifier cette étape"
                                                size="icon-sm"
                                                variant="ghost"
                                                class="h-6 w-6"
                                                @click="openEditEtape(etape)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                texte="Supprimer cette étape de l'échéancier"
                                                size="icon-sm"
                                                variant="ghost"
                                                class="h-6 w-6 text-destructive hover:text-destructive"
                                                :disabled="deleteEtapeForm.processing"
                                                @click="deleteEtape(etape)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </BoutonTooltip>
                                        </div>
                                    </template>
                                </li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Visioconférences -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.visios = !ouvert.visios"
                    >
                        <Video class="h-5 w-5" />
                        <CardTitle>Visioconférences</CardTitle>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.visios }"
                        />
                    </button>
                    <Button size="sm" @click="showVisioDialog = true">
                        <Plus class="mr-1.5 h-4 w-4" />
                        Planifier
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.visios">
                    <div v-if="visioConferences.length === 0" class="text-sm text-muted-foreground">
                        Aucune visioconférence planifiée.
                    </div>
                    <div v-else class="flex flex-col gap-3">
                        <VisioSession
                            v-for="visio in visioConferences"
                            :key="visio.id"
                            :visio="visio"
                            :can-manage="true"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Planifier une visioconférence -->
        <Dialog v-model:open="showVisioDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Planifier une visioconférence</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitVisio">
                    <div class="grid gap-2">
                        <Label for="visio-titre">Titre</Label>
                        <Input
                            id="visio-titre"
                            v-model="visioForm.titre"
                            placeholder="Ex. Rencontre de lancement"
                            required
                        />
                        <p v-if="visioForm.errors.titre" class="text-sm text-destructive">
                            {{ visioForm.errors.titre }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="visio-scheduled">Date planifiée (optionnel)</Label>
                        <Input
                            id="visio-scheduled"
                            v-model="visioForm.scheduled_at"
                            type="datetime-local"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="visio-groupe">Groupe ciblé (optionnel)</Label>
                        <select
                            id="visio-groupe"
                            v-model="visioForm.groupe_id"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option :value="null">Tous les groupes du cours</option>
                            <template v-for="classe in classes" :key="classe.id">
                                <!-- Les groupes ne sont pas chargés ici, cette option est disponible via texte libre -->
                            </template>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Laissez vide pour une session ouverte à tous les groupes.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showVisioDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="visioForm.processing || !visioForm.titre.trim()">
                            Créer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Ajouter une étape à l'échéancier -->
        <Dialog v-model:open="showAddEtapeDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter une étape</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitAddEtape">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="add-semaine">Semaine</Label>
                            <Input
                                id="add-semaine"
                                v-model.number="addEtapeForm.semaine"
                                type="number"
                                min="1"
                                max="15"
                            />
                            <p v-if="addEtapeForm.errors.semaine" class="text-sm text-destructive">
                                {{ addEtapeForm.errors.semaine }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="add-periode">Période (optionnel)</Label>
                            <select
                                id="add-periode"
                                v-model="addEtapeForm.periode"
                                class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option :value="null">—</option>
                                <option :value="1">Période 1</option>
                                <option :value="2">Période 2</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-etape-texte">Description de l'étape</Label>
                        <Input
                            id="add-etape-texte"
                            v-model="addEtapeForm.etape"
                            placeholder="ex. Remise du plan provisoire"
                            maxlength="500"
                        />
                        <p v-if="addEtapeForm.errors.etape" class="text-sm text-destructive">
                            {{ addEtapeForm.errors.etape }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showAddEtapeDialog = false">
                            {{ $t('common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="addEtapeForm.processing || !addEtapeForm.etape.trim()"
                        >
                            Ajouter
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Créer une classe -->
        <Dialog v-model:open="showCreateClasseDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nouvelle classe</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCreateClasse">
                    <div class="grid gap-2">
                        <Label for="classe-numero">Numero (obligatoire)</Label>
                        <Input id="classe-numero" v-model="createClasseForm.numero" placeholder="Ex. 00001" maxlength="5" />
                        <InputError :message="createClasseForm.errors.numero" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="classe-nom">Nom (optionnel)</Label>
                        <Input id="classe-nom" v-model="createClasseForm.nom" placeholder="Ex. Classe du matin" />
                        <InputError :message="createClasseForm.errors.nom" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="classe-jour">Jour (optionnel)</Label>
                            <Input id="classe-jour" v-model="createClasseForm.jour_semaine" placeholder="Ex. Lundi" />
                            <InputError :message="createClasseForm.errors.jour_semaine" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="classe-horaire">Plage horaire (optionnel)</Label>
                            <Input id="classe-horaire" v-model="createClasseForm.plage_horaire" placeholder="Ex. 08:30 - 11:30" />
                            <InputError :message="createClasseForm.errors.plage_horaire" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateClasseDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="createClasseForm.processing || createClasseForm.numero.length !== 5">
                            Créer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Vider l'échéancier -->
        <ConfirmationModal
            :open="showViderEcheancierModal"
            title="Vider l'échéancier"
            description="Cette action supprimera toutes les étapes de l'échéancier pour ce cours. Cette opération ne peut pas être annulée."
            confirm-label="Oui, tout supprimer"
            :loading="destroyAllForm.processing"
            @update:open="showViderEcheancierModal = $event"
            @confirm="destroyAllEtapes"
        />

        <!-- Modal : Confirmer suppression classe -->
        <ConfirmationModal
            :open="classeASupprimer !== null"
            :title="`Supprimer ${classeASupprimer?.nom ?? classeASupprimer?.code ?? 'la section'}`"
            description="Cette action supprimera également le projet de recherche associé et ne peut pas être annulée."
            :loading="deleteClasseForm.processing"
            @update:open="handleClasseDialogUpdate"
            @confirm="executeDeleteClasse"
        />

        <!-- Modal : Transférer le cours -->
        <Dialog v-model:open="showTransfertDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Transférer ce cours</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    Une copie du cours sera créée avec l'échéancier, les objectifs, les documents et les types de projets (grilles incluses).
                    Les classes et les étudiants ne seront <strong>pas</strong> copiés.
                </p>
                <form class="space-y-4" @submit.prevent="submitTransfert">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Session</Label>
                            <Select v-model="transfertForm.session">
                                <SelectTrigger>
                                    <SelectValue placeholder="Session…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="hiver">Hiver</SelectItem>
                                    <SelectItem value="ete">Été</SelectItem>
                                    <SelectItem value="automne">Automne</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="transfertForm.errors.session" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Année</Label>
                            <Input v-model.number="transfertForm.annee" type="number" min="2000" max="2100" />
                            <InputError :message="transfertForm.errors.annee" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showTransfertDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="transfertForm.processing">
                            <Copy class="mr-2 h-4 w-4" />
                            Transférer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
