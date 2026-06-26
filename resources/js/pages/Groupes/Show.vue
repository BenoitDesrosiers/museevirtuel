<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    CalendarPlus,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Download,
    FileText,
    ImagePlus,
    MessageSquare,
    Mic,
    Music,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    Video,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import NoteAvecCorrections from '@/components/NoteAvecCorrections.vue';
import PhotoEditor from '@/components/PhotoEditor.vue';
import VideoCard from '@/components/VideoCard.vue';
import VideoUploadForm from '@/components/VideoUploadForm.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import * as GroupeMediaController from '@/actions/App/Http/Controllers/GroupeMediaController';
import * as GroupeVideoController from '@/actions/App/Http/Controllers/GroupeVideoController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import VisioSession from '@/components/VisioSession.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types/auth';

type User = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
    periode_historique: string | null;
};

type Correction = {
    id: number;
    commentaire_id: string;
    contenu: string;
    user_id: number;
};

type Note = {
    id: number;
    contenu: string;
    created_at: string;
    auteur: User;
    user_id: number;
    corrections: Correction[];
};

type Media = {
    id: number;
    nom_original: string;
    type: 'photo' | 'document' | 'audio';
    taille: number;
    url: string;
    user_id: number;
    auteur: User;
    transcription: string | null;
    transcription_statut:
        | 'en_attente'
        | 'en_cours'
        | 'terminé'
        | 'erreur'
        | null;
};

type Video = {
    id: number;
    titre: string;
    statut: 'brouillon' | 'publié' | 'archivé';
    traitement_statut: string | null;
    duree: number | null;
    taille: number;
    thumbnail_url: string | null;
    url: string;
    user_id: number;
    auteur: User;
};

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

type Groupe = {
    id: number;
    numero: number;
    classe_id: number;
    created_by: number;
    personne_agee_id: number | null;
    membres: User[];
    thematiques: Thematique[];
    notes: Note[];
    medias: Media[];
    temoin: User | null;
};

type EtudiantDispo = {
    id: number;
    prenom: string;
    nom: string;
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
    recording_stream_url: string | null;
    has_recording: boolean;
    recording_is_local: boolean;
    animateur: { id: number; prenom: string; nom: string };
};

type Props = {
    groupe: Groupe;
    classe: Classe;
    cours: Cours;
    estMembre: boolean;
    estEnseignant: boolean;
    estTemoin: boolean;
    estCreateur: boolean;
    thematiquesDispo: Thematique[];
    etudiantsDispo: EtudiantDispo[];
    temoinsDisponibles: User[];
    tousLesTemoins: User[];
    visioConferences: VisioConference[];
    videos: Video[];
    peutTranscrireMedia: boolean;
};

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as Auth).user.id);
const { t } = useI18n();

// ─── Gérer les membres (créateur seulement) ───────────────────────────────────
const showMembresDialog = ref(false);
const membresError = ref('');
const membresForm = useForm({
    ajouter: [] as number[],
    retirer: [] as number[],
});

// Refs découplés de useForm pour tracker les cases à cocher de façon fiable
const membresAjouter = ref<number[]>([]);
const membresRetirer = ref<number[]>([]);

function openMembres() {
    membresAjouter.value = [];
    membresRetirer.value = [];
    membresError.value = '';
    showMembresDialog.value = true;
}

function toggleAjouter(id: number) {
    const idx = membresAjouter.value.indexOf(id);

    if (idx > -1) {
        membresAjouter.value.splice(idx, 1);
    } else {
        membresAjouter.value.push(id);
    }
}

function toggleRetirer(id: number) {
    const idx = membresRetirer.value.indexOf(id);

    if (idx > -1) {
        membresRetirer.value.splice(idx, 1);
    } else {
        membresRetirer.value.push(id);
    }
}

function submitMembres() {
    membresError.value = '';
    membresForm
        .transform((data) => ({
            ...data,
            ajouter: membresAjouter.value,
            retirer: membresRetirer.value,
        }))
        .put(
            `/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${props.groupe.id}/membres`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showMembresDialog.value = false;
                },
                onError: (errors) => {
                    membresError.value =
                        Object.values(errors)[0] ?? t('common.error');
                },
            },
        );
}

// ─── Modifier les thématiques ─────────────────────────────────────────────────
const showThematiquesDialog = ref(false);
const thematiquesError = ref('');
const thematiquesForm = useForm({
    thematiques: [] as number[],
});

// Ref découplé de useForm pour tracker les cases à cocher de façon fiable
const thematiquesSelectionnees = ref<number[]>([]);

function openThematiques() {
    thematiquesSelectionnees.value = props.groupe.thematiques.map((t) => t.id);
    thematiquesError.value = '';
    showThematiquesDialog.value = true;
}

const thematiquesMax = computed(
    () => thematiquesSelectionnees.value.length >= 3,
);

function toggleThematique(id: number) {
    const idx = thematiquesSelectionnees.value.indexOf(id);

    if (idx > -1) {
        thematiquesSelectionnees.value.splice(idx, 1);
    } else if (thematiquesSelectionnees.value.length < 3) {
        thematiquesSelectionnees.value.push(id);
    }
}

function submitThematiques() {
    thematiquesError.value = '';
    thematiquesForm
        .transform((data) => ({
            ...data,
            thematiques: thematiquesSelectionnees.value,
        }))
        .put(
            `/cours/${props.cours.id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/thematiques`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showThematiquesDialog.value = false;
                },
                onError: (errors) => {
                    thematiquesError.value =
                        Object.values(errors)[0] ?? t('common.error');
                },
            },
        );
}

