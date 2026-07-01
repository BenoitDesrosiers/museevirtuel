<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookMarked,
    Check,
    ChevronDown,
    ChevronUp,
    Combine,
    Copy,
    Edit3,
    LoaderCircle,
    Mic,
    Play,
    Plus,
    Square,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import * as GroupeController from '@/actions/App/Http/Controllers/GroupeController';
import * as ChapitreRoutes from '@/actions/App/Http/Controllers/GroupeVideoChapitreController';
import * as GroupeVideoController from '@/actions/App/Http/Controllers/GroupeVideoController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDuree } from '@/lib/formatters';

type Auteur = {
    id: number;
    prenom: string;
    nom: string;
};

type TranscriptionSegment = {
    start: number;
    end: number;
    text: string;
};

type Chapitre = {
    id: number;
    label: string;
    debut: number;
    fin: number | null;
    ordre: number;
};

type Video = {
    id: number;
    titre: string;
    description: string | null;
    statut: 'brouillon' | 'publié' | 'archivé';
    traitement_statut: string | null;
    transcription_statut: string | null;
    transcription: string | null;
    transcription_segments: TranscriptionSegment[] | null;
    transcription_modifiee: boolean;
    duree: number | null;
    taille: number;
    url: string;
    thumbnail_url: string | null;
    user_id: number;
    auteur: Auteur;
    chapitres: Chapitre[];
};

type Cours = { id: number; nom_cours: string };
type Classe = { id: number; cours_id: number };
type Groupe = { id: number; numero: number; classe_id: number };

type AutreVideo = {
    id: number;
    titre: string;
    duree: number | null;
    thumbnail_url: string | null;
};

type Props = {
    cours: Cours;
    classe: Classe;
    groupe: Groupe;
    video: Video;
    autresVideos: AutreVideo[];
    peutTranscrire: boolean;
    peutModifierTranscription: boolean;
    peutGererChapitres: boolean;
};

const props = defineProps<Props>();

// ─── Références du lecteur ────────────────────────────────────────────────────
const videoEl = ref<HTMLVideoElement | null>(null);
const dureeVideo = ref(props.video.duree ?? 0);
const currentTime = ref(0);

// ─── Formulaire d'édition timeline ───────────────────────────────────────────
type Coupe = { debut: number; fin: number };

const editForm = useForm({
    debut: 0,
    fin: props.video.duree ?? 0,
    coupes: [] as Coupe[],
});

function onVideoLoaded() {
    if (!videoEl.value) {
        return;
    }

    dureeVideo.value = videoEl.value.duration;

    if (editForm.fin === 0) {
        editForm.fin = videoEl.value.duration;
    }
}

function onTimeUpdate() {
    if (videoEl.value) {
        currentTime.value = videoEl.value.currentTime;
    }
}

/**
 * Positionne la lecture vidéo à un timestamp donné.
 */
function seekTo(seconds: number) {
    if (videoEl.value) {
        videoEl.value.currentTime = seconds;
        videoEl.value.play();
    }
}

/**
 * Positionne la lecture vidéo au début du segment cliqué.
 */
function seekToSegment(segment: TranscriptionSegment) {
    seekTo(segment.start);
}

function submitEdit() {
    stopPreview();

    // Mise à jour optimiste : afficher l'écran de chargement sans attendre
    // la réponse du serveur, et démarrer le polling tout de suite.
    traitementStatut.value = 'en_attente';
    demarrerPolling();

    editForm.post(
        GroupeVideoController.editer({
            cours: props.cours,
            classe: props.classe,
            groupe: props.groupe,
            video: props.video,
        }).url,
        { preserveScroll: true },
    );
}

// ─── Coupes internes ──────────────────────────────────────────────────────────
function ajouterCoupe() {
    const mi = dureeVideo.value / 2;
    editForm.coupes.push({
        debut: Math.max(0, mi - 2),
        fin: Math.min(dureeVideo.value, mi + 2),
    });
}

function supprimerCoupe(idx: number) {
    editForm.coupes.splice(idx, 1);
}

// ─── Aperçu des modifications ─────────────────────────────────────────────────
const isPreviewing = ref(false);
let previewCleanup: (() => void) | null = null;

/**
 * Calcule les segments [debut, fin] à conserver après application des coupes.
 * Miroir JS de ProcessVideoEdit::calculerSegments() pour le preview client.
 */
function calculerSegments(
    debut: number,
    fin: number,
    coupes: Coupe[],
): [number, number][] {
    if (coupes.length === 0) {
        return [[debut, fin]];
    }

    const sorted = [...coupes].sort((a, b) => a.debut - b.debut);
    const segments: [number, number][] = [];
    let curseur = debut;

    for (const c of sorted) {
        if (c.debut > curseur) {
            segments.push([curseur, c.debut]);
        }

        curseur = c.fin;
    }

    if (curseur < fin) {
        segments.push([curseur, fin]);
    }

    return segments;
}

function stopPreview() {
    previewCleanup?.();
    previewCleanup = null;
}

function previewModifications() {
    if (!videoEl.value) {
        return;
    }

    stopPreview();

    const segments = calculerSegments(
        editForm.debut,
        editForm.fin,
        editForm.coupes,
    );

    if (segments.length === 0) {
        return;
    }

    let segIdx = 0;
    isPreviewing.value = true;
    videoEl.value.currentTime = segments[0][0];
    videoEl.value.play();

    function onPreviewTimeUpdate() {
        if (!videoEl.value) {
            return;
        }

        const [, segFin] = segments[segIdx];

        if (videoEl.value.currentTime >= segFin) {
            segIdx++;

            if (segIdx >= segments.length) {
                // Aperçu terminé — on remet sur le début du trim.
                stopPreview();
                videoEl.value.currentTime = segments[0][0];
                videoEl.value.pause();
            } else {
                videoEl.value.currentTime = segments[segIdx][0];
            }
        }
    }

    videoEl.value.addEventListener('timeupdate', onPreviewTimeUpdate);

    previewCleanup = () => {
        videoEl.value?.removeEventListener('timeupdate', onPreviewTimeUpdate);
        videoEl.value?.pause();
        isPreviewing.value = false;
    };
}

// Arrête l'aperçu si l'utilisateur modifie les paramètres.
watch(
    () => [editForm.debut, editForm.fin, JSON.stringify(editForm.coupes)],
    () => {
        if (isPreviewing.value) {
            stopPreview();
        }
    },
);

// ─── Jumelage (insertion d'une autre vidéo) ───────────────────────────────────
const showJumelage = ref(false);
const jumelageVideoId = ref<number | null>(null);
const jumelagePosition = ref(0);

