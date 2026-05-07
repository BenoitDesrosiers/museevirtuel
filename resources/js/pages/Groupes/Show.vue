<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, ChevronDown, ChevronLeft, ChevronRight, Download, FileText, ImagePlus, MessageSquare, Music, Pencil, Search, Trash2, Video } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import NoteAvecCorrections from '@/components/NoteAvecCorrections.vue';
import VisioSession from '@/components/VisioSession.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
    animateur: { id: number; prenom: string; nom: string };
};

type Props = {
    groupe: Groupe;
    classe: Classe;
    cours: Cours;
    estMembre: boolean;
    estEnseignant: boolean;
    estCreateur: boolean;
    thematiquesDispo: Thematique[];
    etudiantsDispo: EtudiantDispo[];
    temoinsDisponibles: User[];
    tousLesTemoins: User[];
    visioConferences: VisioConference[];
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
                    membresError.value = Object.values(errors)[0] ?? t('common.error');
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

const thematiquesMax = computed(() => thematiquesSelectionnees.value.length >= 3);

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
                    thematiquesError.value = Object.values(errors)[0] ?? t('common.error');
                },
            },
        );
}

// ─── Carrousel photos ─────────────────────────────────────────────────────────
const photos = computed(() => props.groupe.medias.filter((m) => m.type === 'photo'));
const documents = computed(() => props.groupe.medias.filter((m) => m.type === 'document'));
const audios = computed(() => props.groupe.medias.filter((m) => m.type === 'audio'));

const photoIndex = ref(0);

function prevPhoto() {
    photoIndex.value = (photoIndex.value - 1 + photos.value.length) % photos.value.length;
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
        mediaForm.post(`/cours/${props.cours.id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/medias`, {
            onSuccess: () => {
                mediaForm.reset();

                if (mediaFileInput.value) {
mediaFileInput.value.value = '';
}
            },
        });
    }
}

// ─── Supprimer un média ───────────────────────────────────────────────────────
const deleteMediaForm = useForm({});

function deleteMedia(media: Media) {
    if (!confirm(t('groupes.show.confirm_delete_media', { nom: media.nom_original }))) {
return;
}

    deleteMediaForm.delete(
        `/cours/${props.cours.id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/medias/${media.id}`,
    );
}