// ─── Carrousel photos ─────────────────────────────────────────────────────────
const photos = computed(() =>
    props.groupe.medias.filter((m) => m.type === 'photo'),
);
const documents = computed(() =>
    props.groupe.medias.filter((m) => m.type === 'document'),
);
const audios = computed(() =>
    props.groupe.medias.filter((m) => m.type === 'audio'),
);

const photoIndex = ref(0);

function prevPhoto() {
    photoIndex.value =
        (photoIndex.value - 1 + photos.value.length) % photos.value.length;
}

function nextPhoto() {
    photoIndex.value = (photoIndex.value + 1) % photos.value.length;
}

// ─── Upload média ─────────────────────────────────────────────────────────────
const mediaFileInput = ref<HTMLInputElement | null>(null);
const mediaForm = useForm({ fichier: null as File | null });

function handleMediaChange(e: Event) {
    const input = e.target as HTMLInputElement;

    if (input.files && input.files[0]) {
        mediaForm.fichier = input.files[0];
        mediaForm.post(
            `/cours/${props.cours.id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/medias`,
            {
                onSuccess: () => {
                    mediaForm.reset();

                    if (mediaFileInput.value) {
                        mediaFileInput.value.value = '';
                    }
                },
            },
        );
    }
}

// ─── Supprimer un média ───────────────────────────────────────────────────────
const deleteMediaForm = useForm({});

function deleteMedia(media: Media) {
    if (
        !confirm(
            t('groupes.show.confirm_delete_media', { nom: media.nom_original }),
        )
    ) {
        return;
    }

    deleteMediaForm.delete(
        `/cours/${props.cours.id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/medias/${media.id}`,
    );
}

function peutSupprimerMedia(media: Media): boolean {
    return media.user_id === userId.value || props.estEnseignant;
}

// ─── Sélecteur de fichier ─────────────────────────────────────────────────────

/**
 * Ouvre directement la boîte de dialogue de sélection de fichier.
 * L'input caché est toujours présent dans le DOM (hors v-if).
 */
function ouvrirSelecteurFichier() {
    mediaFileInput.value?.click();
}

// ─── Upload vidéo ─────────────────────────────────────────────────────────────
const showVideoUploadDialog = ref(false);

// ─── Édition de photo ──────────────────────────────────────────────────────────
const showPhotoEditorDialog = ref(false);

function ouvrirEditeurPhoto() {
    showPhotoEditorDialog.value = true;
}

function editerPhotoUrl(): string {
    return GroupeMediaController.editer({
        cours: props.cours,
        classe: props.classe,
        groupe: props.groupe,
        media: photos.value[photoIndex.value],
    }).url;
}

// ─── Nouvelle note ────────────────────────────────────────────────────────────
const noteForm = useForm({ contenu: '' });

function submitNote() {
    noteForm.post(`/groupes/${props.groupe.id}/notes`, {
        onSuccess: () => noteForm.reset(),
    });
}

// ─── Supprimer une note ───────────────────────────────────────────────────────
const deleteNoteForm = useForm({});

function deleteNote(note: Note) {
    if (!confirm(t('groupes.show.confirm_delete_note'))) {
        return;
    }

    deleteNoteForm.delete(`/groupes/${props.groupe.id}/notes/${note.id}`);
}

// ─── Masquer/afficher les notes ───────────────────────────────────────────────
const notesReduites = ref<number[]>([]);

const toutesNotesReduites = computed(
    () =>
        props.groupe.notes.length > 0 &&
        props.groupe.notes.every((n) => notesReduites.value.includes(n.id)),
);

function toggleNote(id: number): void {
    const idx = notesReduites.value.indexOf(id);

    if (idx > -1) {
        notesReduites.value.splice(idx, 1);
    } else {
        notesReduites.value.push(id);
    }
}

function toggleToutesNotes(): void {
    if (toutesNotesReduites.value) {
        notesReduites.value = [];
    } else {
        notesReduites.value = props.groupe.notes.map((n) => n.id);
    }
}

// ─── Sections repliables ──────────────────────────────────────────────────────
const ouvert = ref({
    temoin: true,
    membres: true,
    thematiques: true,
    medias: true,
    videos: true,
    notes: true,
    visios: true,
});

const ongletMedia = ref<'photos' | 'documents' | 'audios'>('photos');

// ─── Transcription des messages vocaux ────────────────────────────────────────
const transcrivantIds = ref<Set<number>>(new Set());

let audioPollingInterval: ReturnType<typeof setInterval> | null = null;

function audioEnTranscription(): boolean {
    return props.groupe.medias.some(
        (m) =>
            m.type === 'audio' &&
            (m.transcription_statut === 'en_attente' ||
                m.transcription_statut === 'en_cours'),
    );
}

function demarrerPollingAudio(): void {
    if (audioPollingInterval) return;

    audioPollingInterval = setInterval(() => {
        if (!audioEnTranscription()) {
            stopperPollingAudio();
            return;
        }

        router.reload({ only: ['groupe'], preserveScroll: true });
    }, 4000);
}

function stopperPollingAudio(): void {
    if (audioPollingInterval) {
        clearInterval(audioPollingInterval);
        audioPollingInterval = null;
    }
}

function transcrireAudio(mediaId: number): void {
    transcrivantIds.value = new Set([...transcrivantIds.value, mediaId]);

    router.post(
        GroupeMediaController.transcrire({
            cours: props.cours.id,
            classe: props.classe.id,
            groupe: props.groupe.id,
            media: mediaId,
        }).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                transcrivantIds.value.delete(mediaId);
                transcrivantIds.value = new Set(transcrivantIds.value);

                if (audioEnTranscription()) {
                    demarrerPollingAudio();
                }
            },
        },
    );
}

// Démarre le polling si des audios sont déjà en transcription au chargement.
onMounted(() => {
    if (audioEnTranscription()) {
        demarrerPollingAudio();
    }
});