const jumelageForm = useForm({
    video_a_inserer_id: null as number | null,
    position: 0,
});

/** Vidéo sélectionnée pour l'insertion (null si aucune sélection). */
const videoJumelage = computed(
    () =>
        props.autresVideos.find((v) => v.id === jumelageVideoId.value) ?? null,
);

/** Durée totale estimée après jumelage. */
const dureeTotale = computed(
    () => (dureeVideo.value ?? 0) + (videoJumelage.value?.duree ?? 0),
);

/** Proportions de la timeline de prévisualisation (valeurs entre 0 et 1). */
const proportions = computed(() => {
    const total = dureeTotale.value;

    if (!total) {
        return { avant: 0, insert: 0, apres: 0 };
    }

    return {
        avant: jumelagePosition.value / total,
        insert: (videoJumelage.value?.duree ?? 0) / total,
        apres:
            Math.max(0, (dureeVideo.value ?? 0) - jumelagePosition.value) /
            total,
    };
});

function submitJumelage() {
    jumelageForm.video_a_inserer_id = jumelageVideoId.value;
    jumelageForm.position = jumelagePosition.value;

    const url = GroupeVideoController.jumeler({
        cours: props.cours,
        classe: props.classe,
        groupe: props.groupe,
        video: props.video,
    }).url;

    jumelageForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            showJumelage.value = false;
            // Mise à jour optimiste : déclenche le polling immédiatement.
            traitementStatut.value = 'en_attente';
            demarrerPolling();
        },
    });
}

function annulerJumelage() {
    showJumelage.value = false;
    jumelageVideoId.value = null;
    jumelagePosition.value = 0;
    jumelageForm.reset();
}

// ─── Upload inline pour le jumelage ──────────────────────────────────────────
const showUploadInline = ref(false);
const uploadInlineForm = useForm({
    titre: '',
    fichier: null as File | null,
});

function onUploadInlineFichier(e: Event) {
    const input = e.target as HTMLInputElement;
    uploadInlineForm.fichier = input.files?.[0] ?? null;
}

/**
 * Soumet le formulaire d'upload inline et recharge la page via Inertia.
 * La nouvelle vidéo apparaîtra dans autresVideos après le rechargement.
 */
function submitUploadInline() {
    uploadInlineForm.post(
        GroupeVideoController.store({
            cours: props.cours,
            classe: props.classe,
            groupe: props.groupe,
        }).url,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showUploadInline.value = false;
                uploadInlineForm.reset();
            },
        },
    );
}

// ─── Chapitres ────────────────────────────────────────────────────────────────
const chapitres = ref<Chapitre[]>(props.video.chapitres ?? []);

// Synchronise quand Inertia met à jour les props.
watch(
    () => props.video.chapitres,
    (val) => {
        chapitres.value = val ?? [];
    },
);

const chapitreForm = useForm({
    label: '',
    debut: 0,
    fin: null as number | null,
});

const chapitreEnEdition = ref<Chapitre | null>(null);
const showChapitreForm = ref(false);

/**
 * Positionne le temps de début du chapitre sur le temps de lecture actuel.
 */
function capturerDebutActuel() {
    chapitreForm.debut = Math.round(currentTime.value * 100) / 100;
}

/**
 * Positionne le temps de fin du chapitre sur le temps de lecture actuel.
 */
function capturerFinActuelle() {
    chapitreForm.fin = Math.round(currentTime.value * 100) / 100;
}

/**
 * Parse un timestamp texte (MM:SS, M:SS, HH:MM:SS ou secondes brutes) en secondes.
 * Retourne 0 si la valeur est vide ou invalide.
 */
function parseTemps(val: string): number {
    const s = val.trim();
    if (s === '') return 0;
    if (s.includes(':')) {
        const parts = s.split(':').map((p) => parseFloat(p) || 0);
        if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
        if (parts.length === 2) return parts[0] * 60 + parts[1];
    }
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
}

/**
 * Refs locaux pour les champs texte (MM:SS) des temps de début et de fin.
 * Synchronisés avec chapitreForm via des watchers.
 */
const debutTexte = ref(formatDuree(chapitreForm.debut));
const finTexte = ref('');

watch(
    () => chapitreForm.debut,
    (val) => {
        debutTexte.value = formatDuree(val);
    },
);
watch(
    () => chapitreForm.fin,
    (val) => {
        finTexte.value = val !== null ? formatDuree(val) : '';
    },
);

/** Appelé au blur du champ Début : parse le texte et normalise l'affichage. */
function normaliserDebutTexte() {
    chapitreForm.debut = parseTemps(debutTexte.value);
    debutTexte.value = formatDuree(chapitreForm.debut);
}

/** Appelé au blur du champ Fin : vide → null, sinon parse et normalise. */
function normaliserFinTexte() {
    const s = finTexte.value.trim();
    if (s === '') {
        chapitreForm.fin = null;
    } else {
        chapitreForm.fin = parseTemps(s);
        finTexte.value = formatDuree(chapitreForm.fin);
    }
}

function ouvrirFormChapitre(chapitre?: Chapitre) {
    if (chapitre) {
        chapitreEnEdition.value = chapitre;
        chapitreForm.label = chapitre.label;
        chapitreForm.debut = chapitre.debut;
        chapitreForm.fin = chapitre.fin;
    } else {
        chapitreEnEdition.value = null;
        chapitreForm.reset();
        chapitreForm.debut = Math.round(currentTime.value * 100) / 100;
    }

    showChapitreForm.value = true;
}

function annulerChapitre() {
    showChapitreForm.value = false;
    chapitreEnEdition.value = null;
    chapitreForm.reset();
}

function submitChapitre() {
    const routeArgs = {
        cours: props.cours,
        classe: props.classe,
        groupe: props.groupe,
        video: props.video,
    };

    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showChapitreForm.value = false;
            chapitreEnEdition.value = null;
            chapitreForm.reset();
        },
    };

    if (chapitreEnEdition.value) {
        chapitreForm.patch(
            ChapitreRoutes.update({
                ...routeArgs,
                chapitre: chapitreEnEdition.value,
            }).url,
            opts,
        );
    } else {
        chapitreForm.post(ChapitreRoutes.store(routeArgs).url, opts);
    }
}

function supprimerChapitre(chapitre: Chapitre) {
    router.delete(
        ChapitreRoutes.destroy({
            cours: props.cours,
            classe: props.classe,
            groupe: props.groupe,
            video: props.video,
            chapitre,
        }).url,
        { preserveScroll: true },
    );
}

