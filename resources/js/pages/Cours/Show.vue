<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    Check,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    Download,
    FileText,
    Grid2x2,
    Pencil,
    Plus,
    Trash2,
    Upload,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import typesProjetsRoutes from '@/routes/types-projets';

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    no_da: string;
    statut_cours: string | null;
};

type Classe = {
    id: number;
    code: string;
    nom: string | null;
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
};

type EcheancierEtape = {
    id: number;
    semaine: number;
    etape: string;
    is_done: boolean;
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

type Props = {
    cours: Cours;
    etudiants: Etudiant[];
    classes: Classe[];
    documents: Document[];
    echeancierEtapes: EcheancierEtape[];
    typesProjets: TypeProjet[];
};

const props = defineProps<Props>();
const { t } = useI18n();

// ─── Ajouter un étudiant ──────────────────────────────────────────────────────
const showAddDialog = ref(false);
const addForm = useForm({
    prenom: '',
    nom: '',
    no_da: '',
    statut_cours: '',
    email: '',
});

function openAdd() {
    addForm.reset();
    showAddDialog.value = true;
}

function submitAdd() {
    addForm.post(`/cours/${props.cours.id}/etudiants`, {
        onSuccess: () => {
            showAddDialog.value = false;
            addForm.reset();
        },
    });
}

// ─── Modifier un étudiant ─────────────────────────────────────────────────────
const showEditDialog = ref(false);
const editingEtudiantId = ref<number | null>(null);
const editForm = useForm({
    prenom: '',
    nom: '',
    email: '',
    no_da: '',
    statut_cours: '',
});

function openEdit(etudiant: Etudiant) {
    editingEtudiantId.value = etudiant.id;
    editForm.prenom = etudiant.prenom;
    editForm.nom = etudiant.nom;
    editForm.email = etudiant.email;
    editForm.no_da = etudiant.no_da;
    editForm.statut_cours = etudiant.statut_cours ?? '';
    showEditDialog.value = true;
}

function submitEdit() {
    if (!editingEtudiantId.value) {
        return;
    }

    editForm.put(
        `/cours/${props.cours.id}/etudiants/${editingEtudiantId.value}`,
        {
            onSuccess: () => {
                showEditDialog.value = false;
            },
        },
    );
}

// ─── Retirer un étudiant ──────────────────────────────────────────────────────
const deleteForm = useForm({});

function removeEtudiant(etudiant: Etudiant) {
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

    deleteForm.delete(`/cours/${props.cours.id}/etudiants/${etudiant.id}`);
}

// ─── Import CSV ───────────────────────────────────────────────────────────────
const showImportDialog = ref(false);
const importForm = useForm({ csv: null as File | null });

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;

    if (input.files && input.files[0]) {
        importForm.csv = input.files[0];
    }
}