onUnmounted(() => {
    stopperPollingAudio();
});

// Reprend le polling si les props Inertia sont mis à jour avec un audio en cours.
watch(
    () => props.groupe.medias,
    () => {
        if (audioEnTranscription()) {
            demarrerPollingAudio();
        } else {
            stopperPollingAudio();
        }
    },
    { deep: true },
);

// ─── Planifier une visioconférence (membres + enseignant) ─────────────────────
const showPlanifierDialog = ref(false);
const planifierForm = useForm({
    titre: '',
    scheduled_at: '',
});

function openPlanifier() {
    planifierForm.reset();
    showPlanifierDialog.value = true;
}

function submitPlanifier() {
    planifierForm
        .transform((data) => ({
            ...data,
            groupe_id: props.groupe.id,
            scheduled_at: data.scheduled_at || null,
        }))
        .post(`/cours/${props.cours.id}/visio`, {
            preserveScroll: true,
            onSuccess: () => {
                showPlanifierDialog.value = false;
            },
        });
}

// ─── Séparer les rencontres à venir des rencontres effectuées ─────────────────
const rencontresAVenir = computed(() =>
    props.visioConferences.filter((v) => !v.ended_at),
);

const rencontresEffectuees = computed(() =>
    props.visioConferences.filter((v) => !!v.ended_at),
);

// Onglet actif dans la section visioconférences
const ongletVisio = ref<'avenir' | 'effectuees'>('avenir');

// ─── Assigner / désassigner un témoin (enseignant seulement) ──────────────────
const temoinForm = useForm({
    personne_agee_id: props.groupe.personne_agee_id as number | null,
});

function submitTemoin() {
    temoinForm.put(
        `/cours/${props.cours.id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/temoin`,
        {
            preserveScroll: true,
        },
    );
}

function desassignerTemoin() {
    temoinForm.personne_agee_id = null;
    submitTemoin();
}

// Recherche manuelle de témoin par nom
const rechercheTemoin = ref('');

const resultsRecherche = computed(() => {
    const q = rechercheTemoin.value.trim().toLowerCase();

    if (!q) {
        return [];
    }

    return props.tousLesTemoins.filter((t) =>
        `${t.prenom} ${t.nom}`.toLowerCase().includes(q),
    );
});

function selectionnerTemoinRecherche(temoin: User) {
    temoinForm.personne_agee_id = temoin.id;
    rechercheTemoin.value = '';
    submitTemoin();
}

// ─── Formatage ────────────────────────────────────────────────────────────────
function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-CA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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
</script>