// ─── Transcription Whisper ────────────────────────────────────────────────────
const transcriptionStatut = ref(props.video.transcription_statut);
const transcriptionTexte = ref(props.video.transcription);
const transcriptionModifiee = ref(props.video.transcription_modifiee);
const transcriptionSegments = ref<TranscriptionSegment[] | null>(
    props.video.transcription_segments ?? null,
);

// Index du segment dont le début ≤ currentTime < fin — sert à surligner
// la phrase en cours de lecture en temps réel.
const segmentActifIndex = computed(() => {
    if (!transcriptionSegments.value || !transcriptionSegments.value.length) {
        return -1;
    }

    const t = currentTime.value;

    return transcriptionSegments.value.findIndex(
        (s) => t >= s.start && t < s.end,
    );
});

// Élément DOM du panneau de transcription — utilisé pour l'auto-scroll.
const transcriptionEl = ref<HTMLElement | null>(null);

/**
 * Fait défiler le panneau de transcription pour maintenir le segment
 * actif visible sans déclencher un scroll sur toute la page.
 */
watch(segmentActifIndex, (idx) => {
    if (idx < 0 || !transcriptionEl.value || modeEditionTranscription.value) {
        return;
    }

    const span = transcriptionEl.value.querySelectorAll('[data-segment]')[
        idx
    ] as HTMLElement | undefined;
    span?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
});

const transcrireForm = useForm({});

// ─── Éditeur de transcription ─────────────────────────────────────────────────
const modeEditionTranscription = ref(false);

/**
 * Copie profonde des segments pour l'édition locale sans modifier la source.
 */
const segmentsEdites = ref<TranscriptionSegment[]>([]);

// ─── Groupage transcription par chapitre ──────────────────────────────────────

/**
 * Retourne le chapitre auquel appartient un segment, ou null.
 * Le segment appartient au dernier chapitre dont debut ≤ segment.start
 * et dont fin est nulle ou > segment.start.
 */
function trouverChapitreDeSegment(
    seg: TranscriptionSegment,
    chaps: Chapitre[],
): Chapitre | null {
    for (let i = chaps.length - 1; i >= 0; i--) {
        const c = chaps[i];
        if (seg.start >= c.debut) {
            return c.fin === null || seg.start < c.fin ? c : null;
        }
    }
    return null;
}

/**
 * Regroupe les segments de transcription (avec leur index global) par chapitre.
 * Chaque groupe contient le chapitre (ou null pour les segments non couverts)
 * et la liste des entrées { s: segment, i: index global }.
 */
const groupesTranscription = computed(() => {
    const segs = transcriptionSegments.value;
    if (!segs?.length) return null;

    const chaps = [...chapitres.value].sort((a, b) => a.debut - b.debut);

    const groupes: {
        chapitre: Chapitre | null;
        segments: { s: TranscriptionSegment; i: number }[];
    }[] = [];
    let chapCourantId: number | null | undefined = undefined;

    for (let i = 0; i < segs.length; i++) {
        const seg = segs[i];
        const chap = chaps.length ? trouverChapitreDeSegment(seg, chaps) : null;
        const chapId = chap?.id ?? null;
        if (chapId !== chapCourantId) {
            groupes.push({ chapitre: chap, segments: [{ s: seg, i }] });
            chapCourantId = chapId;
        } else {
            groupes.at(-1)!.segments.push({ s: seg, i });
        }
    }

    return groupes;
});

/**
 * Même regroupement que groupesTranscription mais sur segmentsEdites,
 * pour conserver les index globaux nécessaires au v-model en mode édition.
 */
const groupesEdites = computed(() => {
    if (!segmentsEdites.value.length) return null;

    const chaps = [...chapitres.value].sort((a, b) => a.debut - b.debut);

    const groupes: {
        chapitre: Chapitre | null;
        segments: { s: TranscriptionSegment; i: number }[];
    }[] = [];
    let chapCourantId: number | null | undefined = undefined;

    for (let i = 0; i < segmentsEdites.value.length; i++) {
        const seg = segmentsEdites.value[i];
        const chap = chaps.length ? trouverChapitreDeSegment(seg, chaps) : null;
        const chapId = chap?.id ?? null;
        if (chapId !== chapCourantId) {
            groupes.push({ chapitre: chap, segments: [{ s: seg, i }] });
            chapCourantId = chapId;
        } else {
            groupes.at(-1)!.segments.push({ s: seg, i });
        }
    }

    return groupes;
});

function ouvrirEditionTranscription() {
    segmentsEdites.value = (transcriptionSegments.value ?? []).map((s) => ({
        ...s,
    }));
    modeEditionTranscription.value = true;
}

function annulerEditionTranscription() {
    modeEditionTranscription.value = false;
    segmentsEdites.value = [];
}

const sauvegardeTranscriptionForm = useForm({});

function sauvegarderTranscription() {
    sauvegardeTranscriptionForm
        .transform(() => ({ segments: segmentsEdites.value }))
        .post(
            GroupeVideoController.modifierTranscription({
                cours: props.cours,
                classe: props.classe,
                groupe: props.groupe,
                video: props.video,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    transcriptionSegments.value = segmentsEdites.value.map(
                        (s) => ({ ...s }),
                    );
                    transcriptionTexte.value = segmentsEdites.value
                        .map((s) => s.text)
                        .join(' ');
                    transcriptionModifiee.value = true;
                    modeEditionTranscription.value = false;
                },
            },
        );
}

// ─── Import de transcription (.txt / .srt / .vtt) ────────────────────────────
const importTranscriptionForm = useForm({
    fichier: null as File | null,
});

const importFichierRef = ref<HTMLInputElement | null>(null);

function onImportFichierChange(e: Event) {
    const input = e.target as HTMLInputElement;
    importTranscriptionForm.fichier = input.files?.[0] ?? null;

    if (importTranscriptionForm.fichier) {
        importTranscriptionForm
            .transform((data) => ({ fichier: data.fichier }))
            .post(
                GroupeVideoController.importerTranscription({
                    cours: props.cours,
                    classe: props.classe,
                    groupe: props.groupe,
                    video: props.video,
                }).url,
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        importTranscriptionForm.reset();
                        if (importFichierRef.value) {
                            importFichierRef.value.value = '';
                        }
                        transcriptionModifiee.value = true;
                    },
                },
            );
    }
}

// ─── Copie transcription ──────────────────────────────────────────────────────
const copié = ref(false);
const transcriptionOuverte = ref(true);