function peutSupprimerMedia(media: Media): boolean {
    return media.user_id === userId.value || props.estEnseignant;
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
    () => props.groupe.notes.length > 0 && props.groupe.notes.every((n) => notesReduites.value.includes(n.id)),
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

// ─── Assigner / désassigner un témoin (enseignant seulement) ──────────────────
const temoinForm = useForm({
    personne_agee_id: props.groupe.personne_agee_id as number | null,
});

function submitTemoin() {
    temoinForm.put(`/cours/${props.cours.id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/temoin`, {
        preserveScroll: true,
    });
}

function desassignerTemoin() {
    temoinForm.personne_agee_id = null;
    submitTemoin();
}

// Recherche manuelle de témoin par nom
const rechercheTemoin = ref('');

const resultsRecherche = computed(() => {
    const q = rechercheTemoin.value.trim().toLowerCase();
    if (!q) return [];
    return props.tousLesTemoins.filter(
        (t) => `${t.prenom} ${t.nom}`.toLowerCase().includes(q),
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
        <Head :title="$t('classes.groupes.group_number', { n: groupe.numero })" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/cours/${cours.id}/classes/${groupe.classe_id}/groupes`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('groupes.show.back') }}
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="$t('classes.groupes.group_number', { n: groupe.numero })"
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
                    <Link :href="`/cours/${cours.id}/classes/${groupe.classe_id}/groupes/${groupe.id}/projets`">
                        <BookOpen class="mr-2 h-4 w-4" />
                        Projets
                    </Link>
                </BoutonTooltip>
                <BoutonTooltip
                    v-if="groupe.temoin || estMembre || estEnseignant"
                    texte="Accéder aux échanges avec le témoin de ce groupe"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="`/cours/${cours.id}/classes/${groupe.classe_id}/groupes/${groupe.id}/echanges`">
                        <MessageSquare class="mr-2 h-4 w-4" />
                        Échanges avec le témoin
                        <span v-if="groupe.temoin" class="ml-1 text-xs text-muted-foreground">
                            ({{ groupe.temoin.prenom }} {{ groupe.temoin.nom }})
                        </span>
                    </Link>
                </BoutonTooltip>
            </div>

            <!-- Carte Témoin (enseignant seulement) -->
            <Card v-if="estEnseignant">
                <CardHeader>
                    <CardTitle>Témoin assigné</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="groupe.temoin" class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-full text-xs font-medium">
                                {{ groupe.temoin.prenom[0] }}{{ groupe.temoin.nom[0] }}
                            </span>
                            <span class="text-sm font-medium">{{ groupe.temoin.prenom }} {{ groupe.temoin.nom }}</span>
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
                    <p v-else class="text-muted-foreground mb-4 text-sm">Aucun témoin assigné à ce groupe.</p>

                    <!-- Témoins suggérés (correspondance thématiques) -->
                    <div class="mb-4">
                        <p class="text-muted-foreground mb-1.5 text-xs font-medium uppercase tracking-wide">Témoins suggérés</p>
                        <form class="flex items-end gap-3" @submit.prevent="submitTemoin">
                            <div class="flex-1">
                                <Select
                                    :model-value="temoinForm.personne_agee_id ? String(temoinForm.personne_agee_id) : 'none'"
                                    @update:model-value="(v) => temoinForm.personne_agee_id = v === 'none' ? null : Number(v)"
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sélectionner un témoin…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Aucun témoin</SelectItem>
                                        <SelectItem
                                            v-for="t in temoinsDisponibles"
                                            :key="t.id"
                                            :value="String(t.id)"
                                        >
                                            {{ t.prenom }} {{ t.nom }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="temoinsDisponibles.length === 0 && groupe.thematiques.length === 0" class="text-muted-foreground mt-1.5 text-xs">
                                    Aucun témoin disponible — sélectionnez d'abord une thématique pour ce groupe.
                                </p>
                                <p v-else-if="temoinsDisponibles.length === 0" class="text-muted-foreground mt-1.5 text-xs">
                                    Aucun témoin actif ne correspond aux thématiques de ce groupe.
                                </p>
                            </div>
                            <Button type="submit" size="sm" :disabled="temoinForm.processing">
                                Enregistrer
                            </Button>
                        </form>
                    </div>

                    <!-- Recherche manuelle par nom -->
                    <div>
                        <p class="text-muted-foreground mb-1.5 text-xs font-medium uppercase tracking-wide">Rechercher un autre témoin</p>
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <input
                                v-model="rechercheTemoin"
                                type="text"
                                class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border py-2 pr-3 pl-9 text-sm focus-visible:ring-1 focus-visible:outline-none"
                                placeholder="Rechercher par prénom ou nom…"
                            />
                        </div>
                        <ul v-if="resultsRecherche.length > 0" class="border-input mt-1 max-h-40 overflow-y-auto rounded-md border">
                            <li
                                v-for="t in resultsRecherche"
                                :key="t.id"
                                class="hover:bg-accent flex cursor-pointer items-center justify-between px-3 py-2 text-sm"
                                @click="selectionnerTemoinRecherche(t)"
                            >
                                <span>{{ t.prenom }} {{ t.nom }}</span>
                                <span class="text-muted-foreground text-xs">Assigner</span>
                            </li>
                        </ul>
                        <p v-else-if="rechercheTemoin.trim().length > 0" class="text-muted-foreground mt-1 text-xs">
                            Aucun témoin trouvé pour « {{ rechercheTemoin }} ».
                        </p>
                    </div>

                    <p v-if="temoinForm.errors.personne_agee_id" class="text-destructive mt-2 text-sm">
                        {{ temoinForm.errors.personne_agee_id }}
                    </p>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Membres -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>{{ $t('groupes.show.members') }}</CardTitle>
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
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="membre in groupe.membres"
                                :key="membre.id"
                                class="flex items-center gap-2 text-sm"
                            >
                                <span
                                    class="bg-primary/10 text-primary flex h-7 w-7 items-center justify-center rounded-full text-xs font-medium"
                                >
                                    {{ membre.prenom[0] }}{{ membre.nom[0] }}
                                </span>
                                <span>{{ membre.prenom }} {{ membre.nom }}</span>
                                <span
                                    v-if="membre.id === groupe.created_by"
                                    class="text-muted-foreground text-xs"
                                >
                                    (créateur)
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Thématiques -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>{{ $t('groupes.show.thematic') }}</CardTitle>
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
                    <CardContent>
                        <div v-if="groupe.thematiques.length === 0" class="text-muted-foreground text-sm">
                            {{ $t('groupes.show.no_thematic') }}
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="thematique in groupe.thematiques"
                                :key="thematique.id"
                            >
                                <p class="font-medium text-sm">{{ thematique.nom }}</p>
                                <p
                                    v-if="thematique.periode_historique"
                                    class="text-muted-foreground text-xs"
                                >
                                    {{ thematique.periode_historique }}
                                </p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Carrousel photos -->
            <Card v-if="photos.length > 0">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ $t('groupes.show.photos') }}</CardTitle>
                    <span class="text-muted-foreground text-sm">
                        {{ photoIndex + 1 }} / {{ photos.length }}
                    </span>
                </CardHeader>
                <CardContent>
                    <div class="relative overflow-hidden rounded-lg">
                        <!-- Image -->
                        <img
                            :src="photos[photoIndex].url"
                            :alt="photos[photoIndex].nom_original"
                            class="w-full max-h-96 object-contain bg-muted"
                        />

                        <!-- Boutons navigation -->
                        <button
                            v-if="photos.length > 1"
                            class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full p-1.5 transition-colors"
                            @click="prevPhoto"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <button
                            v-if="photos.length > 1"
                            class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full p-1.5 transition-colors"
                            @click="nextPhoto"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </button>

                        <!-- Bouton supprimer (uploader ou enseignant) -->
                        <button
                            v-if="peutSupprimerMedia(photos[photoIndex])"
                            class="absolute top-2 right-2 bg-destructive/80 hover:bg-destructive text-white rounded-full p-1.5 transition-colors"
                            @click="deleteMedia(photos[photoIndex])"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Légende -->
                    <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ photos[photoIndex].nom_original }}</span>
                        <span>par {{ photos[photoIndex].auteur.prenom }} {{ photos[photoIndex].auteur.nom }}</span>
                    </div>

                    <!-- Miniatures -->
                    <div v-if="photos.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="(photo, idx) in photos"
                            :key="photo.id"
                            class="shrink-0 h-14 w-14 rounded overflow-hidden border-2 transition-colors"
                            :class="idx === photoIndex ? 'border-primary' : 'border-transparent'"
                            @click="photoIndex = idx"
                        >
                            <img
                                :src="photo.url"
                                :alt="photo.nom_original"
                                class="h-full w-full object-cover"
                            />
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- Documents du groupe -->
            <Card v-if="documents.length > 0">
                <CardHeader>
                    <CardTitle>{{ $t('groupes.show.documents') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col divide-y">
                        <div
                            v-for="doc in documents"
                            :key="doc.id"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <FileText class="text-muted-foreground h-5 w-5 shrink-0" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ doc.nom_original }}</p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ doc.type.toUpperCase() }} · {{ formatSize(doc.taille) }} ·
                                        <span>{{ doc.auteur.prenom }} {{ doc.auteur.nom }}</span>
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
                </CardContent>
            </Card>

            <!-- Fichiers audio -->
            <Card v-if="audios.length > 0">
                <CardHeader>
                    <CardTitle>{{ $t('groupes.show.audio') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col divide-y">
                        <div
                            v-for="audio in audios"
                            :key="audio.id"
                            class="flex flex-col gap-2 py-3"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <Music class="text-muted-foreground h-5 w-5 shrink-0" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ audio.nom_original }}</p>
                                        <p class="text-muted-foreground text-xs">
                                            {{ formatSize(audio.taille) }} ·
                                            <span>{{ audio.auteur.prenom }} {{ audio.auteur.nom }}</span>
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
                            <audio controls class="w-full h-10">
                                <source :src="audio.url" />
                                {{ $t('groupes.show.browser_no_audio_support') }}
                            </audio>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Zone upload (membres seulement) -->
            <Card v-if="estMembre">
                <CardHeader>
                    <CardTitle>{{ $t('groupes.show.add_files') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <input
                        ref="mediaFileInput"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.mp3,.wav,.ogg,.m4a,.aac"
                        class="hidden"
                        @change="handleMediaChange"
                    />
                    <p v-if="mediaForm.errors.fichier" class="text-destructive mb-3 text-sm">
                        {{ mediaForm.errors.fichier }}
                    </p>
                    <div
                        class="border-2 border-dashed rounded-lg p-8 flex flex-col items-center gap-3 cursor-pointer hover:bg-muted/50 transition-colors"
                        @click="mediaFileInput?.click()"
                    >
                        <ImagePlus class="text-muted-foreground h-8 w-8" />
                        <div class="text-center">
                            <p class="text-sm font-medium">
                                {{ mediaForm.processing ? $t('groupes.show.uploading') : $t('groupes.show.click_to_add') }}
                            </p>
                            <p class="text-muted-foreground text-xs mt-1">
                                {{ $t('groupes.show.upload_help') }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Notes -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>
                            {{ $t('groupes.show.notes') }}
                            <span class="text-muted-foreground ml-2 text-sm font-normal">
                                ({{ groupe.notes.length }})
                            </span>
                        </CardTitle>
                        <Button
                            v-if="groupe.notes.length > 0"
                            variant="ghost"
                            size="sm"
                            @click="toggleToutesNotes"
                        >
                            {{ toutesNotesReduites ? $t('groupes.show.afficher_tout') : $t('groupes.show.masquer_tout') }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <!-- Liste des notes -->
                    <div v-if="groupe.notes.length === 0" class="text-muted-foreground py-4 text-center text-sm">
                        {{ $t('groupes.show.no_notes') }}
                    </div>

                    <div
                        v-for="note in groupe.notes"
                        :key="note.id"
                        class="border-b pb-4 last:border-0 last:pb-0"
                    >
                        <div class="mb-1 flex items-start justify-between gap-2">
                            <button
                                type="button"
                                class="flex flex-1 cursor-pointer items-center gap-2 text-left"
                                @click="toggleNote(note.id)"
                            >
                                <ChevronDown
                                    class="text-muted-foreground h-4 w-4 shrink-0 transition-transform"
                                    :class="{ 'rotate-180': !notesReduites.includes(note.id) }"
                                />
                                <span class="text-sm font-medium">
                                    {{ note.auteur.prenom }} {{ note.auteur.nom }}
                                </span>
                                <span class="text-muted-foreground text-xs">
                                    {{ formatDate(note.created_at) }}
                                </span>
                            </button>
                            <Button
                                v-if="note.user_id === userId"
                                size="sm"
                                variant="ghost"
                                class="text-destructive hover:text-destructive h-7 w-7 p-0"
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
                            <form class="flex flex-col gap-2" @submit.prevent="submitNote">
                                <textarea
                                    v-model="noteForm.contenu"
                                    rows="3"
                                    maxlength="2000"
                                    :placeholder="$t('groupes.show.write_note')"
                                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <p v-if="noteForm.errors.contenu" class="text-destructive text-sm">
                                    {{ noteForm.errors.contenu }}
                                </p>
                                <div class="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="noteForm.processing || !noteForm.contenu.trim()"
                                    >
                                        {{ $t('groupes.show.publish') }}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- Visioconférences -->
            <Card v-if="visioConferences.length > 0 || estEnseignant">
                <CardHeader>
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <Video class="h-5 w-5" />
                            Visioconférences
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="visioConferences.length === 0" class="text-sm text-muted-foreground">
                        Aucune visioconférence planifiée pour ce groupe.
                    </div>
                    <div v-else class="flex flex-col gap-3">
                        <VisioSession
                            v-for="visio in visioConferences"
                            :key="visio.id"
                            :visio="visio"
                            :can-manage="estEnseignant"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
        <!-- Dialog : gérer les membres -->
        <Dialog v-model:open="showMembresDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ $t('groupes.show.modal_manage_members') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submitMembres">

                    <!-- Inviter des étudiants -->
                    <div>
                        <p class="text-sm font-medium mb-2">{{ $t('groupes.show.modal_invite_students') }}</p>
                        <div v-if="etudiantsDispo.length === 0" class="text-muted-foreground text-sm">
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
                                    :checked="membresAjouter.includes(etudiant.id)"
                                    @click.prevent="() => toggleAjouter(etudiant.id)"
                                />
                                <Label :for="`ajouter-${etudiant.id}`" class="cursor-pointer font-normal">
                                    {{ etudiant.prenom }} {{ etudiant.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <!-- Retirer des membres -->
                    <div>
                        <p class="text-sm font-medium mb-2">{{ $t('groupes.show.modal_remove_members') }}</p>
                        <div class="space-y-2">
                            <div
                                v-for="membre in groupe.membres.filter(m => m.id !== userId)"
                                :key="membre.id"
                                class="flex items-center gap-3"
                            >
                                <Checkbox
                                    :id="`retirer-${membre.id}`"
                                    :checked="membresRetirer.includes(membre.id)"
                                    @click.prevent="() => toggleRetirer(membre.id)"
                                />
                                <Label :for="`retirer-${membre.id}`" class="cursor-pointer font-normal">
                                    {{ membre.prenom }} {{ membre.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <p v-if="membresError" class="text-destructive text-sm">
                        {{ membresError }}
                    </p>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showMembresDialog = false">
                            {{ $t('common.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="membresForm.processing">
                            {{ $t('common.save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog : modifier les thématiques -->
        <FormDialog
            v-model:open="showThematiquesDialog"
            :title="$t('groupes.show.modal_edit_thematic')"
            :is-loading="thematiquesForm.processing"
            scrollable
            @submit="submitThematiques"
        >
            <p class="text-muted-foreground text-sm">
                {{ $t('groupes.show.modal_thematic_help') }}
                <span class="font-medium">({{ thematiquesSelectionnees.length }}/3)</span>
            </p>

            <div v-if="thematiquesDispo.length === 0" class="text-muted-foreground text-sm">
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
                        :checked="thematiquesSelectionnees.includes(thematique.id)"
                        :disabled="thematiquesMax && !thematiquesSelectionnees.includes(thematique.id)"
                        @click.prevent="() => toggleThematique(thematique.id)"
                    />
                    <Label
                        :for="`t-${thematique.id}`"
                        class="cursor-pointer font-normal leading-snug"
                        :class="{ 'text-muted-foreground': thematiquesMax && !thematiquesSelectionnees.includes(thematique.id) }"
                    >
                        {{ thematique.nom }}
                        <span v-if="thematique.periode_historique" class="ml-1 text-xs text-muted-foreground">
                            — {{ thematique.periode_historique }}
                        </span>
                    </Label>
                </div>
            </div>

            <p v-if="thematiquesError" class="text-sm text-destructive">{{ thematiquesError }}</p>
        </FormDialog>
    </AppLayout>
</template>