<template>
    <AppLayout>
        <Head
            :title="$t('classes.groupes.group_number', { n: groupe.numero })"
        />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        :href="`/cours/${cours.id}/classes/${groupe.classe_id}/groupes`"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('groupes.show.back') }}
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="
                    $t('classes.groupes.group_number', { n: groupe.numero })
                "
                :description="`${groupe.classe.code} — Groupe ${groupe.classe.groupe} · ${groupe.classe.nom_cours}`"
            />

            <!-- Liens rapides -->
            <div class="flex flex-wrap gap-2">
                <BoutonTooltip
                    texte="Accéder aux projets de ce groupe"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="`/cours/${cours.id}/classes/${groupe.classe_id}/groupes/${groupe.id}/projets`"
                    >
                        <BookOpen class="h-4 w-4" />
                    </Link>
                </BoutonTooltip>
                <BoutonTooltip
                    v-if="groupe.temoin || estMembre || estEnseignant"
                    texte="Accéder aux échanges avec le témoin de ce groupe"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="`/cours/${cours.id}/classes/${groupe.classe_id}/groupes/${groupe.id}/echanges`"
                    >
                        <MessageSquare class="mr-2 h-4 w-4" />
                        Échanges avec le témoin
                        <span
                            v-if="groupe.temoin"
                            class="ml-1 text-xs text-muted-foreground"
                        >
                            ({{ groupe.temoin.prenom }} {{ groupe.temoin.nom }})
                        </span>
                    </Link>
                </BoutonTooltip>
            </div>

            <!-- Carte Témoin (enseignant seulement) -->
            <Card v-if="estEnseignant">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.temoin = !ouvert.temoin"
                    >
                        <CardTitle>Témoin assigné</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.temoin }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.temoin">
                    <div
                        v-if="groupe.temoin"
                        class="mb-4 flex items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary"
                            >
                                {{ groupe.temoin.prenom[0]
                                }}{{ groupe.temoin.nom[0] }}
                            </span>
                            <span class="text-sm font-medium"
                                >{{ groupe.temoin.prenom }}
                                {{ groupe.temoin.nom }}</span
                            >
                        </div>
                        <BoutonTooltip
                            texte="Retirer le témoin assigné à ce groupe"
                            type="button"
                            size="sm"
                            variant="destructive"
                            :disabled="temoinForm.processing"
                            @click="desassignerTemoin"
                        >
                            Désassigner
                        </BoutonTooltip>
                    </div>
                    <p v-else class="mb-4 text-sm text-muted-foreground">
                        Aucun témoin assigné à ce groupe.
                    </p>

                    <!-- Témoins suggérés (correspondance thématiques) -->
                    <div class="mb-4">
                        <p
                            class="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Témoins suggérés
                        </p>
                        <form
                            class="flex items-end gap-3"
                            @submit.prevent="submitTemoin"
                        >
                            <div class="flex-1">
                                <Select
                                    :model-value="
                                        temoinForm.personne_agee_id
                                            ? String(
                                                  temoinForm.personne_agee_id,
                                              )
                                            : 'none'
                                    "
                                    @update:model-value="
                                        (v) =>
                                            (temoinForm.personne_agee_id =
                                                v === 'none' ? null : Number(v))
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Sélectionner un témoin…"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none"
                                            >Aucun témoin</SelectItem
                                        >
                                        <SelectItem
                                            v-for="t in temoinsDisponibles"
                                            :key="t.id"
                                            :value="String(t.id)"
                                        >
                                            {{ t.prenom }} {{ t.nom }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="
                                        temoinsDisponibles.length === 0 &&
                                        groupe.thematiques.length === 0
                                    "
                                    class="mt-1.5 text-xs text-muted-foreground"
                                >
                                    Aucun témoin disponible — sélectionnez
                                    d'abord une thématique pour ce groupe.
                                </p>
                                <p
                                    v-else-if="temoinsDisponibles.length === 0"
                                    class="mt-1.5 text-xs text-muted-foreground"
                                >
                                    Aucun témoin actif ne correspond aux
                                    thématiques de ce groupe.
                                </p>
                            </div>
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="temoinForm.processing"
                            >
                                Enregistrer
                            </Button>
                        </form>
                    </div>

                    <!-- Recherche manuelle par nom -->
                    <div>
                        <p
                            class="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Rechercher un autre témoin
                        </p>
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <input
                                v-model="rechercheTemoin"
                                type="text"
                                class="w-full rounded-md border border-input bg-background py-2 pr-3 pl-9 text-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                placeholder="Rechercher par prénom ou nom…"
                            />
                        </div>
                        <ul
                            v-if="resultsRecherche.length > 0"
                            class="mt-1 max-h-40 overflow-y-auto rounded-md border border-input"
                        >
                            <li
                                v-for="t in resultsRecherche"
                                :key="t.id"
                                class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm hover:bg-accent"
                                @click="selectionnerTemoinRecherche(t)"
                            >
                                <span>{{ t.prenom }} {{ t.nom }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >Assigner</span
                                >
                            </li>
                        </ul>
                        <p
                            v-else-if="rechercheTemoin.trim().length > 0"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Aucun témoin trouvé pour « {{ rechercheTemoin }} ».
                        </p>
                    </div>

                    <p
                        v-if="temoinForm.errors.personne_agee_id"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ temoinForm.errors.personne_agee_id }}
                    </p>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Membres -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <button
                            type="button"
                            class="flex cursor-pointer items-center gap-2 text-left select-none"
                            @click="ouvert.membres = !ouvert.membres"
                        >
                            <CardTitle>{{
                                $t('groupes.show.members')
                            }}</CardTitle>
                            <ChevronDown
                                class="h-4 w-4 text-muted-foreground transition-transform"
                                :class="{ '-rotate-180': ouvert.membres }"
                            />
                        </button>
                        <Button
                            v-if="estCreateur"
                            size="sm"
                            variant="outline"
                            @click="openMembres"
                        >
                            <Pencil class="mr-2 h-4 w-4" />
                            {{ $t('groupes.show.manage') }}
                        </Button>
                    </CardHeader>
                    <CardContent v-show="ouvert.membres">
                        <ul class="space-y-2">
                            <li
                                v-for="membre in groupe.membres"
                                :key="membre.id"
                                class="flex items-center gap-2 text-sm"
                            >
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary"
                                >
                                    {{ membre.prenom[0] }}{{ membre.nom[0] }}
                                </span>
                                <span
                                    >{{ membre.prenom }} {{ membre.nom }}</span
                                >
                                <span
                                    v-if="membre.id === groupe.created_by"
                                    class="text-xs text-muted-foreground"
                                >
                                    (créateur)
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Thématiques -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <button
                            type="button"
                            class="flex cursor-pointer items-center gap-2 text-left select-none"
                            @click="ouvert.thematiques = !ouvert.thematiques"
                        >
                            <CardTitle>{{
                                $t('groupes.show.thematic')
                            }}</CardTitle>
                            <ChevronDown
                                class="h-4 w-4 text-muted-foreground transition-transform"
                                :class="{ '-rotate-180': ouvert.thematiques }"
                            />
                        </button>
                        <Button
                            v-if="estMembre"
                            size="sm"
                            variant="outline"
                            @click="openThematiques"
                        >
                            <Pencil class="mr-2 h-4 w-4" />
                            {{ $t('groupes.show.edit') }}
                        </Button>
                    </CardHeader>
                    <CardContent v-show="ouvert.thematiques">
                        <div
                            v-if="groupe.thematiques.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ $t('groupes.show.no_thematic') }}
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="thematique in groupe.thematiques"
                                :key="thematique.id"
                            >
                                <p class="text-sm font-medium">
                                    {{ thematique.nom }}
                                </p>
                                <p
                                    v-if="thematique.periode_historique"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ thematique.periode_historique }}
                                </p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Médias du groupe -->
            <Card v-if="groupe.medias.length > 0 || estMembre">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.medias = !ouvert.medias"
                    >
                        <CardTitle>{{ $t('groupes.show.medias') }}</CardTitle>
                        <span
                            v-if="groupe.medias.length > 0"
                            class="text-sm font-normal text-muted-foreground"
                            >({{ groupe.medias.length }})</span
                        >
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.medias }"
                        />
                    </button>
                    <Button
                        v-if="estMembre"
                        variant="outline"
                        size="sm"
                        @click.stop="ouvrirSelecteurFichier()"
                    >
                        <ImagePlus class="mr-2 h-4 w-4" />
                        {{ $t('groupes.show.add_files') }}
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.medias" class="flex flex-col gap-4">
                    <!-- Barre d'onglets -->
                    <div class="flex rounded-lg border p-1 text-sm">
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors"
                            :class="
                                ongletMedia === 'photos'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="ongletMedia = 'photos'"
                        >
                            <ImagePlus class="h-4 w-4" />
                            {{ $t('groupes.show.photos') }}
                            <span v-if="photos.length > 0" class="opacity-70"
                                >({{ photos.length }})</span
                            >
                        </button>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors"
                            :class="
                                ongletMedia === 'documents'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="ongletMedia = 'documents'"
                        >
                            <FileText class="h-4 w-4" />
                            {{ $t('groupes.show.documents') }}
                            <span v-if="documents.length > 0" class="opacity-70"
                                >({{ documents.length }})</span
                            >
                        </button>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors"
                            :class="
                                ongletMedia === 'audios'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="ongletMedia = 'audios'"
                        >
                            <Music class="h-4 w-4" />
                            {{ $t('groupes.show.audio') }}
                            <span v-if="audios.length > 0" class="opacity-70"
                                >({{ audios.length }})</span
                            >
                        </button>
                    </div>

                    <!-- Onglet : Photos -->
                    <div v-if="ongletMedia === 'photos'">
                        <div v-if="photos.length > 0">
                            <div class="relative overflow-hidden rounded-lg">
                                <img
                                    :src="photos[photoIndex].url"
                                    :alt="photos[photoIndex].nom_original"
                                    class="max-h-96 w-full bg-muted object-contain"
                                />

                                <!-- Navigation -->
                                <button
                                    v-if="photos.length > 1"
                                    class="absolute top-1/2 left-2 -translate-y-1/2 rounded-full bg-black/40 p-1.5 text-white transition-colors hover:bg-black/60"
                                    @click="prevPhoto"
                                >
                                    <ChevronLeft class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="photos.length > 1"
                                    class="absolute top-1/2 right-2 -translate-y-1/2 rounded-full bg-black/40 p-1.5 text-white transition-colors hover:bg-black/60"
                                    @click="nextPhoto"
                                >
                                    <ChevronRight class="h-5 w-5" />
                                </button>

                                <!-- Bouton éditer (membres + enseignant, GIF exclu) -->
                                <button
                                    v-if="
                                        (estMembre || estEnseignant) &&
                                        !photos[photoIndex].nom_original
                                            .toLowerCase()
                                            .endsWith('.gif')
                                    "
                                    class="absolute top-2 right-2 rounded-full bg-black/40 p-1.5 text-white transition-colors hover:bg-black/60"
                                    :title="$t('media.editer_photo')"
                                    @click="ouvrirEditeurPhoto"
                                >
                                    <SlidersHorizontal class="h-4 w-4" />
                                </button>

                                <!-- Bouton supprimer -->
                                <button
                                    v-if="
                                        peutSupprimerMedia(photos[photoIndex])
                                    "
                                    class="absolute top-2 right-14 rounded-full bg-destructive/80 p-1.5 text-white transition-colors hover:bg-destructive"
                                    @click="deleteMedia(photos[photoIndex])"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>

                            <!-- Légende -->
                            <div
                                class="mt-2 flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span>{{
                                    photos[photoIndex].nom_original
                                }}</span>
                                <span
                                    >{{ photoIndex + 1 }} /
                                    {{ photos.length }} · par
                                    {{ photos[photoIndex].auteur.prenom }}
                                    {{ photos[photoIndex].auteur.nom }}</span
                                >
                            </div>

                            <!-- Miniatures -->
                            <div
                                v-if="photos.length > 1"
                                class="mt-3 flex gap-2 overflow-x-auto pb-1"
                            >
                                <button
                                    v-for="(photo, idx) in photos"
                                    :key="photo.id"
                                    class="h-14 w-14 shrink-0 overflow-hidden rounded border-2 transition-colors"
                                    :class="
                                        idx === photoIndex
                                            ? 'border-primary'
                                            : 'border-transparent'
                                    "
                                    @click="photoIndex = idx"
                                >
                                    <img
                                        :src="photo.url"
                                        :alt="photo.nom_original"
                                        class="h-full w-full object-cover"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- État vide -->
                        <div
                            v-else
                            class="flex flex-col items-center gap-3 py-10 text-center"
                        >
                            <ImagePlus
                                class="h-10 w-10 text-muted-foreground"
                            />
                            <p class="text-sm text-muted-foreground">
                                {{ $t('groupes.show.no_photos') }}
                            </p>
                            <Button
                                v-if="estMembre"
                                size="sm"
                                variant="outline"
                                @click="ouvrirSelecteurFichier()"
                            >
                                {{ $t('groupes.show.add_photo') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Onglet : Documents -->
                    <div v-if="ongletMedia === 'documents'">
                        <div
                            v-if="documents.length > 0"
                            class="flex flex-col divide-y"
                        >
                            <div
                                v-for="doc in documents"
                                :key="doc.id"
                                class="flex items-center justify-between gap-3 py-3"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <FileText
                                        class="h-5 w-5 shrink-0 text-muted-foreground"
                                    />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">
                                            {{ doc.nom_original }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ doc.type.toUpperCase() }} ·
                                            {{ formatSize(doc.taille) }} ·
                                            <span
                                                >{{ doc.auteur.prenom }}
                                                {{ doc.auteur.nom }}</span
                                            >
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
                                        <a
                                            :href="doc.url"
                                            target="_blank"
                                            download
                                        >
                                            <Download class="h-4 w-4" />
                                        </a>
                                    </BoutonTooltip>
                                    <Button
                                        v-if="peutSupprimerMedia(doc)"
                                        size="sm"
                                        variant="destructive"
                                        @click="deleteMedia(doc)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- État vide -->
                        <div
                            v-else
                            class="flex flex-col items-center gap-3 py-10 text-center"
                        >
                            <FileText class="h-10 w-10 text-muted-foreground" />
                            <p class="text-sm text-muted-foreground">
                                {{ $t('groupes.show.no_documents') }}
                            </p>
                            <Button
                                v-if="estMembre"
                                size="sm"
                                variant="outline"
                                @click="ouvrirSelecteurFichier()"
                            >
                                {{ $t('groupes.show.add_document') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Onglet : Audio -->
                    <div v-if="ongletMedia === 'audios'">
                        <div
                            v-if="audios.length > 0"
                            class="flex flex-col divide-y"
                        >
                            <div
                                v-for="audio in audios"
                                :key="audio.id"
                                class="flex flex-col gap-2 py-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <Music
                                            class="h-5 w-5 shrink-0 text-muted-foreground"
                                        />
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-medium"
                                            >
                                                {{ audio.nom_original }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ formatSize(audio.taille) }} ·
                                                <span
                                                    >{{ audio.auteur.prenom }}
                                                    {{ audio.auteur.nom }}</span
                                                >
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="peutSupprimerMedia(audio)"
                                        size="sm"
                                        variant="destructive"
                                        @click="deleteMedia(audio)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                                <audio controls class="h-10 w-full">
                                    <source :src="audio.url" />
                                    {{
                                        $t(
                                            'groupes.show.browser_no_audio_support',
                                        )
                                    }}
                                </audio>

                                <!-- Transcription -->
                                <div class="mt-1">
                                    <!-- En cours / en attente -->
                                    <div
                                        v-if="
                                            audio.transcription_statut ===
                                                'en_attente' ||
                                            audio.transcription_statut ===
                                                'en_cours'
                                        "
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        <svg
                                            class="h-3 w-3 animate-spin"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            xmlns="http://www.w3.org/2000/svg"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            />
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                            />
                                        </svg>
                                        {{
                                            $t(
                                                'groupes.show.transcription_en_cours',
                                            )
                                        }}
                                    </div>

                                    <!-- Erreur + réessayer -->
                                    <div
                                        v-else-if="
                                            audio.transcription_statut ===
                                            'erreur'
                                        "
                                        class="flex items-center gap-2"
                                    >
                                        <p class="text-xs text-destructive">
                                            {{
                                                $t(
                                                    'groupes.show.transcription_erreur',
                                                )
                                            }}
                                        </p>
                                        <Button
                                            v-if="peutTranscrireMedia"
                                            size="sm"
                                            variant="ghost"
                                            class="h-6 px-2 text-xs"
                                            :disabled="
                                                transcrivantIds.has(audio.id)
                                            "
                                            @click="transcrireAudio(audio.id)"
                                        >
                                            <Mic class="mr-1 h-3 w-3" />
                                            {{
                                                $t(
                                                    'groupes.show.reessayer_transcription',
                                                )
                                            }}
                                        </Button>
                                    </div>

                                    <!-- Transcription disponible -->
                                    <p
                                        v-else-if="
                                            audio.transcription_statut ===
                                                'terminé' && audio.transcription
                                        "
                                        class="text-xs text-muted-foreground italic"
                                    >
                                        {{ audio.transcription }}
                                    </p>

                                    <!-- Pas encore transcrit -->
                                    <Button
                                        v-else-if="
                                            peutTranscrireMedia &&
                                            !audio.transcription_statut
                                        "
                                        size="sm"
                                        variant="ghost"
                                        class="h-6 px-2 text-xs text-muted-foreground"
                                        :disabled="
                                            transcrivantIds.has(audio.id)
                                        "
                                        @click="transcrireAudio(audio.id)"
                                    >
                                        <Mic class="mr-1 h-3 w-3" />
                                        {{
                                            $t('groupes.show.transcrire_audio')
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- État vide -->
                        <div
                            v-else
                            class="flex flex-col items-center gap-3 py-10 text-center"
                        >
                            <Music class="h-10 w-10 text-muted-foreground" />
                            <p class="text-sm text-muted-foreground">
                                {{ $t('groupes.show.no_audio') }}
                            </p>
                            <Button
                                v-if="estMembre"
                                size="sm"
                                variant="outline"
                                @click="ouvrirSelecteurFichier()"
                            >
                                {{ $t('groupes.show.add_audio') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Input caché : hors v-if pour être toujours disponible dans le DOM -->
                    <input
                        ref="mediaFileInput"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.mp3,.wav,.ogg,.m4a,.aac"
                        class="hidden"
                        @change="handleMediaChange"
                    />

                    <!-- Erreur d'upload (visible depuis n'importe quel onglet) -->
                    <p
                        v-if="mediaForm.errors.fichier"
                        class="text-sm text-destructive"
                    >
                        {{ mediaForm.errors.fichier }}
                    </p>
                </CardContent>
            </Card>

            <!-- Dialog éditeur de photo ─────────────────────────────────────── -->
            <Dialog v-model:open="showPhotoEditorDialog">
                <DialogContent class="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{{
                            $t('media.editer_photo')
                        }}</DialogTitle>
                    </DialogHeader>

                    <PhotoEditor
                        :photo-url="photos[photoIndex]?.url ?? ''"
                        :edit-url="photos.length > 0 ? editerPhotoUrl() : ''"
                        :on-success="() => (showPhotoEditorDialog = false)"
                    />
                </DialogContent>
            </Dialog>

            <!-- Notes -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.notes = !ouvert.notes"
                    >
                        <CardTitle>{{ $t('groupes.show.notes') }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground"
                            >({{ groupe.notes.length }})</span
                        >
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.notes }"
                        />
                    </button>
                    <Button
                        v-if="groupe.notes.length > 0"
                        variant="ghost"
                        size="sm"
                        @click.stop="toggleToutesNotes"
                    >
                        {{
                            toutesNotesReduites
                                ? $t('groupes.show.afficher_tout')
                                : $t('groupes.show.masquer_tout')
                        }}
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.notes" class="flex flex-col gap-4">
                    <!-- Liste des notes -->
                    <div
                        v-if="groupe.notes.length === 0"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('groupes.show.no_notes') }}
                    </div>

                    <div
                        v-for="note in groupe.notes"
                        :key="note.id"
                        class="border-b pb-4 last:border-0 last:pb-0"
                    >
                        <div
                            class="mb-1 flex items-start justify-between gap-2"
                        >
                            <button
                                type="button"
                                class="flex flex-1 cursor-pointer items-center gap-2 text-left"
                                @click="toggleNote(note.id)"
                            >
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                                    :class="{
                                        'rotate-180': !notesReduites.includes(
                                            note.id,
                                        ),
                                    }"
                                />
                                <span class="text-sm font-medium">
                                    {{ note.auteur.prenom }}
                                    {{ note.auteur.nom }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatDate(note.created_at) }}
                                </span>
                            </button>
                            <Button
                                v-if="note.user_id === userId"
                                size="sm"
                                variant="ghost"
                                class="h-7 w-7 p-0 text-destructive hover:text-destructive"
                                @click="deleteNote(note)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <NoteAvecCorrections
                            v-show="!notesReduites.includes(note.id)"
                            :note="note"
                            :est-enseignant="estEnseignant"
                            :groupe-id="groupe.id"
                        />
                    </div>

                    <!-- Formulaire nouvelle note (membres seulement) -->
                    <template v-if="estMembre">
                        <div class="border-t pt-4">
                            <form
                                class="flex flex-col gap-2"
                                @submit.prevent="submitNote"
                            >
                                <textarea
                                    v-model="noteForm.contenu"
                                    rows="3"
                                    maxlength="2000"
                                    :placeholder="$t('groupes.show.write_note')"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <p
                                    v-if="noteForm.errors.contenu"
                                    class="text-sm text-destructive"
                                >
                                    {{ noteForm.errors.contenu }}
                                </p>
                                <div class="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="
                                            noteForm.processing ||
                                            !noteForm.contenu.trim()
                                        "
                                    >
                                        {{ $t('groupes.show.publish') }}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- Vidéos du groupe -->
            <Card v-if="videos.length > 0 || estMembre">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.videos = !ouvert.videos"
                    >
                        <Video class="h-5 w-5" />
                        <CardTitle>Vidéos</CardTitle>
                        <span
                            v-if="videos.length > 0"
                            class="text-sm font-normal text-muted-foreground"
                            >({{ videos.length }})</span
                        >
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.videos }"
                        />
                    </button>
                    <Button
                        v-if="estMembre"
                        type="button"
                        size="sm"
                        variant="outline"
                        @click.stop="showVideoUploadDialog = true"
                    >
                        <Plus class="mr-1.5 h-3.5 w-3.5" />
                        Ajouter une vidéo
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.videos" class="flex flex-col gap-4">
                    <!-- Grille de vidéos -->
                    <div
                        v-if="videos.length > 0"
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <VideoCard
                            v-for="video in videos"
                            :key="video.id"
                            :video="video"
                            :show-url="
                                GroupeVideoController.show({
                                    cours,
                                    classe,
                                    groupe,
                                    video,
                                }).url
                            "
                            :publier-url="
                                GroupeVideoController.publier({
                                    cours,
                                    classe,
                                    groupe,
                                    video,
                                }).url
                            "
                            :destroy-url="
                                GroupeVideoController.destroy({
                                    cours,
                                    classe,
                                    groupe,
                                    video,
                                }).url
                            "
                            :peut-publier="
                                estEnseignant || video.user_id === userId
                            "
                            :peut-supprimer="
                                estEnseignant || video.user_id === userId
                            "
                        />
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Aucune vidéo publiée dans ce groupe.
                    </p>
                </CardContent>
            </Card>

            <!-- Visioconférences -->
            <Card
                v-if="
                    visioConferences.length > 0 ||
                    estEnseignant ||
                    estMembre ||
                    estTemoin
                "
            >
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.visios = !ouvert.visios"
                    >
                        <Video class="h-5 w-5" />
                        <CardTitle>Visioconférences</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.visios }"
                        />
                    </button>
                    <Button
                        v-if="estMembre || estEnseignant"
                        size="sm"
                        variant="outline"
                        @click="openPlanifier"
                    >
                        <CalendarPlus class="mr-2 h-4 w-4" />
                        Planifier
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.visios">
                    <!-- Toggle à venir / effectuées -->
                    <div class="mb-4 flex rounded-lg border p-1 text-sm">
                        <button
                            type="button"
                            class="flex-1 rounded-md px-3 py-1.5 font-medium transition-colors"
                            :class="
                                ongletVisio === 'avenir'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="ongletVisio = 'avenir'"
                        >
                            À venir
                            <span
                                v-if="rencontresAVenir.length > 0"
                                class="ml-1 opacity-70"
                                >({{ rencontresAVenir.length }})</span
                            >
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-md px-3 py-1.5 font-medium transition-colors"
                            :class="
                                ongletVisio === 'effectuees'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="ongletVisio = 'effectuees'"
                        >
                            Effectuées
                            <span
                                v-if="rencontresEffectuees.length > 0"
                                class="ml-1 opacity-70"
                                >({{ rencontresEffectuees.length }})</span
                            >
                        </button>
                    </div>

                    <!-- Rencontres à venir -->
                    <div v-if="ongletVisio === 'avenir'">
                        <div
                            v-if="rencontresAVenir.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Aucune rencontre à venir pour ce groupe.
                        </div>
                        <div v-else class="flex flex-col gap-3">
                            <VisioSession
                                v-for="visio in rencontresAVenir"
                                :key="visio.id"
                                :visio="visio"
                                :can-manage="estEnseignant"
                                :can-start="estMembre || estEnseignant"
                                :est-temoin="estTemoin"
                            />
                        </div>
                    </div>

                    <!-- Rencontres effectuées -->
                    <div v-if="ongletVisio === 'effectuees'">
                        <div
                            v-if="rencontresEffectuees.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Aucune rencontre effectuée pour ce groupe.
                        </div>
                        <div v-else class="flex flex-col gap-3">
                            <VisioSession
                                v-for="visio in rencontresEffectuees"
                                :key="visio.id"
                                :visio="visio"
                                :can-manage="estEnseignant"
                                :can-start="estMembre || estEnseignant"
                                :est-temoin="estTemoin"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
        <!-- Dialog : gérer les membres -->
        <Dialog v-model:open="showMembresDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{
                        $t('groupes.show.modal_manage_members')
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submitMembres">
                    <!-- Inviter des étudiants -->
                    <div>
                        <p class="mb-2 text-sm font-medium">
                            {{ $t('groupes.show.modal_invite_students') }}
                        </p>
                        <div
                            v-if="etudiantsDispo.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ $t('groupes.show.modal_all_members') }}
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="etudiant in etudiantsDispo"
                                :key="etudiant.id"
                                class="flex items-center gap-3"
                            >
                                <Checkbox
                                    :id="`ajouter-${etudiant.id}`"
                                    :checked="
                                        membresAjouter.includes(etudiant.id)
                                    "
                                    @click.prevent="
                                        () => toggleAjouter(etudiant.id)
                                    "
                                />
                                <Label
                                    :for="`ajouter-${etudiant.id}`"
                                    class="cursor-pointer font-normal"
                                >
                                    {{ etudiant.prenom }} {{ etudiant.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <!-- Retirer des membres -->
                    <div>
                        <p class="mb-2 text-sm font-medium">
                            {{ $t('groupes.show.modal_remove_members') }}
                        </p>
                        <div class="space-y-2">
                            <div
                                v-for="membre in groupe.membres.filter(
                                    (m) => m.id !== userId,
                                )"
                                :key="membre.id"
                                class="flex items-center gap-3"
                            >
                                <Checkbox
                                    :id="`retirer-${membre.id}`"
                                    :checked="
                                        membresRetirer.includes(membre.id)
                                    "
                                    @click.prevent="
                                        () => toggleRetirer(membre.id)
                                    "
                                />
                                <Label
                                    :for="`retirer-${membre.id}`"
                                    class="cursor-pointer font-normal"
                                >
                                    {{ membre.prenom }} {{ membre.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <p v-if="membresError" class="text-sm text-destructive">
                        {{ membresError }}
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showMembresDialog = false"
                        >
                            {{ $t('common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="membresForm.processing"
                        >
                            {{ $t('common.save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog : planifier une visioconférence -->
        <Dialog v-model:open="showPlanifierDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Planifier une visioconférence</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitPlanifier">
                    <div class="grid gap-2">
                        <Label for="planifier-titre">Titre</Label>
                        <input
                            id="planifier-titre"
                            v-model="planifierForm.titre"
                            type="text"
                            required
                            maxlength="255"
                            placeholder="Ex : Rencontre du groupe 2"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <p
                            v-if="planifierForm.errors.titre"
                            class="text-sm text-destructive"
                        >
                            {{ planifierForm.errors.titre }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="planifier-date"
                            >Date et heure (optionnel)</Label
                        >
                        <input
                            id="planifier-date"
                            v-model="planifierForm.scheduled_at"
                            type="datetime-local"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showPlanifierDialog = false"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            :disabled="planifierForm.processing"
                        >
                            Créer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog : modifier les thématiques -->
        <!-- Dialog : ajouter une vidéo -->
        <Dialog v-model:open="showVideoUploadDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Ajouter une vidéo</DialogTitle>
                </DialogHeader>
                <VideoUploadForm
                    :upload-url="
                        GroupeVideoController.store({ cours, classe, groupe })
                            .url
                    "
                    :on-success="() => (showVideoUploadDialog = false)"
                />
            </DialogContent>
        </Dialog>

        <FormDialog
            v-model:open="showThematiquesDialog"
            :title="$t('groupes.show.modal_edit_thematic')"
            :is-loading="thematiquesForm.processing"
            scrollable
            @submit="submitThematiques"
        >
            <p class="text-sm text-muted-foreground">
                {{ $t('groupes.show.modal_thematic_help') }}
                <span class="font-medium"
                    >({{ thematiquesSelectionnees.length }}/3)</span
                >
            </p>

            <div
                v-if="thematiquesDispo.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('groupes.show.modal_no_thematic_available') }}
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="thematique in thematiquesDispo"
                    :key="thematique.id"
                    class="flex items-start gap-3"
                >
                    <Checkbox
                        :id="`t-${thematique.id}`"
                        :checked="
                            thematiquesSelectionnees.includes(thematique.id)
                        "
                        :disabled="
                            thematiquesMax &&
                            !thematiquesSelectionnees.includes(thematique.id)
                        "
                        @click.prevent="() => toggleThematique(thematique.id)"
                    />
                    <Label
                        :for="`t-${thematique.id}`"
                        class="cursor-pointer leading-snug font-normal"
                        :class="{
                            'text-muted-foreground':
                                thematiquesMax &&
                                !thematiquesSelectionnees.includes(
                                    thematique.id,
                                ),
                        }"
                    >
                        {{ thematique.nom }}
                        <span
                            v-if="thematique.periode_historique"
                            class="ml-1 text-xs text-muted-foreground"
                        >
                            — {{ thematique.periode_historique }}
                        </span>
                    </Label>
                </div>
            </div>

            <p v-if="thematiquesError" class="text-sm text-destructive">
                {{ thematiquesError }}
            </p>
        </FormDialog>
    </AppLayout>
</template>