async function copierTranscription() {
    if (!transcriptionTexte.value) {
        return;
    }

    if (navigator.clipboard) {
        // API moderne — disponible uniquement en HTTPS ou localhost.
        await navigator.clipboard.writeText(transcriptionTexte.value);
    } else {
        // Fallback pour les contextes HTTP (développement sans HTTPS).
        const ta = document.createElement('textarea');
        ta.value = transcriptionTexte.value;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }

    copié.value = true;
    setTimeout(() => (copié.value = false), 2000);
}

function submitTranscrire() {
    // Mise à jour optimiste — afficher le spinner sans attendre la réponse.
    transcriptionStatut.value = 'en_attente';
    transcriptionModifiee.value = false;
    demarrerPolling();

    transcrireForm.post(
        GroupeVideoController.transcrire({
            cours: props.cours,
            classe: props.classe,
            groupe: props.groupe,
            video: props.video,
        }).url,
        { preserveScroll: true },
    );
}

// Synchronise les refs quand Inertia met à jour les props après un redirect.
watch(
    () => props.video.transcription_statut,
    (newVal) => {
        transcriptionStatut.value = newVal;

        if (newVal === 'en_attente' || newVal === 'en_cours') {
            demarrerPolling();
        }
    },
);

watch(
    () => props.video.transcription,
    (newVal) => {
        transcriptionTexte.value = newVal;
    },
);

watch(
    () => props.video.transcription_segments,
    (newVal) => {
        transcriptionSegments.value = newVal ?? null;
    },
);

watch(
    () => props.video.transcription_modifiee,
    (newVal) => {
        transcriptionModifiee.value = newVal;
    },
);

// ─── Polling statut traitement ────────────────────────────────────────────────
const traitementStatut = ref(props.video.traitement_statut);

// Synchronise le ref quand Inertia met à jour les props après un redirect.
// Sans ce watch, traitementStatut resterait à sa valeur initiale après
// la soumission du formulaire d'édition.
watch(
    () => props.video.traitement_statut,
    (newVal) => {
        traitementStatut.value = newVal;

        if (newVal === 'en_attente' || newVal === 'en_cours') {
            demarrerPolling();
        }
    },
);
let pollingInterval: ReturnType<typeof setInterval> | null = null;

function demarrerPolling() {
    if (pollingInterval) {
        return;
    }

    pollingInterval = setInterval(async () => {
        const traitementActif =
            traitementStatut.value === 'en_attente' ||
            traitementStatut.value === 'en_cours';
        const transcriptionActive =
            transcriptionStatut.value === 'en_attente' ||
            transcriptionStatut.value === 'en_cours';

        if (!traitementActif && !transcriptionActive) {
            arreterPolling();

            return;
        }

        try {
            const url = GroupeVideoController.statut({
                cours: props.cours,
                classe: props.classe,
                groupe: props.groupe,
                video: props.video,
            }).url;

            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();

            const étaitActif =
                traitementStatut.value === 'en_attente' ||
                traitementStatut.value === 'en_cours';

            traitementStatut.value = data.traitement_statut;
            transcriptionStatut.value = data.transcription_statut;
            transcriptionTexte.value = data.transcription;
            transcriptionSegments.value = data.transcription_segments ?? null;

            // Recharge uniquement lors de la transition actif → terminé,
            // pas si la page est rechargée alors que le statut est déjà 'terminé'.
            if (étaitActif && data.traitement_statut === 'terminé') {
                window.location.reload();
            }
        } catch {
            // On ignore les erreurs réseau ponctuelles.
        }
    }, 3000);
}

function arreterPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

onMounted(() => {
    if (
        traitementStatut.value === 'en_attente' ||
        traitementStatut.value === 'en_cours' ||
        transcriptionStatut.value === 'en_attente' ||
        transcriptionStatut.value === 'en_cours'
    ) {
        demarrerPolling();
    }
});

onUnmounted(() => {
    arreterPolling();
    stopPreview();
});
</script>