function submitImport() {
    importForm.post(`/cours/${props.cours.id}/import`, {
        onSuccess: () => {
            showImportDialog.value = false;
            importForm.reset();
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
const addEtapeForm = useForm({ semaine: 1, etape: '' });

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
const editEtapeForm = useForm({ etape: '' });

function openEditEtape(etape: EcheancierEtape) {
    editingEtape.value = etape;
    editEtapeForm.etape = etape.etape;
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
const showCreateTpDialog = ref(false);
const createTpForm = useForm({
    nom: '',
    description: '',
    sections: [] as { label: string; description: string }[],
});

function ajouterSectionCreate() {
    createTpForm.sections.push({ label: '', description: '' });
}

function supprimerSectionCreate(idx: number) {
    createTpForm.sections.splice(idx, 1);
}

function creerTypeProjet() {
    createTpForm.post(typesProjetsRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateTpDialog.value = false;
            createTpForm.reset();
        },
    });
}

const toggleTpForm = useForm({});

function toggleAccessibleTp(tp: TypeProjet) {
    toggleTpForm.patch(typesProjetsRoutes.toggleAccessible.url(tp.id), {
        preserveScroll: true,
    });
}

const deleteTpForm = useForm({});

function supprimerTp(tp: TypeProjet) {
    if (!confirm(`Supprimer « ${tp.nom} » ? Cette action supprimera également la grille de correction associée et ne peut pas être annulée.`)) {
        return;
    }
    deleteTpForm.delete(typesProjetsRoutes.destroy.url(tp.id), {
        preserveScroll: true,
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
            <div class="flex flex-col gap-1">
                <Heading
                    :title="`${cours.code} — Groupe ${cours.groupe}`"
                    :description="cours.nom_cours"
                />
                <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
                    <span v-if="cours.description">{{ cours.description }}</span>
                </div>
            </div>

            <!-- Liste des étudiants -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        {{ $t('classes.show.students') }}
                        <span class="ml-2 text-sm font-normal text-muted-foreground">
                            ({{ etudiants.length }})
                        </span>
                    </CardTitle>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" @click="showImportDialog = true">
                            <Upload class="mr-2 h-4 w-4" />
                            {{ $t('classes.show.import_csv') }}
                        </Button>
                        <Button size="sm" @click="openAdd">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('classes.show.add_student') }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pr-4 pb-3 font-medium">{{ $t('classes.show.table_header_da') }}</th>
                                    <th class="pr-4 pb-3 font-medium">{{ $t('classes.show.table_header_name') }}</th>
                                    <th class="pr-4 pb-3 font-medium">{{ $t('classes.show.table_header_first_name') }}</th>
                                    <th class="pr-4 pb-3 font-medium">{{ $t('classes.show.table_header_email') }}</th>
                                    <th class="pr-4 pb-3 font-medium">{{ $t('classes.show.table_header_status') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('classes.show.table_header_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="etudiant in etudiants" :key="etudiant.id" class="border-b last:border-0">
                                    <td class="py-3 pr-4 font-mono text-xs">{{ etudiant.no_da }}</td>
                                    <td class="py-3 pr-4 font-medium">{{ etudiant.nom }}</td>
                                    <td class="py-3 pr-4">{{ etudiant.prenom }}</td>
                                    <td class="py-3 pr-4 text-xs text-muted-foreground">{{ etudiant.email }}</td>
                                    <td class="py-3 pr-4">
                                        <span v-if="etudiant.statut_cours" class="rounded bg-muted px-2 py-0.5 text-xs">
                                            {{ etudiant.statut_cours }}
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <Button size="sm" variant="outline" @click="openEdit(etudiant)">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button size="sm" variant="destructive" @click="removeEtudiant(etudiant)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="etudiants.length === 0">
                                    <td colspan="6" class="py-6 text-center text-muted-foreground">
                                        {{ $t('classes.show.no_students') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Classes du cours -->
            <Card>
                <CardHeader>
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            {{ $t('cours.show.classes_title') }}
                            <span class="text-sm font-normal text-muted-foreground">
                                ({{ classes.length }})
                            </span>
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
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
                                        {{ classe.nom ?? classe.code }}
                                    </p>
                                    <p class="font-mono text-xs text-muted-foreground">{{ classe.code }}</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <Button size="sm" variant="outline" as-child>
                                        <Link :href="`/cours/${cours.id}/classes/${classe.id}`">
                                            {{ $t('cours.show.classes_see') }}
                                        </Link>
                                    </Button>
                                    <Button size="sm" variant="destructive" @click="confirmDeleteClasse(classe)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
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
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            {{ $t('classes.show.documents_title') }}
                            <span class="text-sm font-normal text-muted-foreground">
                                ({{ documents.length }})
                            </span>
                        </span>
                    </CardTitle>
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
                <CardContent>
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
                                <Button size="sm" variant="outline" as-child>
                                    <a :href="doc.url" target="_blank" download>
                                        <Download class="h-4 w-4" />
                                    </a>
                                </Button>
                                <Button size="sm" variant="destructive" @click="removeDocument(doc)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Types de projet -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5" />
                            Types de projet
                            <span class="text-sm font-normal text-muted-foreground">
                                ({{ typesProjets.length }})
                            </span>
                        </span>
                    </CardTitle>
                    <Button size="sm" @click="showCreateTpDialog = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouveau type
                    </Button>
                </CardHeader>
                <CardContent>
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
                                        :href="typesProjetsRoutes.grille.edit.url(tp.id)"
                                        class="text-primary hover:underline"
                                    >
                                        {{ tp.grille ? tp.grille.nom : 'Configurer la grille' }}
                                    </Link>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <Button
                                    size="sm"
                                    :variant="tp.accessible ? 'outline' : 'secondary'"
                                    class="h-7 text-xs"
                                    :disabled="toggleTpForm.processing"
                                    @click="toggleAccessibleTp(tp)"
                                >
                                    <ChevronRight v-if="!tp.accessible" class="mr-1 h-3 w-3" />
                                    <ChevronDown v-else class="mr-1 h-3 w-3" />
                                    {{ tp.accessible ? 'Masquer' : 'Rendre accessible' }}
                                </Button>
                                <Button size="icon" variant="ghost" class="h-7 w-7" as-child>
                                    <Link :href="typesProjetsRoutes.edit.url(tp.id)" title="Modifier">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Link>
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="h-7 w-7 text-muted-foreground hover:text-destructive"
                                    :disabled="deleteTpForm.processing"
                                    @click="supprimerTp(tp)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Échéancier -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <Calendar class="h-5 w-5" />
                            Échéancier
                            <span class="text-sm font-normal text-muted-foreground">
                                ({{ echeancierEtapes.length }} étapes)
                            </span>
                        </span>
                    </CardTitle>
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
                <CardContent>
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
                                        <div class="flex flex-1 items-center gap-2">
                                            <Input
                                                v-model="editEtapeForm.etape"
                                                class="h-7 text-sm"
                                                @keydown.enter.prevent="submitEditEtape"
                                                @keydown.escape="editingEtape = null"
                                            />
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
                                            class="flex-1 cursor-pointer text-sm leading-snug"
                                            :class="etape.is_done ? 'text-muted-foreground line-through' : ''"
                                        >
                                            {{ etape.etape }}
                                        </label>
                                        <div class="flex shrink-0 gap-1">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="h-6 w-6 p-0"
                                                @click="openEditEtape(etape)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="h-6 w-6 p-0 text-destructive hover:text-destructive"
                                                :disabled="deleteEtapeForm.processing"
                                                @click="deleteEtape(etape)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </template>
                                </li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Ajouter une étape à l'échéancier -->
        <Dialog v-model:open="showAddEtapeDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter une étape</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitAddEtape">
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

        <!-- Modal : Ajouter étudiant -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('classes.show.modal_add_student') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitAdd">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="add-prenom">{{ $t('classes.show.modal_first_name') }}</Label>
                            <Input
                                id="add-prenom"
                                v-model="addForm.prenom"
                                :placeholder="$t('classes.show.modal_first_name')"
                            />
                            <InputError :message="addForm.errors.prenom" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="add-nom">{{ $t('classes.show.modal_name') }}</Label>
                            <Input
                                id="add-nom"
                                v-model="addForm.nom"
                                :placeholder="$t('classes.show.modal_name')"
                            />
                            <InputError :message="addForm.errors.nom" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-da">{{ $t('classes.show.modal_da_number') }}</Label>
                        <Input
                            id="add-da"
                            v-model="addForm.no_da"
                            :placeholder="$t('classes.show.modal_da_number')"
                        />
                        <InputError :message="addForm.errors.no_da" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-statut">{{ $t('classes.show.modal_course_status') }}</Label>
                        <Input
                            id="add-statut"
                            v-model="addForm.statut_cours"
                            :placeholder="$t('classes.show.modal_course_status')"
                        />
                        <InputError :message="addForm.errors.statut_cours" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-email">
                            {{ $t('classes.show.modal_email') }}
                            <span class="text-xs font-normal text-muted-foreground">
                                {{ $t('classes.show.modal_email_note') }}
                            </span>
                        </Label>
                        <Input
                            id="add-email"
                            v-model="addForm.email"
                            type="email"
                            placeholder="prenom.nom@etu.cegepdrummond.ca"
                        />
                        <InputError :message="addForm.errors.email" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showAddDialog = false">
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button type="submit" :disabled="addForm.processing">
                            {{ $t('classes.show.modal_add') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Modifier étudiant -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('classes.show.modal_edit_student') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>{{ $t('classes.show.modal_first_name') }}</Label>
                            <Input
                                v-model="editForm.prenom"
                                :placeholder="$t('classes.show.modal_first_name')"
                            />
                            <InputError :message="editForm.errors.prenom" />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{ $t('classes.show.modal_name') }}</Label>
                            <Input
                                v-model="editForm.nom"
                                :placeholder="$t('classes.show.modal_name')"
                            />
                            <InputError :message="editForm.errors.nom" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('classes.show.modal_email') }}</Label>
                        <Input v-model="editForm.email" type="email" />
                        <InputError :message="editForm.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('classes.show.modal_da_number') }}</Label>
                        <Input v-model="editForm.no_da" />
                        <InputError :message="editForm.errors.no_da" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('classes.show.modal_course_status') }}</Label>
                        <Input
                            v-model="editForm.statut_cours"
                            :placeholder="$t('classes.show.modal_course_status')"
                        />
                        <InputError :message="editForm.errors.statut_cours" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showEditDialog = false">
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{ $t('classes.show.modal_save') }}
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
            :title="`Supprimer la ${$t('classes.groupes.group_number', { n: classeASupprimer?.numero ?? '' })}`"
            description="Cette action supprimera également le projet de recherche associé et ne peut pas être annulée."
            :loading="deleteClasseForm.processing"
            @update:open="handleClasseDialogUpdate"
            @confirm="executeDeleteClasse"
        />

        <!-- Modal : Créer un type de projet -->
        <Dialog v-model:open="showCreateTpDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nouveau type de projet</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="creerTypeProjet">
                    <div class="grid gap-2">
                        <Label for="tp-nom-create">Nom <span class="text-destructive">*</span></Label>
                        <Input
                            id="tp-nom-create"
                            v-model="createTpForm.nom"
                            placeholder="Ex. : Projet de recherche"
                            required
                        />
                        <InputError :message="createTpForm.errors.nom" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tp-desc-create">Description (optionnelle)</Label>
                        <Textarea
                            id="tp-desc-create"
                            v-model="createTpForm.description"
                            placeholder="Notes sur ce type de projet..."
                            :rows="2"
                        />
                        <InputError :message="createTpForm.errors.description" />
                    </div>

                    <!-- Sections -->
                    <div class="grid gap-3 border-t pt-3">
                        <div>
                            <Label>Sections du projet</Label>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Définissez les parties que les étudiants devront rédiger.
                            </p>
                        </div>
                        <div v-if="createTpForm.sections.length > 0" class="flex flex-col gap-2">
                            <div
                                v-for="(section, idx) in createTpForm.sections"
                                :key="idx"
                                class="flex items-start gap-2 rounded-md border bg-muted/30 p-3"
                            >
                                <span class="mt-2 w-5 shrink-0 text-center text-xs text-muted-foreground">
                                    {{ idx + 1 }}
                                </span>
                                <div class="flex-1 space-y-1.5">
                                    <Input
                                        v-model="createTpForm.sections[idx].label"
                                        placeholder="Titre de la section *"
                                        required
                                    />
                                    <InputError :message="createTpForm.errors[`sections.${idx}.label`]" />
                                    <Input
                                        v-model="createTpForm.sections[idx].description"
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

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateTpDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="createTpForm.processing || !createTpForm.nom.trim()">
                            Créer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Import CSV -->
        <Dialog v-model:open="showImportDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('classes.show.modal_import_csv') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitImport">
                    <p class="text-sm text-muted-foreground">
                        {{ $t('classes.show.modal_csv_format') }}
                        <code>;</code>) :
                    </p>
                    <code class="block rounded bg-muted p-3 text-xs">
                        {{ $t('classes.show.modal_csv_fields') }}
                    </code>
                    <div class="grid gap-2">
                        <Label for="csv-file">{{ $t('classes.show.modal_csv_file') }}</Label>
                        <Input
                            id="csv-file"
                            type="file"
                            accept=".csv,.txt"
                            @change="handleFileChange"
                        />
                        <InputError :message="importForm.errors.csv" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showImportDialog = false">
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button type="submit" :disabled="importForm.processing || !importForm.csv">
                            {{ $t('classes.show.modal_import') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