<template>
    <AppLayout>
        <Head :title="video.titre" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        :href="
                            GroupeController.show({ cours, classe, groupe }).url
                        "
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour au groupe
                    </Link>
                </Button>
            </div>

            <!-- Titre + statut -->
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold">{{ video.titre }}</h1>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="{
                        'bg-amber-100 text-amber-800':
                            video.statut === 'brouillon',
                        'bg-green-100 text-green-800':
                            video.statut === 'publié',
                        'bg-muted text-muted-foreground':
                            video.statut === 'archivé',
                    }"
                >
                    {{ video.statut }}
                </span>
            </div>

            <!-- Bannière erreur de traitement -->
            <div
                v-if="traitementStatut === 'erreur'"
                class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                Le traitement a échoué. Vérifiez que FFmpeg est installé sur le
                serveur.
            </div>

            <!-- Lecteur vidéo -->
            <Card>
                <CardContent class="p-4">
                    <!-- Badge aperçu en cours -->
                    <div
                        v-if="isPreviewing"
                        class="mb-2 flex items-center gap-2 rounded-md bg-primary/10 px-3 py-1.5 text-xs font-medium text-primary"
                    >
                        <span
                            class="inline-block h-2 w-2 animate-pulse rounded-full bg-primary"
                        />
                        Aperçu en cours — lecture de la version modifiée
                    </div>

                    <video
                        ref="videoEl"
                        :src="video.url"
                        controls
                        class="max-h-[60vh] w-full rounded-lg bg-black"
                        @loadedmetadata="onVideoLoaded"
                        @timeupdate="onTimeUpdate"
                    />

                    <!-- Marqueurs de chapitres sous le lecteur -->
                    <div
                        v-if="chapitres.length > 0 && dureeVideo > 0"
                        class="relative mt-2 h-5 w-full"
                    >
                        <button
                            v-for="chap in chapitres"
                            :key="chap.id"
                            type="button"
                            class="absolute top-0 flex -translate-x-1/2 flex-col items-center"
                            :style="{
                                left: `${(chap.debut / dureeVideo) * 100}%`,
                            }"
                            :title="chap.label"
                            @click="seekTo(chap.debut)"
                        >
                            <span class="h-3 w-0.5 bg-primary/60" />
                            <span
                                class="max-w-[80px] truncate text-[9px] text-muted-foreground"
                                >{{ chap.label }}</span
                            >
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Chapitres ──────────────────────────────────────────────── -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <BookMarked class="h-4 w-4" />
                            Chapitres
                        </CardTitle>
                        <Button
                            v-if="peutGererChapitres && !showChapitreForm"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="ouvrirFormChapitre()"
                        >
                            <Plus class="mr-1.5 h-3.5 w-3.5" />
                            Ajouter un chapitre
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <!-- Liste des chapitres -->
                    <div
                        v-if="chapitres.length > 0"
                        class="divide-y rounded-md border"
                    >
                        <div
                            v-for="chap in chapitres"
                            :key="chap.id"
                            class="flex items-center gap-3 px-3 py-2"
                        >
                            <button
                                type="button"
                                class="flex flex-1 items-center gap-2 text-left"
                                @click="seekTo(chap.debut)"
                            >
                                <span
                                    class="min-w-[3.5rem] text-xs tabular-nums text-primary"
                                >
                                    {{ formatDuree(chap.debut) }}
                                </span>
                                <span
                                    v-if="chap.fin !== null"
                                    class="text-xs text-muted-foreground"
                                >
                                    → {{ formatDuree(chap.fin) }}
                                </span>
                                <span class="text-sm">{{ chap.label }}</span>
                            </button>
                            <div
                                v-if="peutGererChapitres"
                                class="flex shrink-0 gap-1"
                            >
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground"
                                    @click="ouvrirFormChapitre(chap)"
                                >
                                    <Edit3 class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="supprimerChapitre(chap)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <p
                        v-else-if="!showChapitreForm"
                        class="text-sm text-muted-foreground"
                    >
                        Aucun chapitre défini. Ajoutez des chapitres pour
                        structurer la transcription.
                    </p>

                    <!-- Formulaire d'ajout / édition de chapitre -->
                    <div
                        v-if="showChapitreForm && peutGererChapitres"
                        class="rounded-md border bg-muted/30 p-4"
                    >
                        <p class="mb-3 text-sm font-medium">
                            {{
                                chapitreEnEdition
                                    ? 'Modifier le chapitre'
                                    : 'Nouveau chapitre'
                            }}
                        </p>
                        <div class="flex flex-col gap-3">
                            <div class="grid gap-1">
                                <label class="text-xs font-medium"
                                    >Titre du chapitre</label
                                >
                                <Input
                                    v-model="chapitreForm.label"
                                    placeholder="ex. Introduction, Méthode…"
                                    class="text-sm"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <!-- Début -->
                                <div class="grid gap-1">
                                    <label class="text-xs font-medium"
                                        >Début</label
                                    >
                                    <div class="flex items-center gap-1.5">
                                        <Input
                                            v-model="debutTexte"
                                            placeholder="0:00"
                                            class="w-20 text-center text-sm tabular-nums"
                                            @blur="normaliserDebutTexte"
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="shrink-0 text-xs"
                                            :title="`Capter le temps actuel : ${formatDuree(currentTime)}`"
                                            @click="capturerDebutActuel"
                                        >
                                            Capter ({{
                                                formatDuree(currentTime)
                                            }})
                                        </Button>
                                    </div>
                                </div>

                                <!-- Fin -->
                                <div class="grid gap-1">
                                    <label class="text-xs font-medium">
                                        Fin
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >(optionnel)</span
                                        >
                                    </label>
                                    <div class="flex items-center gap-1.5">
                                        <Input
                                            v-model="finTexte"
                                            placeholder="—"
                                            class="w-20 text-center text-sm tabular-nums"
                                            @blur="normaliserFinTexte"
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="shrink-0 text-xs"
                                            :title="`Capter le temps actuel : ${formatDuree(currentTime)}`"
                                            @click="capturerFinActuelle"
                                        >
                                            Capter ({{
                                                formatDuree(currentTime)
                                            }})
                                        </Button>
                                        <button
                                            v-if="chapitreForm.fin !== null"
                                            type="button"
                                            class="shrink-0 text-muted-foreground hover:text-destructive"
                                            title="Effacer la fin"
                                            @click="chapitreForm.fin = null"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2 pt-1">
                                <Button
                                    type="button"
                                    size="sm"
                                    :disabled="
                                        chapitreForm.processing ||
                                        !chapitreForm.label
                                    "
                                    @click="submitChapitre"
                                >
                                    {{
                                        chapitreForm.processing
                                            ? 'Sauvegarde…'
                                            : 'Sauvegarder'
                                    }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    @click="annulerChapitre"
                                >
                                    Annuler
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Transcription ──────────────────────────────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle>Transcription</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <!-- En attente ou en cours -->
                    <div
                        v-if="
                            transcriptionStatut === 'en_attente' ||
                            transcriptionStatut === 'en_cours'
                        "
                        class="flex items-center gap-3 text-sm text-muted-foreground"
                    >
                        <LoaderCircle
                            class="h-4 w-4 animate-spin text-primary"
                        />
                        Transcription en cours…
                    </div>

                    <!-- Erreur -->
                    <template v-else-if="transcriptionStatut === 'erreur'">
                        <p class="text-sm text-destructive">
                            La transcription a échoué. Vérifiez que Whisper est
                            installé sur le serveur.
                        </p>
                        <Button
                            v-if="peutTranscrire"
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="transcrireForm.processing"
                            @click="submitTranscrire"
                        >
                            <Mic class="mr-2 h-4 w-4" />
                            Réessayer la transcription
                        </Button>
                    </template>

                    <!-- Transcription disponible -->
                    <template v-else-if="transcriptionTexte">
                        <!-- Barre d'outils -->
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2"
                                    @click="
                                        transcriptionOuverte =
                                            !transcriptionOuverte
                                    "
                                >
                                    <ChevronUp
                                        v-if="transcriptionOuverte"
                                        class="mr-2 h-4 w-4"
                                    />
                                    <ChevronDown
                                        v-else
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{
                                        transcriptionOuverte
                                            ? 'Réduire'
                                            : 'Afficher'
                                    }}
                                </Button>

                                <!-- Badge transcription modifiée manuellement -->
                                <span
                                    v-if="transcriptionModifiee"
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"
                                >
                                    Modifiée manuellement
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <!-- Mode édition / lecture -->
                                <Button
                                    v-if="
                                        peutModifierTranscription &&
                                        transcriptionSegments?.length &&
                                        !modeEditionTranscription
                                    "
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="ouvrirEditionTranscription"
                                >
                                    <Edit3 class="mr-2 h-4 w-4" />
                                    Modifier
                                </Button>

                                <!-- Import fichier .txt / .srt / .vtt -->
                                <template v-if="peutModifierTranscription">
                                    <input
                                        ref="importFichierRef"
                                        type="file"
                                        accept=".txt,.srt,.vtt"
                                        class="hidden"
                                        @change="onImportFichierChange"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            importTranscriptionForm.processing
                                        "
                                        @click="importFichierRef?.click()"
                                    >
                                        <Upload class="mr-2 h-4 w-4" />
                                        {{
                                            importTranscriptionForm.processing
                                                ? 'Import…'
                                                : 'Importer (.txt/.srt/.vtt)'
                                        }}
                                    </Button>
                                </template>

                                <!-- Copier -->
                                <Button
                                    v-if="!modeEditionTranscription"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="copierTranscription"
                                >
                                    <Check
                                        v-if="copié"
                                        class="mr-2 h-4 w-4 text-green-600"
                                    />
                                    <Copy v-else class="mr-2 h-4 w-4" />
                                    {{ copié ? 'Copié !' : 'Copier' }}
                                </Button>
                            </div>
                        </div>

                        <!-- Panneau transcription ouvert -->
                        <div v-if="transcriptionOuverte">
                            <!-- ── Mode lecture ── -->
                            <div
                                v-if="!modeEditionTranscription"
                                ref="transcriptionEl"
                                class="rounded-md bg-muted p-4 text-sm leading-relaxed"
                            >
                                <!-- Segments groupés par chapitre -->
                                <template v-if="groupesTranscription">
                                    <div
                                        v-for="(groupe, gi) in groupesTranscription"
                                        :key="gi"
                                        :class="gi > 0 ? 'mt-4' : ''"
                                    >
                                        <!-- En-tête de section (chapitre) -->
                                        <div
                                            v-if="groupe.chapitre"
                                            class="mb-2 flex items-center gap-2"
                                        >
                                            <div
                                                class="h-px flex-1 bg-border"
                                            />
                                            <button
                                                type="button"
                                                class="shrink-0 text-xs font-semibold text-foreground hover:text-primary"
                                                :title="`Aller à ${formatDuree(groupe.chapitre.debut)}`"
                                                @click="seekTo(groupe.chapitre.debut)"
                                            >
                                                {{ groupe.chapitre.label }}
                                            </button>
                                            <span
                                                class="shrink-0 tabular-nums text-xs text-muted-foreground"
                                            >
                                                {{
                                                    formatDuree(
                                                        groupe.chapitre.debut,
                                                    )
                                                }}
                                            </span>
                                            <div
                                                class="h-px flex-1 bg-border"
                                            />
                                        </div>

                                        <!-- Segments du groupe -->
                                        <span
                                            v-for="entry in groupe.segments"
                                            :key="entry.i"
                                            data-segment
                                            :class="[
                                                'cursor-pointer rounded px-0.5 transition-colors',
                                                entry.i === segmentActifIndex
                                                    ? 'bg-primary/20 text-primary'
                                                    : 'hover:bg-primary/10',
                                            ]"
                                            :title="formatDuree(entry.s.start)"
                                            @click="seekToSegment(entry.s)"
                                            >{{ entry.s.text }}
                                        </span>
                                    </div>
                                </template>
                                <!-- Fallback texte brut -->
                                <span v-else class="whitespace-pre-wrap">{{
                                    transcriptionTexte
                                }}</span>
                            </div>

                            <!-- ── Mode édition ── -->
                            <div v-else class="flex flex-col gap-3">
                                <p class="text-xs text-muted-foreground">
                                    Modifiez le texte de chaque segment.
                                    Les horodatages sont conservés.
                                </p>
                                <div class="max-h-[50vh] overflow-y-auto rounded-md border">
                                    <template
                                        v-if="groupesEdites"
                                        v-for="(groupe, gi) in groupesEdites"
                                        :key="gi"
                                    >
                                        <!-- En-tête de section -->
                                        <div
                                            v-if="groupe.chapitre"
                                            class="flex items-center gap-2 border-b bg-muted/50 px-3 py-1.5"
                                        >
                                            <span
                                                class="text-xs font-semibold"
                                                >{{
                                                    groupe.chapitre.label
                                                }}</span
                                            >
                                            <span
                                                class="tabular-nums text-xs text-muted-foreground"
                                                >{{
                                                    formatDuree(
                                                        groupe.chapitre.debut,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <!-- Segments du groupe -->
                                        <div
                                            v-for="entry in groupe.segments"
                                            :key="entry.i"
                                            class="flex gap-2 border-b p-2 last:border-b-0"
                                        >
                                            <button
                                                type="button"
                                                class="mt-1 shrink-0 tabular-nums text-xs text-primary hover:underline"
                                                @click="seekTo(entry.s.start)"
                                            >
                                                {{
                                                    formatDuree(entry.s.start)
                                                }}
                                            </button>
                                            <Textarea
                                                v-model="
                                                    segmentsEdites[entry.i].text
                                                "
                                                rows="2"
                                                class="flex-1 text-sm"
                                            />
                                        </div>
                                    </template>
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :disabled="
                                            sauvegardeTranscriptionForm.processing
                                        "
                                        @click="sauvegarderTranscription"
                                    >
                                        {{
                                            sauvegardeTranscriptionForm.processing
                                                ? 'Sauvegarde…'
                                                : 'Sauvegarder les corrections'
                                        }}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        @click="annulerEditionTranscription"
                                    >
                                        Annuler
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Regénérer Whisper -->
                        <div v-if="peutTranscrire" class="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="transcrireForm.processing"
                                @click="submitTranscrire"
                            >
                                <Mic class="mr-2 h-4 w-4" />
                                Regénérer la transcription
                            </Button>
                            <p
                                v-if="transcriptionModifiee"
                                class="text-xs text-amber-600"
                            >
                                ⚠ Vos corrections manuelles seront écrasées.
                            </p>
                        </div>
                    </template>

                    <!-- Aucune transcription -->
                    <template v-else>
                        <p class="text-sm text-muted-foreground">
                            Aucune transcription disponible pour cette vidéo.
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="peutTranscrire"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="transcrireForm.processing"
                                @click="submitTranscrire"
                            >
                                <Mic class="mr-2 h-4 w-4" />
                                Générer la transcription
                            </Button>

                            <!-- Import même sans transcription existante -->
                            <template v-if="peutModifierTranscription">
                                <input
                                    ref="importFichierRef"
                                    type="file"
                                    accept=".txt,.srt,.vtt"
                                    class="hidden"
                                    @change="onImportFichierChange"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        importTranscriptionForm.processing
                                    "
                                    @click="importFichierRef?.click()"
                                >
                                    <Upload class="mr-2 h-4 w-4" />
                                    Importer une transcription (.txt/.srt/.vtt)
                                </Button>
                            </template>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- Écran de chargement pendant le traitement FFmpeg -->
            <Card
                v-if="
                    traitementStatut === 'en_attente' ||
                    traitementStatut === 'en_cours'
                "
            >
                <CardContent class="flex flex-col items-center gap-4 py-16">
                    <LoaderCircle class="h-10 w-10 animate-spin text-primary" />
                    <div class="text-center">
                        <p class="font-medium">Traitement vidéo en cours…</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            La page se rechargera automatiquement une fois le
                            traitement terminé.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Éditeur de timeline -->
            <Card v-else>
                <CardHeader>
                    <CardTitle>Rogner la vidéo</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-6">
                    <!-- Trim début/fin -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <label class="text-sm font-medium" for="trim-debut">
                                Début (secondes)
                            </label>
                            <input
                                id="trim-debut"
                                v-model.number="editForm.debut"
                                type="range"
                                :min="0"
                                :max="dureeVideo"
                                step="0.1"
                                class="w-full accent-primary"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ formatDuree(editForm.debut) }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-sm font-medium" for="trim-fin">
                                Fin (secondes)
                            </label>
                            <input
                                id="trim-fin"
                                v-model.number="editForm.fin"
                                type="range"
                                :min="0"
                                :max="dureeVideo"
                                step="0.1"
                                class="w-full accent-primary"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ formatDuree(editForm.fin) }}
                            </p>
                        </div>
                    </div>

                    <!-- Barre de timeline visuelle -->
                    <div
                        class="relative h-8 w-full overflow-hidden rounded bg-muted"
                    >
                        <!-- Segment gardé (en bleu) -->
                        <div
                            class="absolute top-0 h-full bg-primary/30"
                            :style="{
                                left: `${(editForm.debut / dureeVideo) * 100}%`,
                                width: `${((editForm.fin - editForm.debut) / dureeVideo) * 100}%`,
                            }"
                        />

                        <!-- Segments supprimés (en rouge) -->
                        <div
                            v-for="(coupe, idx) in editForm.coupes"
                            :key="idx"
                            class="absolute top-0 h-full bg-destructive/30"
                            :style="{
                                left: `${(coupe.debut / dureeVideo) * 100}%`,
                                width: `${((coupe.fin - coupe.debut) / dureeVideo) * 100}%`,
                            }"
                        />

                        <!-- Marqueurs de chapitres -->
                        <div
                            v-for="chap in chapitres"
                            :key="chap.id"
                            class="absolute top-0 h-full w-px bg-foreground/40"
                            :style="{
                                left: `${(chap.debut / dureeVideo) * 100}%`,
                            }"
                            :title="chap.label"
                        />

                        <!-- Curseur de lecture -->
                        <div
                            v-if="dureeVideo > 0"
                            class="absolute top-0 h-full w-0.5 bg-foreground/60 transition-none"
                            :style="{
                                left: `${(currentTime / dureeVideo) * 100}%`,
                            }"
                        />

                        <!-- Marqueurs temps -->
                        <span
                            class="absolute top-1/2 left-1 -translate-y-1/2 text-xs text-muted-foreground"
                            >0:00</span
                        >
                        <span
                            class="absolute top-1/2 right-1 -translate-y-1/2 text-xs text-muted-foreground"
                        >
                            {{ formatDuree(dureeVideo) }}
                        </span>
                    </div>

                    <!-- Coupes internes -->
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium">
                                Segments à supprimer
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="ajouterCoupe"
                            >
                                <Plus class="mr-1.5 h-3.5 w-3.5" />
                                Ajouter une coupe
                            </Button>
                        </div>

                        <div
                            v-if="editForm.coupes.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Aucune coupe interne définie.
                        </div>

                        <div
                            v-for="(coupe, idx) in editForm.coupes"
                            :key="idx"
                            class="flex items-center gap-3 rounded-lg border p-3"
                        >
                            <div class="flex flex-1 gap-4">
                                <div class="grid flex-1 gap-1">
                                    <label
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Début
                                    </label>
                                    <input
                                        v-model.number="coupe.debut"
                                        type="range"
                                        :min="0"
                                        :max="dureeVideo"
                                        step="0.1"
                                        class="w-full accent-destructive"
                                    />
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ formatDuree(coupe.debut) }}</span
                                    >
                                </div>
                                <div class="grid flex-1 gap-1">
                                    <label
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Fin
                                    </label>
                                    <input
                                        v-model.number="coupe.fin"
                                        type="range"
                                        :min="0"
                                        :max="dureeVideo"
                                        step="0.1"
                                        class="w-full accent-destructive"
                                    />
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ formatDuree(coupe.fin) }}</span
                                    >
                                </div>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="text-destructive hover:text-destructive"
                                @click="supprimerCoupe(idx)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Validation erreurs -->
                    <p
                        v-if="editForm.errors.debut || editForm.errors.fin"
                        class="text-sm text-destructive"
                    >
                        {{ editForm.errors.debut || editForm.errors.fin }}
                    </p>

                    <!-- Actions -->
                    <div class="flex items-center justify-between">
                        <!-- Bouton aperçu -->
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="
                                dureeVideo === 0 ||
                                editForm.fin <= editForm.debut ||
                                traitementStatut === 'en_attente' ||
                                traitementStatut === 'en_cours'
                            "
                            @click="
                                isPreviewing
                                    ? stopPreview()
                                    : previewModifications()
                            "
                        >
                            <Square v-if="isPreviewing" class="mr-2 h-4 w-4" />
                            <Play v-else class="mr-2 h-4 w-4" />
                            {{
                                isPreviewing
                                    ? "Arrêter l'aperçu"
                                    : 'Prévisualiser'
                            }}
                        </Button>

                        <!-- Bouton soumettre -->
                        <Button
                            type="button"
                            :disabled="
                                editForm.processing ||
                                traitementStatut === 'en_attente' ||
                                traitementStatut === 'en_cours' ||
                                editForm.fin <= editForm.debut
                            "
                            @click="submitEdit"
                        >
                            {{
                                editForm.processing
                                    ? 'Envoi…'
                                    : 'Appliquer les modifications'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Section jumelage ───────────────────────────────────────── -->
            <Card
                v-if="
                    traitementStatut !== 'en_attente' &&
                    traitementStatut !== 'en_cours'
                "
            >
                <CardHeader>
                    <CardTitle>Jumeler avec une autre vidéo</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-6">
                    <!-- Durée inconnue — le jumelage n'est pas possible -->
                    <div
                        v-if="dureeVideo === 0"
                        class="text-sm text-muted-foreground"
                    >
                        La durée de la vidéo n'est pas encore calculée. Revenez
                        après le traitement pour utiliser le jumelage.
                    </div>

                    <template v-else>
                        <!-- Upload inline d'une nouvelle vidéo -->
                        <div>
                            <Button
                                v-if="!showUploadInline"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-xs"
                                @click="showUploadInline = true"
                            >
                                <Upload class="mr-1.5 h-3.5 w-3.5" />
                                Uploader une nouvelle vidéo pour le jumelage
                            </Button>

                            <!-- Formulaire d'upload inline -->
                            <div
                                v-if="showUploadInline"
                                class="rounded-md border bg-muted/30 p-4"
                            >
                                <p class="mb-3 text-sm font-medium">
                                    Uploader une vidéo dans ce groupe
                                </p>
                                <div class="flex flex-col gap-3">
                                    <div class="grid gap-1">
                                        <label class="text-xs font-medium"
                                            >Titre</label
                                        >
                                        <Input
                                            v-model="uploadInlineForm.titre"
                                            placeholder="Titre de la vidéo"
                                            class="text-sm"
                                        />
                                    </div>
                                    <div class="grid gap-1">
                                        <label class="text-xs font-medium"
                                            >Fichier vidéo (mp4, webm, mov, avi,
                                            mkv — max 2 Go)</label
                                        >
                                        <input
                                            type="file"
                                            accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska"
                                            class="text-sm"
                                            @change="onUploadInlineFichier"
                                        />
                                    </div>
                                    <div class="flex gap-2 pt-1">
                                        <Button
                                            type="button"
                                            size="sm"
                                            :disabled="
                                                uploadInlineForm.processing ||
                                                !uploadInlineForm.titre ||
                                                !uploadInlineForm.fichier
                                            "
                                            @click="submitUploadInline"
                                        >
                                            {{
                                                uploadInlineForm.processing
                                                    ? 'Upload…'
                                                    : 'Uploader'
                                            }}
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            @click="
                                                showUploadInline = false;
                                                uploadInlineForm.reset();
                                            "
                                        >
                                            Annuler
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Aucune autre vidéo disponible dans le groupe -->
                        <div
                            v-if="autresVideos.length === 0 && !showJumelage"
                            class="text-sm text-muted-foreground"
                        >
                            Aucune autre vidéo disponible dans ce groupe.
                            Uploadez-en une ci-dessus pour pouvoir jumeler.
                        </div>

                        <!-- Bouton d'ouverture -->
                        <div
                            v-else-if="
                                autresVideos.length > 0 && !showJumelage
                            "
                        >
                            <Button
                                type="button"
                                variant="outline"
                                @click="showJumelage = true"
                            >
                                <Combine class="mr-2 h-4 w-4" />
                                Jumeler
                            </Button>
                        </div>

                        <!-- Formulaire de jumelage -->
                        <template v-else-if="showJumelage">
                            <!-- Sélection de la vidéo à insérer -->
                            <div class="grid gap-1.5">
                                <label
                                    class="text-sm font-medium"
                                    for="jumelage-video"
                                >
                                    Vidéo à insérer
                                </label>
                                <select
                                    id="jumelage-video"
                                    v-model.number="jumelageVideoId"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                                >
                                    <option :value="null" disabled>
                                        — Choisir une vidéo —
                                    </option>
                                    <option
                                        v-for="v in autresVideos"
                                        :key="v.id"
                                        :value="v.id"
                                    >
                                        {{ v.titre }}
                                        <template v-if="v.duree">
                                            ({{ formatDuree(v.duree) }})
                                        </template>
                                    </option>
                                </select>
                            </div>

                            <!-- Curseur de position -->
                            <div class="grid gap-1.5">
                                <label
                                    class="text-sm font-medium"
                                    for="jumelage-position"
                                >
                                    Position d'insertion
                                </label>
                                <input
                                    id="jumelage-position"
                                    v-model.number="jumelagePosition"
                                    type="range"
                                    :min="0"
                                    :max="dureeVideo"
                                    step="0.1"
                                    class="w-full accent-primary"
                                />
                                <div
                                    class="flex justify-between text-xs text-muted-foreground"
                                >
                                    <span>0:00</span>
                                    <span class="font-medium">{{
                                        formatDuree(jumelagePosition)
                                    }}</span>
                                    <span>{{ formatDuree(dureeVideo) }}</span>
                                </div>
                            </div>

                            <!-- Timeline de prévisualisation proportionnelle -->
                            <div
                                v-if="videoJumelage"
                                class="flex flex-col gap-1.5"
                            >
                                <p class="text-sm font-medium">
                                    Aperçu du montage
                                </p>
                                <div
                                    class="flex h-8 w-full overflow-hidden rounded"
                                >
                                    <div
                                        v-if="proportions.avant > 0"
                                        class="flex items-center justify-center bg-primary/40 text-xs font-medium text-primary"
                                        :style="{
                                            width: `${proportions.avant * 100}%`,
                                        }"
                                        title="Base (avant)"
                                    />
                                    <div
                                        class="flex items-center justify-center bg-amber-400/60 text-xs font-medium text-amber-800"
                                        :style="{
                                            width: `${proportions.insert * 100}%`,
                                        }"
                                        title="Vidéo insérée"
                                    />
                                    <div
                                        v-if="proportions.apres > 0"
                                        class="flex items-center justify-center bg-primary/40 text-xs font-medium text-primary"
                                        :style="{
                                            width: `${proportions.apres * 100}%`,
                                        }"
                                        title="Base (après)"
                                    />
                                </div>
                                <div
                                    class="flex gap-4 text-xs text-muted-foreground"
                                >
                                    <span class="flex items-center gap-1">
                                        <span
                                            class="inline-block h-2.5 w-2.5 rounded-sm bg-primary/40"
                                        />
                                        Base
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span
                                            class="inline-block h-2.5 w-2.5 rounded-sm bg-amber-400/60"
                                        />
                                        {{ videoJumelage.titre }}
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Durée finale estimée :
                                    {{ formatDuree(dureeTotale) }}
                                </p>
                            </div>

                            <!-- Erreurs de validation -->
                            <p
                                v-if="
                                    jumelageForm.errors.video_a_inserer_id ||
                                    jumelageForm.errors.position
                                "
                                class="text-sm text-destructive"
                            >
                                {{
                                    jumelageForm.errors.video_a_inserer_id ||
                                    jumelageForm.errors.position
                                }}
                            </p>

                            <!-- Actions -->
                            <div class="flex items-center gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="annulerJumelage"
                                >
                                    <X class="mr-2 h-4 w-4" />
                                    Annuler
                                </Button>
                                <Button
                                    type="button"
                                    :disabled="
                                        jumelageForm.processing ||
                                        jumelageVideoId === null
                                    "
                                    @click="submitJumelage"
                                >
                                    <Combine class="mr-2 h-4 w-4" />
                                    {{
                                        jumelageForm.processing
                                            ? 'Envoi…'
                                            : 'Confirmer le jumelage'
                                    }}
                                </Button>
                            </div>
                        </template>
                    </template>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
