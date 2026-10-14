<script setup lang="ts">
import axios from 'axios'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import {
    AlertTriangle,
    ArrowLeft,
    BarChart2,
    BookOpen,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Eye,
    FileText,
    GripVertical,
    ImageIcon,
    Pencil,
    Info,
    LayoutDashboard,
    Lock,
    Minus,
    Music,
    Plus,
    Trash2,
    Type,
    Video,
    X,
} from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import InputError from '@/components/shared/InputError.vue'
import MuseeCropModal from '@/components/musee/MuseeCropModal.vue'
import type { CropData } from '@/components/musee/MuseeCropModal.vue'
import MuseeRichEditor from '@/components/musee/MuseeRichEditor.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/AppLayout.vue'
import {
    destroy as destroyBloc,
    reorder as reorderBlocs,
    store as storeBloc,
    update as updateBloc,
    updateColonne as updateBlocColonne,
    updateDimensions as updateBlocDimensions,
} from '@/actions/App/Http/Controllers/MuseeBlocController'
import {
    destroy as destroyPage,
    reorder as reorderPages,
    store as storePage,
    update as updatePage,
} from '@/actions/App/Http/Controllers/MuseePageController'
import {
    destroy as destroyImage,
    store as storeImage,
    update as updateImage,
} from '@/actions/App/Http/Controllers/MuseeImageController'
import { store as storeMedia } from '@/actions/App/Http/Controllers/GroupeMediaController'
import { update as updateMeta, updateEntete } from '@/actions/App/Http/Controllers/MuseeMetaController'
import * as museePublication from '@/actions/App/Http/Controllers/MuseePublicationController'
import {
    destroy as destroySegment,
    store as storeSegment,
} from '@/actions/App/Http/Controllers/MuseeVideoSegmentController'

// ─── Types ────────────────────────────────────────────────────────────────────

type Cours = { id: number; nom_cours: string; code: string; groupe: string }
type Classe = { id: number; code: string; cours_id: number }
type Groupe = { id: number; code: string; classe_id: number }
type TypeProjet = { id: number; nom: string }
type Projet = {
    id: number
    titre_projet: string | null
    verrouille: boolean
    remis_le: string | null
    mode_edition_enseignant: boolean
}
type CategorieMeta = { id: number; nom: string }
type EpoqueMeta = { id: number; nom: string; annee_debut: number | null; annee_fin: number | null }
type Meta = {
    id: number
    slug: string
    intro_texte: string | null
    intro_image_path: string | null
    entete_titre: string | null
    entete_sous_titre: string | null
    entete_overlay_couleur: string | null
    entete_image_position: string
    entete_image_path: string | null
    entete_image_url: string | null
    epoque: CategorieMeta | null
    thematique: CategorieMeta | null
    region: CategorieMeta | null
}
type BlocType = 'texte' | 'image' | 'separateur' | 'carrousel' | 'video' | 'audio'
type ImageAncree = { image_id: number | null; position: 'gauche' | 'droite' } | null
type BlocContenuTexte = { html: string; image_ancree?: ImageAncree }
type BlocContenuImage = { image_id: number | null; legende: string; alt: string }
type CarrouselItem = { image_id: number; legende: string; alt: string }
type BlocContenuCarrousel = { images: CarrouselItem[] }
type BlocContenuVideo = {
    source: 'upload' | 'youtube' | 'vimeo'
    groupe_video_id: number | null
    url_externe: string | null
    legende: string
}
type BlocPisteAudio = {
    groupe_media_id: number | null
    titre: string
}
type BlocContenuAudio = {
    pistes: BlocPisteAudio[]
    legende: string
}
type VideoSegment = {
    id: number
    section_id: number
    debut_secondes: number
    fin_secondes: number
    label: string
}
type GroupeVideo = {
    id: number
    titre: string
    url: string
    thumbnail_url: string | null
    duree: number | null
    transcription_statut: string | null
    transcription: string | null
}
type GroupeAudio = {
    id: number
    nom_original: string
    url: string
    transcription_statut: string | null
    transcription: string | null
}
type GroupeNote = {
    id: number
    contenu: string
    user_id: number
    auteur: { id: number; prenom: string; nom: string }
}
type Bloc = {
    id: number
    type: BlocType
    contenu: BlocContenuTexte | BlocContenuImage | BlocContenuCarrousel | BlocContenuVideo | BlocContenuAudio | null
    ordre: number
    colonne: 1 | 2
    hauteur_px: number | null
    largeur_pct: number | null
    zone_id: string | null
    musee_page_id: number | null
    segments: VideoSegment[]
}
type Contrainte = { type: BlocType; requis: boolean; label: string }
type MuseeLayout = { nb_colonnes: 1 | 2; ratio: '50-50' | '60-40' | '40-60' | '70-30' | '30-70' } | null
type ZoneCanevas = {
    id: string
    type: BlocType
    label: string
    x: number
    y: number
    w: number
    h: number
    ordre_mobile: number
    obligatoire?: boolean
}
type SectionCanevas = { hauteur_vw: number; gap?: number; zones: ZoneCanevas[] }
type Section = {
    id: number
    label: string
    ordre: number
    contraintes: Contrainte[]
    layout: MuseeLayout
    musee_canevas: SectionCanevas | null
    blocs: Bloc[]
    est_obligatoire: boolean
    est_reutilisable: boolean
    min_occurrences: number
    max_occurrences: number | null
}
type MuseePage = {
    id: number
    section_id: number
    titre: string
    ordre: number
}
type MuseeImage = {
    id: number
    url: string
    alt: string
    legende: string
    crop_data?: { x: number; y: number } | null
}

type Props = {
    cours: Cours
    classe: Classe
    groupe: Groupe
    typeProjet: TypeProjet
    projet: Projet
    meta: Meta
    sections: Section[]
    museePages: MuseePage[]
    images: MuseeImage[]
    template: Record<string, string>
    epoques: EpoqueMeta[]
    thematiques: CategorieMeta[]
    regionsAdministratives: CategorieMeta[]
    peutEditer: boolean
    estEnseignant: boolean
    verrouille: boolean
    membres: { id: number; prenom: string; nom: string }[]
    enseignant: { id: number; prenom: string; nom: string }
    videos: GroupeVideo[] | null
    audios: GroupeAudio[] | null
    notes: GroupeNote[] | null
    publication: {
        est_publie: boolean
        statut: 'brouillon' | 'soumis' | 'approuve' | 'rejete'
        publie_le: string | null
        soumis_le: string | null
        raison_rejet: string | null
    }
    stats: { total: number; last7: number; parJour: Record<string, number> } | null
}

const props = defineProps<Props>()

// ─── Navigation entre panneaux ────────────────────────────────────────────────

type Panneau = 'meta' | 'entete' | 'blocs'

const panneauActif = ref<Panneau>('meta')

// Quand il n'y a qu'une seule section, l'auto-sélectionner pour éviter
// une étape de navigation superflue — la publication est un long bloc libre.
const sectionActiveId = ref<number | null>(
    props.sections.length === 1 ? props.sections[0].id : null,
)

// Page active (système multi-pages) — null si on travaille en mode section direct
const pageActiveId = ref<number | null>(null)

/** Page active dérivée de l'ID. */
const pageActive = computed(() =>
    props.museePages.find((p) => p.id === pageActiveId.value) ?? null,
)

/** Pages disponibles pour la section active. */
const pagesDeLaSectionActive = computed(() =>
    props.museePages.filter((p) => p.section_id === sectionActiveId.value),
)

/** Paramètres communs pour les routes MuseePage. */
const pageRouteParams = computed(() => ({
    cours: props.cours.id,
    classe: props.classe.id,
    groupe: props.groupe.id,
    typeProjet: props.typeProjet.id,
}))

/** Nouveau titre de page en cours d'édition (null = mode lecture). */
const titrePagesEdition = ref<Record<number, string>>({})

/** Formulaire d'ajout de nouvelle page. */
const nouvellePagSectionId = ref<number | null>(null)
const nouvellePagTitre = ref('')

/** Sélectionne une page et met à jour la section active correspondante. */
function selectionnerPage(page: MuseePage): void {
    pageActiveId.value = page.id
    sectionActiveId.value = page.section_id
}

/** Crée une nouvelle page pour une section réutilisable. */
function creerPage(sectionId: number, titre: string): void {
    if (!titre.trim()) return
    router.post(
        storePage.url(pageRouteParams.value),
        { section_id: sectionId, titre: titre.trim() },
        {
            preserveScroll: true,
            only: ['museePages'],
            onSuccess: () => {
                nouvellePagSectionId.value = null
                nouvellePagTitre.value = ''
            },
        },
    )
}

/** Renomme une page. */
function renommerPage(page: MuseePage, titre: string): void {
    if (!titre.trim() || titre === page.titre) {
        delete titrePagesEdition.value[page.id]
        return
    }
    router.patch(
        updatePage.url({ ...pageRouteParams.value, museePage: page.id }),
        { titre: titre.trim() },
        {
            preserveScroll: true,
            only: ['museePages'],
            onSuccess: () => { delete titrePagesEdition.value[page.id] },
        },
    )
}

/** Supprime une page après confirmation. */
function supprimerPage(page: MuseePage): void {
    if (!window.confirm(`Supprimer la page «${page.titre}» et tout son contenu ?`)) return
    router.delete(
        destroyPage.url({ ...pageRouteParams.value, museePage: page.id }),
        {
            preserveScroll: true,
            only: ['museePages', 'sections'],
            onSuccess: () => {
                if (pageActiveId.value === page.id) {
                    pageActiveId.value = null
                }
            },
        },
    )
}

const panneaux = [
    { id: 'meta' as Panneau, label: 'Métadonnées', icon: Info },
    { id: 'entete' as Panneau, label: 'En-tête', icon: LayoutDashboard },
    { id: 'blocs' as Panneau, label: 'Contenu (blocs)', icon: BookOpen },
]

function selectPanneau(id: Panneau) {
    panneauActif.value = id
    // Si une seule section, ne pas réinitialiser la sélection
    if (id !== 'blocs') sectionActiveId.value = null
    else if (props.sections.length === 1) sectionActiveId.value = props.sections[0].id
}

// ─── Formulaire métadonnées ───────────────────────────────────────────────────

const formMeta = useForm({
    intro_texte: props.meta.intro_texte ?? '',
    epoque_historique_id: props.meta.epoque?.id?.toString() ?? '',
    thematique_id: props.meta.thematique?.id?.toString() ?? '',
    region_administrative_id: props.meta.region?.id?.toString() ?? '',
})

function sauvegarderMeta() {
    formMeta.patch(
        updateMeta.url({
            cours: props.cours.id,
            classe: props.classe.id,
            groupe: props.groupe.id,
            typeProjet: props.typeProjet.id,
        }),
        { only: ['meta'] },
    )
}

// ─── Formulaire en-tête ───────────────────────────────────────────────────────

const formEntete = useForm({
    entete_titre: props.meta.entete_titre ?? '',
    entete_sous_titre: props.meta.entete_sous_titre ?? '',
    entete_overlay_couleur: (props.meta.entete_overlay_couleur ?? '#000000').substring(0, 7),
    entete_image_position: props.meta.entete_image_position ?? 'center',
    entete_image: null as File | null,
})

const imageEntetePreview = ref<string | null>(props.meta.entete_image_url ?? null)

// Mise à jour de l'aperçu local après un rechargement partiel Inertia.
// Sans ce watch, imageEntetePreview resterait sur le createObjectURL temporaire
// même une fois l'image persistée, et la correspondance avec le stockage serait perdue.
// On observe entete_image_url (fourni par le modèle) plutôt que construire
// manuellement le chemin — compatible S3 et disque local.
watch(
    () => props.meta.entete_image_url,
    (newUrl) => {
        if (newUrl) {
            imageEntetePreview.value = newUrl
        }
    },
)

function onImageEnteteChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    formEntete.entete_image = file
    imageEntetePreview.value = URL.createObjectURL(file)
}

function sauvegarderEntete() {
    formEntete.post(
        updateEntete.url({
            cours: props.cours.id,
            classe: props.classe.id,
            groupe: props.groupe.id,
            typeProjet: props.typeProjet.id,
        }),
        { forceFormData: true, only: ['meta'] },
    )
}

/**
 * Ouvre l'aperçu du musée public.
 *
 * Si le formulaire d'en-tête contient des modifications non sauvegardées
 * (notamment une image sélectionnée), on les sauvegarde d'abord — sinon
 * l'iframe chargerait l'ancienne version depuis la base de données.
 */
function ouvrirApercu() {
    if (!formEntete.isDirty) {
        apercuOuvert.value = true
        return
    }

    formEntete.post(
        updateEntete.url({
            cours: props.cours.id,
            classe: props.classe.id,
            groupe: props.groupe.id,
            typeProjet: props.typeProjet.id,
        }),
        {
            forceFormData: true,
            only: ['meta'],
            onSuccess: () => { apercuOuvert.value = true },
            // On ouvre quand même l'aperçu en cas d'erreur de validation
            // pour ne pas bloquer l'utilisateur.
            onError: () => { apercuOuvert.value = true },
        },
    )
}

// ─── Éditeur de blocs ─────────────────────────────────────────────────────────

const sectionActive = computed(() =>
    props.sections.find((s) => s.id === sectionActiveId.value) ?? null,
)

/**
 * Retourne les contraintes obligatoires dont le type de bloc est absent de la section.
 * Utilisé pour afficher les avertissements à l'étudiant.
 */
function contraintesManquantes(section: Section): Contrainte[] {
    const typesPresents = new Set(section.blocs.map((b) => b.type))
    return (section.contraintes ?? []).filter(
        (c) => c.requis && !typesPresents.has(c.type),
    )
}

/**
 * Indique si la section utilise le mode canevas (zones prédéfinies).
 * Sinon → mode blocs libre (comportement historique).
 */
function hasCanevas(section: Section | null): boolean {
    return !!((section?.musee_canevas?.zones?.length ?? 0) > 0)
}

/**
 * Retourne le bloc associé à une zone du canevas, ou undefined si la zone est vide.
 */
function blocPourZone(zoneId: string): Bloc | undefined {
    return localBlocs.value.find((b) => b.zone_id === zoneId)
}

/**
 * Vide une zone du canevas en supprimant son bloc associé.
 * Appelé depuis l'icône poubelle sur la zone dans l'aperçu visuel.
 */
function viderZone(zone: ZoneCanevas): void {
    const bloc = blocPourZone(zone.id)
    if (!bloc) return
    router.delete(destroyBloc.url({ ...routeParams.value, bloc: bloc.id }), {
        preserveScroll: true,
        only: ['sections'],
        onSuccess: () => {
            if (expandedBlocId.value === bloc.id) {
                expandedBlocId.value = null
                draftContenu.value = null
            }
        },
    })
}

/**
 * Crée un nouveau bloc lié à une zone du canevas.
 * Le type est imposé par la zone ; l'étudiant n'a pas à le choisir.
 */
function ajouterBlocZone(zone: ZoneCanevas): void {
    const data: Record<string, unknown> = { type: zone.type, zone_id: zone.id }
    if (pageActiveId.value !== null) data.musee_page_id = pageActiveId.value
    router.post(
        storeBloc.url(routeParams.value),
        data,
        { preserveScroll: true, only: ['sections'] },
    )
}

// Copie locale réactive pour le drag-and-drop — synchronisée après les réponses serveur
const localBlocs = ref<Bloc[]>([])

watch(
    [sectionActive, pageActiveId],
    ([sec, pageId]) => {
        if (!sec) {
            localBlocs.value = []
            return
        }
        if (pageId !== null) {
            // Mode page : afficher uniquement les blocs de la page active
            localBlocs.value = sec.blocs.filter((b) => b.musee_page_id === pageId).map((b) => ({ ...b }))
        } else {
            // Mode section directe (pas de pages pour cette section) : blocs sans page_id
            localBlocs.value = sec.blocs.filter((b) => b.musee_page_id === null).map((b) => ({ ...b }))
        }
    },
    { immediate: true, deep: true },
)

// Bloc actuellement ouvert pour édition
const expandedBlocId = ref<number | null>(null)
const draftContenu = ref<BlocContenuTexte | BlocContenuImage | BlocContenuCarrousel | BlocContenuVideo | BlocContenuAudio | null>(null)

// Formulaire d'ajout de segment vidéo
const segmentFormBlocId = ref<number | null>(null)
const segmentDraft = ref({ section_id: '', debut_secondes: '', fin_secondes: '', label: '' })

function editerBloc(bloc: Bloc) {
    if (expandedBlocId.value === bloc.id) {
        expandedBlocId.value = null
        draftContenu.value = null
        return
    }
    expandedBlocId.value = bloc.id
    const contenu = bloc.contenu ? JSON.parse(JSON.stringify(bloc.contenu)) : null

    // Convertir l'ancien format audio mono-piste (groupe_media_id racine) vers le nouveau format pistes[]
    if (bloc.type === 'audio' && contenu && !('pistes' in contenu)) {
        draftContenu.value = {
            pistes: [{ groupe_media_id: (contenu as { groupe_media_id: number | null }).groupe_media_id ?? null, titre: (contenu as { titre: string }).titre ?? '' }],
            legende: (contenu as { legende: string }).legende ?? '',
        } as BlocContenuAudio
    } else {
        draftContenu.value = contenu
    }
}

const routeParams = computed(() => ({
    cours: props.cours.id,
    classe: props.classe.id,
    groupe: props.groupe.id,
    typeProjet: props.typeProjet.id,
    section: sectionActiveId.value!,
}))

const imageRouteParams = computed(() => ({
    cours: props.cours.id,
    classe: props.classe.id,
    groupe: props.groupe.id,
    typeProjet: props.typeProjet.id,
}))

/** URL de la page de correction (enseignant). */
const correctionUrl = computed(() =>
    `/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${props.groupe.id}/projets/${props.typeProjet.id}/musee/correction`,
)

/** Paramètres communs pour toutes les routes musee-publication de ce projet. */
const publiParams = computed(() => ({
    cours: props.cours.id,
    classe: props.classe.id,
    groupe: props.groupe.id,
    typeProjet: props.typeProjet.id,
}))

/** Étudiant soumet le musée pour approbation. */
function soumettre() {
    router.post(museePublication.soumettre.url(publiParams.value), {}, { preserveScroll: true, only: ['publication'] })
}

/** Étudiant annule la soumission pour reprendre l'édition. */
function annulerSoumission() {
    router.post(museePublication.annulerSoumission.url(publiParams.value), {}, { preserveScroll: true, only: ['publication'] })
}

/** Enseignant bascule la visibilité publique (masquer / remettre en ligne). */
function togglePublication() {
    router.post(museePublication.toggle.url(publiParams.value), {}, { preserveScroll: true, only: ['publication'] })
}

/** Enseignant approuve la soumission → publication immédiate. */
function approuver() {
    router.post(museePublication.approuver.url(publiParams.value), {}, { preserveScroll: true, only: ['publication'] })
}

/** Formulaire de rejet. */
const rejetOuvert = ref(false)
const raisonRejet = ref('')

/** Enseignant rejette la soumission avec un commentaire. */
function rejeter() {
    if (! raisonRejet.value.trim()) return
    router.post(
        museePublication.rejeter.url(publiParams.value),
        { raison_rejet: raisonRejet.value },
        {
            preserveScroll: true,
            only: ['publication'],
            onSuccess: () => {
                rejetOuvert.value = false
                raisonRejet.value = ''
            },
        },
    )
}

/** Convertit des secondes entières en mm:ss pour l'affichage dans l'éditeur. */
function formatTemps(secondes: number): string {
    const m = Math.floor(secondes / 60)
    const s = secondes % 60
    return `${m}:${s.toString().padStart(2, '0')}`
}

function ajouterSegment(bloc: Bloc) {
    router.post(
        storeSegment.url({ ...routeParams.value, bloc: bloc.id }),
        {
            section_id: Number(segmentDraft.value.section_id),
            debut_secondes: Number(segmentDraft.value.debut_secondes),
            fin_secondes: Number(segmentDraft.value.fin_secondes),
            label: segmentDraft.value.label,
        },
        {
            preserveScroll: true,
            only: ['sections'],
            onSuccess: () => {
                segmentDraft.value = { section_id: '', debut_secondes: '', fin_secondes: '', label: '' }
                segmentFormBlocId.value = null
            },
        },
    )
}

function supprimerSegment(segmentId: number, blocId: number) {
    if (!window.confirm('Supprimer ce segment ?')) return
    router.delete(
        destroySegment.url({ ...routeParams.value, bloc: blocId, segment: segmentId }),
        { preserveScroll: true, only: ['sections'] },
    )
}

function ajouterBloc(type: BlocType) {
    const data: Record<string, unknown> = { type }
    if (pageActiveId.value !== null) data.musee_page_id = pageActiveId.value
    router.post(storeBloc.url(routeParams.value), data, { preserveScroll: true, only: ['sections'] })
}

/**
 * Crée un bloc depuis la palette du groupe, en pré-remplissant l'asset sélectionné.
 * Pour les vidéos : passe groupe_video_id. Pour les audios : passe groupe_media_id.
 */
function ajouterBlocAvecMedia(type: 'video' | 'audio', params: Record<string, number>) {
    router.post(storeBloc.url(routeParams.value), { type, ...params }, { preserveScroll: true, only: ['sections'] })
}

/**
 * Convertit une transcription plain-text en HTML minimal pour le TipTap.
 * Chaque paragraphe séparé par une ligne vide devient un <p>.
 */
function transcriptionVersHtml(texte: string): string {
    return texte
        .split(/\n{2,}/)
        .map((p) => p.replace(/\n/g, ' ').trim())
        .filter((p) => p.length > 0)
        .map((p) => `<p>${p}</p>`)
        .join('')
}

/**
 * Crée un bloc texte pré-rempli avec la transcription d'un média.
 */
function insererTranscription(transcription: string) {
    router.post(
        storeBloc.url(routeParams.value),
        { type: 'texte', html: transcriptionVersHtml(transcription) },
        { preserveScroll: true, only: ['sections'] },
    )
}

/**
 * Crée un bloc image pré-rempli depuis la bibliothèque d'images du projet.
 */
function insererImageCommeBloc(imageId: number) {
    const img = imagePourBloc(imageId)
    router.post(
        storeBloc.url(routeParams.value),
        {
            type: 'image',
            image_id: imageId,
            alt: img?.alt ?? '',
            legende: img?.legende ?? '',
        },
        { preserveScroll: true, only: ['sections'] },
    )
}

// ─── Redimensionnement des blocs ──────────────────────────────────────────────

/** Types de blocs qui supportent le redimensionnement en hauteur. */
const TYPES_REDIMENSIONNABLES: BlocType[] = ['image', 'video', 'audio', 'carrousel']

const redimensionnementEnCours = ref<{ blocId: number; debutY: number; hauteurDepart: number } | null>(null)

/**
 * Démarre le suivi du redimensionnement au mousedown sur le handle du bloc.
 */
function demarrerRedimensionnement(event: MouseEvent, bloc: Bloc): void {
    event.preventDefault()
    redimensionnementEnCours.value = {
        blocId: bloc.id,
        debutY: event.clientY,
        hauteurDepart: bloc.hauteur_px ?? 280,
    }

    const onMouseMove = (e: MouseEvent) => {
        if (!redimensionnementEnCours.value) return
        const delta = e.clientY - redimensionnementEnCours.value.debutY
        const nouvelleHauteur = Math.max(80, Math.min(1200, redimensionnementEnCours.value.hauteurDepart + delta))
        const b = localBlocs.value.find((b) => b.id === redimensionnementEnCours.value!.blocId)
        if (b) b.hauteur_px = Math.round(nouvelleHauteur)
    }

    const onMouseUp = () => {
        document.removeEventListener('mousemove', onMouseMove)
        document.removeEventListener('mouseup', onMouseUp)
        if (!redimensionnementEnCours.value) return
        const b = localBlocs.value.find((b) => b.id === redimensionnementEnCours.value!.blocId)
        if (b) sauvegarderDimensions(b)
        redimensionnementEnCours.value = null
    }

    document.addEventListener('mousemove', onMouseMove)
    document.addEventListener('mouseup', onMouseUp)
}

/**
 * Enregistre les dimensions personnalisées du bloc sur le serveur.
 */
function sauvegarderDimensions(bloc: Bloc): void {
    router.patch(
        updateBlocDimensions.url({ ...routeParams.value, bloc: bloc.id }),
        { hauteur_px: bloc.hauteur_px, largeur_pct: bloc.largeur_pct },
        { preserveScroll: true, preserveState: true, only: ['sections'] },
    )
}

/**
 * Remet les dimensions du bloc à auto (null).
 */
function reinitialiserDimensions(bloc: Bloc): void {
    const b = localBlocs.value.find((b) => b.id === bloc.id)
    if (b) { b.hauteur_px = null; b.largeur_pct = null }
    router.patch(
        updateBlocDimensions.url({ ...routeParams.value, bloc: bloc.id }),
        { hauteur_px: null, largeur_pct: null },
        { preserveScroll: true, preserveState: true, only: ['sections'] },
    )
}

/**
 * Bascule le bloc entre la colonne 1 et la colonne 2 dans une section 2 colonnes.
 */
function changerColonne(bloc: Bloc, section: Section) {
    if (!section.layout || section.layout.nb_colonnes < 2) return
    const nouvelleColonne: 1 | 2 = bloc.colonne === 1 ? 2 : 1
    router.patch(
        updateBlocColonne.url({
            ...routeParams.value,
            section: section.id,
            bloc: bloc.id,
        }),
        { colonne: nouvelleColonne },
        { preserveScroll: true, only: ['sections'] },
    )
}

/** Onglet actif dans la palette de médias du groupe. */
const paletteOnglet = ref<'videos' | 'audios' | 'images'>('videos')

/** Palette ouverte ou fermée dans l'éditeur de section. */
const paletteOuverte = ref(false)

/** Modal d'aperçu du musée public ouvert ou fermé. */
const apercuOuvert = ref(false)

function sauvegarderBloc(bloc: Bloc) {
    const data: Record<string, unknown> = {}

    if (bloc.type === 'texte') {
        const c = draftContenu.value as BlocContenuTexte
        data.html = c.html
        // Transmettre image_ancree seulement si elle a été définie dans le brouillon
        if ('image_ancree' in c) {
            data.image_ancree = c.image_ancree ?? null
        }
    } else if (bloc.type === 'image') {
        const c = draftContenu.value as BlocContenuImage
        data.image_id = c.image_id
        data.legende = c.legende
        data.alt = c.alt
    } else if (bloc.type === 'carrousel') {
        const c = draftContenu.value as BlocContenuCarrousel
        data.images = c.images
    } else if (bloc.type === 'video') {
        const c = draftContenu.value as BlocContenuVideo
        data.source = c.source
        data.groupe_video_id = c.groupe_video_id
        data.url_externe = c.url_externe
        data.legende = c.legende
    } else if (bloc.type === 'audio') {
        const c = draftContenu.value as BlocContenuAudio
        data.pistes = c.pistes
        data.legende = c.legende
    }

    router.patch(
        updateBloc.url({ ...routeParams.value, bloc: bloc.id }),
        data,
        {
            preserveScroll: true,
            only: ['sections'],
            onSuccess: () => {
                expandedBlocId.value = null
                draftContenu.value = null
            },
        },
    )
}

function supprimerBloc(bloc: Bloc) {
    if (!window.confirm('Supprimer ce bloc ?')) return
    router.delete(destroyBloc.url({ ...routeParams.value, bloc: bloc.id }), {
        preserveScroll: true,
        only: ['sections'],
        onSuccess: () => {
            if (expandedBlocId.value === bloc.id) {
                expandedBlocId.value = null
                draftContenu.value = null
            }
        },
    })
}

function onBlocDragEnd() {
    router.patch(
        reorderBlocs.url(routeParams.value),
        { ordre: localBlocs.value.map((b) => b.id) },
        { preserveScroll: true, preserveState: true, only: ['sections'] },
    )
}

// ─── Bibliothèque d'images ────────────────────────────────────────────────────

const localImages = ref<MuseeImage[]>([...props.images])
const isUploadingImage = ref(false)
const erreurUpload = ref<string | null>(null)
const cropModalData = ref<{ url: string; id: number } | null>(null)
const isSavingCrop = ref(false)

watch(
    () => props.images,
    (imgs) => {
        localImages.value = [...imgs]
    },
)

function imagePourBloc(imageId: number | null): MuseeImage | undefined {
    return localImages.value.find((img) => img.id === imageId)
}

function cropStyle(img: MuseeImage | undefined): string {
    if (!img?.crop_data) return 'object-fit: cover'
    return `object-fit: cover; object-position: ${img.crop_data.x}% ${img.crop_data.y}%`
}

// Sélectionner une image dans un bloc image standard
function selectionnerImage(imageId: number) {
    if (!draftContenu.value) return
    const img = imagePourBloc(imageId)
    ;(draftContenu.value as BlocContenuImage).image_id = imageId
    if (img && !(draftContenu.value as BlocContenuImage).alt) {
        ;(draftContenu.value as BlocContenuImage).alt = img.alt
    }
    if (img && !(draftContenu.value as BlocContenuImage).legende) {
        ;(draftContenu.value as BlocContenuImage).legende = img.legende
    }
}

// Ajouter une image au carrousel en cours d'édition
function ajouterImageCarrousel(imageId: number) {
    if (!draftContenu.value) return
    const c = draftContenu.value as BlocContenuCarrousel
    if (c.images.some((i) => i.image_id === imageId)) return // déjà présente
    const img = imagePourBloc(imageId)
    c.images.push({
        image_id: imageId,
        alt: img?.alt ?? '',
        legende: img?.legende ?? '',
    })
}

function retirerImageCarrousel(index: number) {
    if (!draftContenu.value) return
    ;(draftContenu.value as BlocContenuCarrousel).images.splice(index, 1)
}

// ─── Pistes audio ─────────────────────────────────────────────────────────────

/**
 * Ajoute une piste vide à la liste des pistes du bloc audio en édition.
 */
function ajouterPisteAudio(): void {
    if (!draftContenu.value) return
    ;(draftContenu.value as BlocContenuAudio).pistes.push({ groupe_media_id: null, titre: '' })
}

/**
 * Supprime la piste à l'index donné du bloc audio en édition.
 */
function retirerPisteAudio(idx: number): void {
    if (!draftContenu.value) return
    ;(draftContenu.value as BlocContenuAudio).pistes.splice(idx, 1)
}

// ─── Upload audio depuis l'éditeur ────────────────────────────────────────────

const audioFileInput = ref<HTMLInputElement | null>(null)
const audioUploadEnCours = ref(false)
const audioUploadErreur = ref<string | null>(null)

/**
 * Ouvre le sélecteur de fichier audio caché.
 */
function ouvrirSelecteurAudio(): void {
    audioFileInput.value?.click()
}

/**
 * Uploade un fichier audio vers le groupe, puis rafraîchit la liste des audios.
 */
function handleAudioChange(e: Event): void {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return

    audioUploadErreur.value = null
    audioUploadEnCours.value = true

    const formData = new FormData()
    formData.append('fichier', file)

    // Réinitialiser l'input pour permettre de re-sélectionner le même fichier
    ;(e.target as HTMLInputElement).value = ''

    router.post(
        storeMedia.url({ cours: props.cours, classe: props.classe, groupe: props.groupe }),
        formData,
        {
            preserveScroll: true,
            only: ['audios'],
            onSuccess: () => { audioUploadEnCours.value = false },
            onError: (errors) => {
                audioUploadEnCours.value = false
                audioUploadErreur.value = errors.fichier ?? 'Erreur lors de l\'upload.'
            },
        },
    )
}

// Sélectionner une image pour l'image ancrée d'un bloc texte
function selectionnerImageAncree(imageId: number) {
    if (!draftContenu.value) return
    const c = draftContenu.value as BlocContenuTexte
    if (!c.image_ancree) {
        c.image_ancree = { image_id: imageId, position: 'droite' }
    } else {
        c.image_ancree.image_id = imageId
    }
}

async function uploaderImage(event: Event) {
    erreurUpload.value = null
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return

    isUploadingImage.value = true
    const formData = new FormData()
    formData.append('image', file)

    try {
        const { data: img } = await axios.post<MuseeImage>(
            storeImage.url(imageRouteParams.value),
            formData,
        )
        localImages.value = [...localImages.value, img]
        // Ouvrir le modal de crop avant de sélectionner l'image
        cropModalData.value = { url: img.url, id: img.id }
    } catch (err: unknown) {
        if (axios.isAxiosError(err)) {
            erreurUpload.value = `Erreur ${err.response?.status ?? '?'} lors de l'upload`
            console.error('[musee-upload]', err.response?.status, err.response?.data)
        } else {
            erreurUpload.value = 'Erreur réseau lors de l\'upload'
        }
    } finally {
        isUploadingImage.value = false
        ;(event.target as HTMLInputElement).value = ''
    }
}

async function confirmerCrop(cropData: CropData) {
    if (!cropModalData.value) return
    isSavingCrop.value = true

    const imageId = cropModalData.value.id

    try {
        const { data: updated } = await axios.patch<MuseeImage>(
            updateImage.url({ ...imageRouteParams.value, museeImage: imageId }),
            { crop_data: cropData },
        )
        localImages.value = localImages.value.map((img) =>
            img.id === updated.id ? updated : img,
        )
    } catch (err: unknown) {
        console.error('[musee-crop]', err)
    } finally {
        isSavingCrop.value = false
        // Sélectionner l'image (dans le bloc en cours d'édition) après le crop
        if (draftContenu.value) {
            const type = localBlocs.value.find((b) => b.id === expandedBlocId.value)?.type
            if (type === 'image') {
                selectionnerImage(imageId)
            } else if (type === 'carrousel') {
                ajouterImageCarrousel(imageId)
            }
        }
        cropModalData.value = null
    }
}

function annulerCrop() {
    // L'image est déjà uploadée — on la sélectionne sans crop
    if (cropModalData.value && draftContenu.value) {
        const type = localBlocs.value.find((b) => b.id === expandedBlocId.value)?.type
        if (type === 'image') {
            selectionnerImage(cropModalData.value.id)
        } else if (type === 'carrousel') {
            ajouterImageCarrousel(cropModalData.value.id)
        }
    }
    cropModalData.value = null
}

// ─── Indicateurs de complétion ────────────────────────────────────────────────

const metaComplete = computed(
    () => formMeta.intro_texte.trim() !== '' && !!formMeta.epoque_historique_id,
)

const enteteComplete = computed(() => formEntete.entete_titre.trim() !== '')

const nbBlocsTotal = computed(() =>
    props.sections.reduce((acc, s) => acc + s.blocs.length, 0),
)

// ─── CSS variables du template ────────────────────────────────────────────────

const templateStyle = computed(() => props.template)
</script>

<template>
    <AppLayout>
        <Head :title="typeProjet.nom" />

        <!-- Modal de crop (affiché par-dessus tout) -->
        <MuseeCropModal
            v-if="cropModalData"
            :image-url="cropModalData.url"
            :image-id="cropModalData.id"
            @confirmed="confirmerCrop"
            @cancelled="annulerCrop"
        />

        <!-- Bannière verrouillé -->
        <div
            v-if="verrouille"
            class="flex items-center gap-2 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            <Lock class="h-3.5 w-3.5" />
            Ce projet est verrouillé — la modification est désactivée.
        </div>

        <div class="flex h-[calc(100vh-3.5rem)] overflow-hidden">
            <!-- ─── Panneau gauche — navigation ────────────────────────────────── -->
            <aside
                class="flex w-60 shrink-0 flex-col border-r bg-background"
                :style="templateStyle"
            >
                <!-- En-tête sidebar -->
                <div class="border-b px-4 py-3">
                    <Link
                        :href="`/cours/${cours.id}/classes/${classe.id}/groupes/${groupe.id}/projets`"
                        class="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft class="h-3 w-3" />
                        Retour aux projets
                    </Link>
                    <p class="text-xs font-semibold text-foreground">{{ typeProjet.nom }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Groupe {{ groupe.code }}
                    </p>
                </div>

                <!-- Navigation des panneaux -->
                <nav class="flex-1 overflow-y-auto px-2 py-3">
                    <ul class="space-y-0.5">
                        <li v-for="panneau in panneaux" :key="panneau.id">
                            <button
                                type="button"
                                :class="[
                                    'flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors',
                                    panneauActif === panneau.id
                                        ? 'bg-primary/10 font-medium text-primary'
                                        : 'text-foreground hover:bg-muted',
                                ]"
                                @click="selectPanneau(panneau.id)"
                            >
                                <component :is="panneau.icon" class="h-4 w-4 shrink-0" />
                                <span class="flex-1">{{ panneau.label }}</span>
                                <!-- Indicateurs de complétion -->
                                <CheckCircle2
                                    v-if="panneau.id === 'meta' && metaComplete"
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                <CheckCircle2
                                    v-else-if="panneau.id === 'entete' && enteteComplete"
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                <span
                                    v-else-if="panneau.id === 'blocs' && nbBlocsTotal > 0"
                                    class="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary"
                                >{{ nbBlocsTotal }}</span>
                            </button>

                            <!-- Sous-nav sections/pages (visible quand blocs est actif) -->
                            <template v-if="panneau.id === 'blocs' && panneauActif === 'blocs'">
                                <ul class="mt-0.5 space-y-0.5 pl-5">
                                    <li
                                        v-if="sections.length === 0"
                                        class="px-3 py-1 text-xs italic text-muted-foreground"
                                    >
                                        Aucune section définie
                                    </li>
                                    <template v-for="section in sections" :key="section.id">
                                        <!-- Section avec pages (multi-pages) -->
                                        <template v-if="museePages.some(p => p.section_id === section.id)">
                                            <!-- Label de la section (non cliquable directement) -->
                                            <li class="px-3 pt-1.5 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                {{ section.label }}
                                                <span v-if="section.est_obligatoire" class="ml-1 text-destructive" title="Section obligatoire">*</span>
                                            </li>
                                            <!-- Pages de la section -->
                                            <li
                                                v-for="page in museePages.filter(p => p.section_id === section.id)"
                                                :key="page.id"
                                            >
                                                <!-- Mode édition du titre -->
                                                <div
                                                    v-if="titrePagesEdition[page.id] !== undefined"
                                                    class="flex items-center gap-1 px-3 py-1"
                                                >
                                                    <input
                                                        v-model="titrePagesEdition[page.id]"
                                                        type="text"
                                                        class="min-w-0 flex-1 rounded border border-border bg-background px-1.5 py-0.5 text-xs"
                                                        maxlength="255"
                                                        autofocus
                                                        @keydown.enter="renommerPage(page, titrePagesEdition[page.id])"
                                                        @keydown.escape="delete titrePagesEdition[page.id]"
                                                        @blur="renommerPage(page, titrePagesEdition[page.id])"
                                                    />
                                                </div>
                                                <!-- Mode lecture -->
                                                <div
                                                    v-else
                                                    :class="[
                                                        'group flex w-full items-center gap-1 rounded px-3 py-1.5 text-left text-xs transition-colors',
                                                        pageActiveId === page.id
                                                            ? 'bg-primary/10 font-medium text-primary'
                                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                                    ]"
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex-1 truncate text-left"
                                                        @click="selectionnerPage(page)"
                                                    >
                                                        {{ page.titre }}
                                                    </button>
                                                    <template v-if="peutEditer">
                                                        <button
                                                            type="button"
                                                            class="shrink-0 opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-foreground"
                                                            title="Renommer"
                                                            @click.stop="titrePagesEdition[page.id] = page.titre"
                                                        >
                                                            <FileText class="h-3 w-3" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="shrink-0 opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive"
                                                            title="Supprimer"
                                                            @click.stop="supprimerPage(page)"
                                                        >
                                                            <X class="h-3 w-3" />
                                                        </button>
                                                    </template>
                                                </div>
                                            </li>
                                            <!-- Formulaire d'ajout d'une nouvelle page -->
                                            <li v-if="peutEditer && section.est_reutilisable && (section.max_occurrences === null || museePages.filter(p => p.section_id === section.id).length < section.max_occurrences)">
                                                <div v-if="nouvellePagSectionId === section.id" class="flex items-center gap-1 px-3 py-1">
                                                    <input
                                                        v-model="nouvellePagTitre"
                                                        type="text"
                                                        class="min-w-0 flex-1 rounded border border-border bg-background px-1.5 py-0.5 text-xs"
                                                        placeholder="Titre de la page…"
                                                        maxlength="255"
                                                        autofocus
                                                        @keydown.enter="creerPage(section.id, nouvellePagTitre)"
                                                        @keydown.escape="nouvellePagSectionId = null; nouvellePagTitre = ''"
                                                    />
                                                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="nouvellePagSectionId = null; nouvellePagTitre = ''">
                                                        <X class="h-3 w-3" />
                                                    </button>
                                                </div>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="flex w-full items-center gap-1 px-3 py-1 text-xs text-primary/70 hover:text-primary"
                                                    @click="nouvellePagSectionId = section.id; nouvellePagTitre = ''"
                                                >
                                                    <Plus class="h-3 w-3" />
                                                    Ajouter une page
                                                </button>
                                            </li>
                                        </template>

                                        <!-- Section sans pages (mode direct / hérité) -->
                                        <template v-else>
                                            <!-- Bouton de création de la première page si section configurée -->
                                            <li v-if="peutEditer && (section.est_reutilisable || section.musee_canevas)">
                                                <div class="px-3 pt-1.5 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                    {{ section.label }}
                                                    <span v-if="section.est_obligatoire" class="ml-1 text-destructive">*</span>
                                                </div>
                                                <div v-if="nouvellePagSectionId === section.id" class="flex items-center gap-1 px-3 py-1">
                                                    <input
                                                        v-model="nouvellePagTitre"
                                                        type="text"
                                                        class="min-w-0 flex-1 rounded border border-border bg-background px-1.5 py-0.5 text-xs"
                                                        placeholder="Titre de la page…"
                                                        maxlength="255"
                                                        autofocus
                                                        @keydown.enter="creerPage(section.id, nouvellePagTitre)"
                                                        @keydown.escape="nouvellePagSectionId = null; nouvellePagTitre = ''"
                                                    />
                                                    <button type="button" class="text-muted-foreground" @click="nouvellePagSectionId = null; nouvellePagTitre = ''">
                                                        <X class="h-3 w-3" />
                                                    </button>
                                                </div>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="flex w-full items-center gap-1 px-3 py-1 text-xs text-primary/70 hover:text-primary"
                                                    @click="nouvellePagSectionId = section.id; nouvellePagTitre = section.label"
                                                >
                                                    <Plus class="h-3 w-3" />
                                                    Créer cette page
                                                </button>
                                            </li>
                                            <!-- Section classique (mode blocs libre) -->
                                            <li v-else>
                                                <button
                                                    type="button"
                                                    :class="[
                                                        'flex w-full items-center gap-2 rounded px-3 py-1.5 text-left text-xs transition-colors',
                                                        sectionActiveId === section.id && pageActiveId === null
                                                            ? 'bg-primary/10 font-medium text-primary'
                                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                                    ]"
                                                    @click="sectionActiveId = section.id; pageActiveId = null"
                                                >
                                                    <span class="flex-1 truncate">{{ section.label }}</span>
                                                    <span class="shrink-0 text-[10px]">
                                                        {{ section.blocs.length }}
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                    </template>
                                </ul>
                            </template>
                        </li>
                    </ul>
                </nav>

                <!-- Bouton Aperçu -->
                <div class="border-t px-2 py-2">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors text-foreground hover:bg-muted"
                        @click="ouvrirApercu"
                    >
                        <Eye class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <span class="flex-1">Aperçu du musée</span>
                    </button>
                </div>

                <!-- Membres -->
                <div class="border-t px-4 py-3">
                    <p class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Membres
                    </p>
                    <ul class="space-y-0.5">
                        <li
                            v-for="membre in membres"
                            :key="membre.id"
                            class="text-xs text-muted-foreground"
                        >
                            {{ membre.prenom }} {{ membre.nom }}
                        </li>
                    </ul>
                </div>

                <!-- Publication — étudiant -->
                <div
                    v-if="!estEnseignant && !verrouille"
                    class="border-t px-4 py-3 space-y-2"
                >
                    <p class="mb-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Publication
                    </p>

                    <!-- Badge statut -->
                    <span
                        :class="[
                            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium',
                            publication.statut === 'brouillon' && 'bg-muted text-muted-foreground',
                            publication.statut === 'soumis' && 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                            publication.statut === 'approuve' && 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                            publication.statut === 'rejete' && 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        ]"
                    >
                        {{
                            publication.statut === 'brouillon' ? 'Brouillon'
                            : publication.statut === 'soumis' ? 'En attente'
                            : publication.statut === 'approuve' ? 'Approuvé ✓'
                            : 'Rejeté'
                        }}
                    </span>

                    <!-- Raison du rejet -->
                    <div
                        v-if="publication.statut === 'rejete' && publication.raison_rejet"
                        class="rounded border border-red-200 bg-red-50 px-2.5 py-2 dark:border-red-800 dark:bg-red-950"
                    >
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">
                            Commentaire de l'enseignant
                        </p>
                        <p class="mt-0.5 text-xs text-red-700 dark:text-red-300">
                            {{ publication.raison_rejet }}
                        </p>
                    </div>

                    <!-- Brouillon / Rejeté → Soumettre -->
                    <button
                        v-if="publication.statut === 'brouillon' || publication.statut === 'rejete'"
                        type="button"
                        class="flex w-full items-center justify-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                        @click="soumettre"
                    >
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        Soumettre pour approbation
                    </button>

                    <!-- Soumis → En attente + Annuler -->
                    <template v-else-if="publication.statut === 'soumis'">
                        <p class="text-xs text-muted-foreground">
                            En attente d'approbation par l'enseignant.
                        </p>
                        <button
                            type="button"
                            class="flex w-full items-center gap-1.5 rounded-md px-2 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="annulerSoumission"
                        >
                            <Minus class="h-3.5 w-3.5 shrink-0" />
                            Annuler la soumission
                        </button>
                    </template>

                    <!-- Approuvé -->
                    <p
                        v-else-if="publication.statut === 'approuve'"
                        class="text-xs text-emerald-700 dark:text-emerald-400"
                    >
                        Musée publié dans la galerie.
                    </p>
                </div>

                <!-- Contrôles enseignant -->
                <div
                    v-if="estEnseignant"
                    class="border-t px-4 py-3 space-y-1"
                >
                    <p class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Enseignant
                    </p>

                    <Link
                        :href="correctionUrl"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        <BookOpen class="h-3.5 w-3.5 shrink-0" />
                        Page de correction
                    </Link>

                    <!-- Soumis → Approuver / Rejeter -->
                    <template v-if="publication.statut === 'soumis'">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md bg-emerald-600 px-2 py-1.5 text-left text-xs font-medium text-white hover:bg-emerald-700"
                            @click="approuver"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5 shrink-0" />
                            Approuver et publier
                        </button>

                        <button
                            v-if="!rejetOuvert"
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950"
                            @click="rejetOuvert = true"
                        >
                            <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                            Rejeter…
                        </button>

                        <!-- Formulaire rejet inline -->
                        <div
                            v-if="rejetOuvert"
                            class="rounded-md border border-red-200 bg-red-50 p-2.5 space-y-2 dark:border-red-800 dark:bg-red-950"
                        >
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">
                                Motif du rejet
                            </p>
                            <Textarea
                                v-model="raisonRejet"
                                rows="3"
                                placeholder="Expliquez aux étudiants ce qui doit être corrigé…"
                                class="text-xs"
                            />
                            <div class="flex gap-1.5">
                                <button
                                    type="button"
                                    :disabled="!raisonRejet.trim()"
                                    class="flex-1 rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                    @click="rejeter"
                                >
                                    Confirmer
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted"
                                    @click="rejetOuvert = false; raisonRejet = ''"
                                >
                                    Annuler
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Pas soumis → Toggle publication + badge statut -->
                    <template v-else>
                        <!-- Badge statut (info) -->
                        <span
                            v-if="publication.statut !== 'brouillon'"
                            :class="[
                                'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium',
                                publication.statut === 'approuve' && 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                publication.statut === 'rejete' && 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                            ]"
                        >
                            {{ publication.statut === 'approuve' ? 'Approuvé' : 'Rejeté' }}
                        </span>

                        <button
                            type="button"
                            :class="[
                                'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors',
                                publication.est_publie
                                    ? 'text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                            @click="togglePublication"
                        >
                            <CheckCircle2
                                v-if="publication.est_publie"
                                class="h-3.5 w-3.5 shrink-0"
                            />
                            <Lock
                                v-else
                                class="h-3.5 w-3.5 shrink-0"
                            />
                            {{ publication.est_publie ? 'Publié dans la galerie' : 'Publier dans la galerie' }}
                        </button>
                    </template>

                    <!-- Mini stats de vues -->
                    <div
                        v-if="stats != null"
                        class="mt-2 rounded-md border px-3 py-2"
                    >
                        <p class="mb-1.5 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            <BarChart2 class="h-3 w-3" />
                            Statistiques de vues
                        </p>
                        <div class="flex items-baseline justify-between text-xs">
                            <span class="text-muted-foreground">Total</span>
                            <span class="font-semibold tabular-nums">{{ stats.total }}</span>
                        </div>
                        <div class="flex items-baseline justify-between text-xs">
                            <span class="text-muted-foreground">7 derniers jours</span>
                            <span class="font-semibold tabular-nums">{{ stats.last7 }}</span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ─── Panneau droit — éditeur ────────────────────────────────────── -->
            <main class="flex-1 overflow-y-auto bg-muted/30 p-6">

                <!-- ── Panneau : Métadonnées ── -->
                <div v-if="panneauActif === 'meta'" class="mx-auto max-w-xl space-y-6">
                    <div>
                        <h2 class="text-base font-semibold">Métadonnées du projet</h2>
                        <p class="text-sm text-muted-foreground">
                            Ces informations apparaîtront sur la carte galerie publique.
                        </p>
                    </div>

                    <Card>
                        <CardContent class="grid gap-5 pt-6">
                            <!-- Intro texte -->
                            <div class="grid gap-2">
                                <Label for="intro_texte">Texte d'introduction</Label>
                                <Textarea
                                    id="intro_texte"
                                    v-model="formMeta.intro_texte"
                                    :disabled="!peutEditer"
                                    rows="3"
                                    placeholder="Courte description visible dans la galerie…"
                                />
                                <InputError :message="formMeta.errors.intro_texte" />
                            </div>

                            <!-- Catégorisation -->
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <!-- Époque -->
                                <div class="grid min-w-0 gap-2">
                                    <Label>Époque historique</Label>
                                    <Select
                                        v-model="formMeta.epoque_historique_id"
                                        :disabled="!peutEditer"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choisir…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="epoque in epoques"
                                                :key="epoque.id"
                                                :value="epoque.id.toString()"
                                            >
                                                {{ epoque.nom }}
                                                <span
                                                    v-if="epoque.annee_debut || epoque.annee_fin"
                                                    class="ml-1 text-muted-foreground"
                                                >
                                                    ({{ epoque.annee_debut ?? '…' }}–{{ epoque.annee_fin ?? "auj." }})
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="formMeta.errors.epoque_historique_id" />
                                </div>

                                <!-- Thématique -->
                                <div class="grid min-w-0 gap-2">
                                    <Label>Thématique</Label>
                                    <Select
                                        v-model="formMeta.thematique_id"
                                        :disabled="!peutEditer"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choisir…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="thematique in thematiques"
                                                :key="thematique.id"
                                                :value="thematique.id.toString()"
                                            >
                                                {{ thematique.nom }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="formMeta.errors.thematique_id" />
                                </div>

                                <!-- Région administrative -->
                                <div class="grid min-w-0 gap-2">
                                    <Label>Région administrative</Label>
                                    <Select
                                        v-model="formMeta.region_administrative_id"
                                        :disabled="!peutEditer"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choisir…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="region in regionsAdministratives"
                                                :key="region.id"
                                                :value="region.id.toString()"
                                            >
                                                {{ region.nom }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="formMeta.errors.region_administrative_id" />
                                </div>
                            </div>

                            <!-- Slug (lecture seule) -->
                            <div class="grid gap-1.5">
                                <Label class="text-muted-foreground">Identifiant unique (slug)</Label>
                                <p class="rounded border bg-muted/50 px-3 py-2 font-mono text-xs text-muted-foreground">
                                    {{ meta.slug }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex justify-end">
                        <Button
                            :disabled="!peutEditer || formMeta.processing"
                            @click="sauvegarderMeta"
                        >
                            {{ formMeta.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>

                <!-- ── Panneau : En-tête ── -->
                <div v-else-if="panneauActif === 'entete'" class="mx-auto max-w-xl space-y-6">
                    <div>
                        <h2 class="text-base font-semibold">En-tête du musée</h2>
                        <p class="text-sm text-muted-foreground">
                            Titre, sous-titre, image de fond et position de cadrage.
                        </p>
                    </div>

                    <!-- Prévisualisation de l'en-tête -->
                    <div
                        class="relative flex h-40 items-end overflow-hidden rounded-lg border"
                        :style="{
                            backgroundImage: imageEntetePreview
                                ? `url(${imageEntetePreview})`
                                : undefined,
                            backgroundSize: 'cover',
                            backgroundPosition: formEntete.entete_image_position === 'top'
                                ? 'center top'
                                : formEntete.entete_image_position === 'bottom'
                                  ? 'center bottom'
                                  : 'center center',
                            backgroundColor: imageEntetePreview
                                ? undefined
                                : 'hsl(var(--muted))',
                        }"
                    >
                        <div
                            class="absolute inset-0"
                            :style="{
                                backgroundColor:
                                    formEntete.entete_overlay_couleur || 'transparent',
                                opacity: imageEntetePreview ? 0.5 : 0,
                            }"
                        />
                        <div
                            v-if="!imageEntetePreview"
                            class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
                        >
                            <ImageIcon class="mr-2 h-5 w-5" />
                            Aucune image sélectionnée
                        </div>
                        <div
                            v-if="formEntete.entete_titre || formEntete.entete_sous_titre"
                            class="relative z-10 p-4"
                        >
                            <p
                                v-if="formEntete.entete_titre"
                                class="text-lg font-bold leading-tight text-white drop-shadow"
                            >
                                {{ formEntete.entete_titre }}
                            </p>
                            <p
                                v-if="formEntete.entete_sous_titre"
                                class="text-sm text-white/90 drop-shadow"
                            >
                                {{ formEntete.entete_sous_titre }}
                            </p>
                        </div>
                    </div>

                    <Card>
                        <CardContent class="grid gap-5 pt-6">
                            <div class="grid gap-2">
                                <Label for="entete_image">Image de fond</Label>
                                <input
                                    id="entete_image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    :disabled="!peutEditer"
                                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                                    @change="onImageEnteteChange"
                                />
                                <p class="text-xs text-muted-foreground">
                                    JPEG, PNG ou WebP — max 4 Mo.
                                </p>
                                <InputError :message="formEntete.errors.entete_image" />
                            </div>

                            <!-- Position de l'image de fond (tâche 4.4) -->
                            <div class="grid gap-2">
                                <Label>Position de l'image</Label>
                                <div class="flex gap-2">
                                    <button
                                        v-for="opt in [
                                            { val: 'top', label: 'Haut' },
                                            { val: 'center', label: 'Centre' },
                                            { val: 'bottom', label: 'Bas' },
                                        ]"
                                        :key="opt.val"
                                        type="button"
                                        :disabled="!peutEditer"
                                        :class="[
                                            'flex-1 rounded border py-1.5 text-sm transition-colors',
                                            formEntete.entete_image_position === opt.val
                                                ? 'border-primary bg-primary/10 font-medium text-primary'
                                                : 'border-border text-muted-foreground hover:bg-muted',
                                        ]"
                                        @click="formEntete.entete_image_position = opt.val"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Contrôle quelle partie de l'image reste visible quand elle est rognée.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="entete_titre">Titre de l'en-tête</Label>
                                <Input
                                    id="entete_titre"
                                    v-model="formEntete.entete_titre"
                                    :disabled="!peutEditer"
                                    placeholder="Ex : Le Musée de la Nouvelle-France"
                                />
                                <InputError :message="formEntete.errors.entete_titre" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="entete_sous_titre">Sous-titre</Label>
                                <Input
                                    id="entete_sous_titre"
                                    v-model="formEntete.entete_sous_titre"
                                    :disabled="!peutEditer"
                                    placeholder="Ex : Groupe 3 — Histoire 330"
                                />
                                <InputError :message="formEntete.errors.entete_sous_titre" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="entete_overlay">Couleur de l'overlay</Label>
                                <div class="flex items-center gap-3">
                                    <input
                                        id="entete_overlay"
                                        v-model="formEntete.entete_overlay_couleur"
                                        type="color"
                                        :disabled="!peutEditer"
                                        class="h-9 w-14 cursor-pointer rounded border border-input"
                                    />
                                    <span class="font-mono text-sm text-muted-foreground">
                                        {{ formEntete.entete_overlay_couleur }}
                                    </span>
                                </div>
                                <InputError :message="formEntete.errors.entete_overlay_couleur" />
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex justify-end">
                        <Button
                            :disabled="!peutEditer || formEntete.processing"
                            @click="sauvegarderEntete"
                        >
                            {{ formEntete.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>

                <!-- ── Panneau : Blocs ── -->
                <div v-else-if="panneauActif === 'blocs'" class="mx-auto max-w-2xl space-y-4">

                    <!-- Sélecteur de section (aucune section active) -->
                    <template v-if="!sectionActiveId">
                        <div>
                            <h2 class="text-base font-semibold">Contenu par section</h2>
                            <p class="text-sm text-muted-foreground">
                                Sélectionnez une section pour ajouter et organiser les blocs.
                            </p>
                        </div>

                        <div v-if="sections.length === 0">
                            <Card>
                                <CardContent class="py-10 text-center text-sm text-muted-foreground">
                                    Aucune section n'a encore été définie par l'enseignant pour ce
                                    type de projet.
                                </CardContent>
                            </Card>
                        </div>

                        <div v-else class="grid gap-3">
                            <Card
                                v-for="section in sections"
                                :key="section.id"
                                class="cursor-pointer transition-colors hover:bg-muted/60"
                                @click="sectionActiveId = section.id"
                            >
                                <CardContent class="flex items-center justify-between py-4">
                                    <div class="flex items-center gap-3">
                                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                                        <span class="font-medium">{{ section.label }}</span>
                                        <!-- Indicateur zones (mode canevas) -->
                                        <span
                                            v-if="hasCanevas(section)"
                                            class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                                        >
                                            {{ section.blocs.filter(b => b.zone_id).length }}/{{ section.musee_canevas!.zones.filter(z => z.type !== 'vide').length }} zones
                                        </span>
                                        <!-- Indicateur de blocs obligatoires manquants (mode libre) -->
                                        <span
                                            v-else-if="contraintesManquantes(section).length > 0"
                                            class="flex items-center gap-0.5 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                                            :title="`${contraintesManquantes(section).length} bloc(s) obligatoire(s) manquant(s)`"
                                        >
                                            <AlertTriangle class="h-2.5 w-2.5" />
                                            {{ contraintesManquantes(section).length }}
                                        </span>
                                    </div>
                                    <span class="text-sm text-muted-foreground">
                                        {{ section.blocs.length }}
                                        {{ section.blocs.length === 1 ? 'bloc' : 'blocs' }}
                                    </span>
                                </CardContent>
                            </Card>
                        </div>
                    </template>

                    <!-- Éditeur de blocs d'une section -->
                    <template v-else>
                        <!-- En-tête section -->
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                @click="sectionActiveId = null"
                            >
                                <ArrowLeft class="h-3.5 w-3.5" />
                                Sections
                            </button>
                            <span class="text-xs text-muted-foreground">/</span>
                            <h2 class="text-sm font-semibold">{{ sectionActive?.label }}</h2>
                        </div>

                        <!-- Avertissement : blocs obligatoires manquants (mode libre uniquement) -->
                        <div
                            v-if="!hasCanevas(sectionActive) && sectionActive && contraintesManquantes(sectionActive).length > 0"
                            class="flex flex-col gap-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"
                        >
                            <p class="flex items-center gap-1.5 text-xs font-semibold text-amber-800">
                                <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                                Blocs obligatoires manquants
                            </p>
                            <ul class="ml-5 list-disc text-xs text-amber-700">
                                <li
                                    v-for="c in contraintesManquantes(sectionActive)"
                                    :key="c.label + c.type"
                                >
                                    {{ c.label || c.type }}
                                </li>
                            </ul>
                        </div>

                        <!-- Mode canevas : aperçu visuel du canevas avec crayon / poubelle par zone -->
                        <div
                            v-if="hasCanevas(sectionActive) && sectionActive"
                            class="rounded-lg border border-border p-4"
                        >
                            <p class="mb-3 text-xs font-medium text-muted-foreground">
                                Canevas
                                <span class="ml-1 font-normal">
                                    ({{ sectionActive.blocs.filter(b => b.zone_id).length }} / {{ sectionActive.musee_canevas!.zones.filter(z => z.type !== 'vide').length }} zones remplies)
                                </span>
                            </p>

                            <!-- Grille visuelle du canevas — reproduit exactement la mise en page enseignant -->
                            <div
                                class="relative w-full overflow-hidden rounded border border-border bg-muted/20"
                                :style="{ paddingTop: `${sectionActive.musee_canevas!.hauteur_vw}%` }"
                            >
                                <div class="absolute inset-0">
                                    <div
                                        v-for="zone in sectionActive.musee_canevas!.zones"
                                        :key="zone.id"
                                        class="absolute"
                                        :style="{
                                            left: `${zone.x}%`,
                                            top: `${zone.y}%`,
                                            width: `${zone.w}%`,
                                            height: `${zone.h}%`,
                                        }"
                                    >
                                        <!-- Fond de la zone — rouge si obligatoire et vide, vert si remplie, neutre sinon -->
                                        <div
                                            class="group absolute flex flex-col items-center justify-center overflow-hidden transition-all"
                                            :style="{ inset: `${sectionActive.musee_canevas!.gap ?? 4}px` }"
                                            :class="[
                                                zone.type === 'vide'
                                                    ? 'border border-dashed border-white/20'
                                                    : blocPourZone(zone.id)
                                                        ? 'border border-emerald-400/50 bg-emerald-500/20'
                                                        : zone.obligatoire
                                                            ? 'border-2 border-dashed border-red-400/70 bg-red-500/10'
                                                            : 'border border-dashed border-white/20 bg-muted/40',
                                            ]"
                                        >
                                            <!-- Zone vide : type + label -->
                                            <template v-if="zone.type !== 'vide'">
                                                <span class="truncate px-1 text-center text-[9px] font-semibold text-foreground/60">
                                                    {{ zone.label }}
                                                    <span v-if="zone.obligatoire && !blocPourZone(zone.id)" class="text-red-500">*</span>
                                                </span>

                                                <!-- Actions : icônes centrées, visibles au hover -->
                                                <div
                                                    v-if="peutEditer"
                                                    class="mt-1 flex items-center gap-2 opacity-0 transition-opacity group-hover:opacity-100"
                                                >
                                                    <!-- Crayon : éditer / ajouter -->
                                                    <button
                                                        type="button"
                                                        :title="blocPourZone(zone.id) ? 'Modifier ce bloc' : 'Ajouter un contenu'"
                                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-background/80 shadow transition-colors hover:bg-primary hover:text-primary-foreground"
                                                        @click="blocPourZone(zone.id) ? editerBloc(blocPourZone(zone.id)!) : ajouterBlocZone(zone)"
                                                    >
                                                        <Pencil class="h-3.5 w-3.5" />
                                                    </button>
                                                    <!-- Poubelle : vider la zone (uniquement si bloc existant) -->
                                                    <button
                                                        v-if="blocPourZone(zone.id)"
                                                        type="button"
                                                        title="Vider cette zone"
                                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-background/80 shadow transition-colors hover:bg-destructive hover:text-destructive-foreground"
                                                        @click="viderZone(zone)"
                                                    >
                                                        <Trash2 class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>

                                                <!-- Indicateur visuel de contenu rempli -->
                                                <CheckCircle2
                                                    v-if="blocPourZone(zone.id)"
                                                    class="absolute right-1 top-1 h-3 w-3 text-emerald-500"
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Liste des blocs (draggable) -->
                        <VueDraggable
                            v-model="localBlocs"
                            handle=".bloc-drag"
                            :animation="150"
                            class="space-y-3"
                            @end="onBlocDragEnd"
                        >
                            <div
                                v-for="bloc in localBlocs"
                                :key="bloc.id"
                                class="rounded-lg border bg-background shadow-sm"
                            >
                                <!-- En-tête du bloc -->
                                <div class="flex items-center gap-2 border-b px-3 py-2">
                                    <GripVertical
                                        class="bloc-drag h-4 w-4 shrink-0 cursor-grab text-muted-foreground/40"
                                    />

                                    <!-- Badge type -->
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                            bloc.type === 'texte'
                                                ? 'bg-blue-100 text-blue-700'
                                                : bloc.type === 'image'
                                                  ? 'bg-emerald-100 text-emerald-700'
                                                  : bloc.type === 'carrousel'
                                                    ? 'bg-violet-100 text-violet-700'
                                                    : bloc.type === 'video'
                                                      ? 'bg-amber-100 text-amber-700'
                                                      : bloc.type === 'audio'
                                                        ? 'bg-pink-100 text-pink-700'
                                                        : 'bg-gray-100 text-gray-600',
                                        ]"
                                    >
                                        <Type v-if="bloc.type === 'texte'" class="h-3 w-3" />
                                        <ImageIcon v-else-if="bloc.type === 'image'" class="h-3 w-3" />
                                        <ChevronRight v-else-if="bloc.type === 'carrousel'" class="h-3 w-3" />
                                        <Video v-else-if="bloc.type === 'video'" class="h-3 w-3" />
                                        <Music v-else-if="bloc.type === 'audio'" class="h-3 w-3" />
                                        <Minus v-else class="h-3 w-3" />
                                        {{ bloc.type }}
                                    </span>

                                    <span class="flex-1" />

                                    <!-- Bascule colonne (sections 2-colonnes uniquement) -->
                                    <button
                                        v-if="peutEditer && sectionActive?.layout?.nb_colonnes === 2"
                                        type="button"
                                        class="rounded border border-border px-1.5 py-0.5 text-[11px] text-muted-foreground hover:border-muted-foreground/50 hover:text-foreground"
                                        :title="`Colonne ${bloc.colonne} — cliquer pour déplacer`"
                                        @click="changerColonne(bloc, sectionActive!)"
                                    >
                                        Col.{{ bloc.colonne }}
                                    </button>

                                    <!-- Bouton éditer (sauf séparateur) -->
                                    <button
                                        v-if="bloc.type !== 'separateur' && peutEditer"
                                        type="button"
                                        :class="[
                                            'rounded px-2 py-1 text-xs transition-colors',
                                            expandedBlocId === bloc.id
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted',
                                        ]"
                                        @click="editerBloc(bloc)"
                                    >
                                        {{ expandedBlocId === bloc.id ? 'Fermer' : 'Éditer' }}
                                    </button>

                                    <!-- Bouton supprimer -->
                                    <button
                                        v-if="peutEditer"
                                        type="button"
                                        class="rounded p-1 text-muted-foreground hover:bg-red-50 hover:text-red-600"
                                        title="Supprimer ce bloc"
                                        @click="supprimerBloc(bloc)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <!-- Aperçu condensé (quand fermé) -->
                                <div
                                    v-if="expandedBlocId !== bloc.id"
                                    class="px-3 py-2"
                                >
                                    <!-- Aperçu texte -->
                                    <p
                                        v-if="bloc.type === 'texte'"
                                        class="line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{ bloc.contenu && (bloc.contenu as BlocContenuTexte).html
                                            ? (bloc.contenu as BlocContenuTexte).html.replace(/<[^>]*>/g, ' ').trim() || '(vide)'
                                            : '(vide)' }}
                                    </p>

                                    <!-- Aperçu image -->
                                    <div
                                        v-else-if="bloc.type === 'image'"
                                        class="flex items-center gap-2"
                                    >
                                        <img
                                            v-if="bloc.contenu && (bloc.contenu as BlocContenuImage).image_id && imagePourBloc((bloc.contenu as BlocContenuImage).image_id!)"
                                            :src="imagePourBloc((bloc.contenu as BlocContenuImage).image_id!)!.url"
                                            :alt="(bloc.contenu as BlocContenuImage).alt"
                                            class="h-10 w-14 rounded object-cover"
                                        />
                                        <span
                                            v-else
                                            class="text-xs italic text-muted-foreground"
                                        >Aucune image sélectionnée</span>
                                    </div>

                                    <!-- Aperçu carrousel -->
                                    <div
                                        v-else-if="bloc.type === 'carrousel'"
                                        class="flex items-center gap-1.5"
                                    >
                                        <div class="flex -space-x-2">
                                            <img
                                                v-for="(item, i) in (bloc.contenu as BlocContenuCarrousel).images.slice(0, 4)"
                                                :key="i"
                                                :src="imagePourBloc(item.image_id)?.url"
                                                class="h-8 w-8 rounded-full border-2 border-white object-cover"
                                                :alt="item.alt"
                                            />
                                        </div>
                                        <span class="text-xs text-muted-foreground">
                                            {{ (bloc.contenu as BlocContenuCarrousel).images.length }}
                                            image{{ (bloc.contenu as BlocContenuCarrousel).images.length !== 1 ? 's' : '' }}
                                        </span>
                                    </div>

                                    <!-- Aperçu vidéo -->
                                    <div
                                        v-else-if="bloc.type === 'video'"
                                        class="flex items-center gap-2"
                                    >
                                        <div class="flex h-8 w-12 shrink-0 items-center justify-center rounded bg-muted">
                                            <Video class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <span class="truncate text-xs text-muted-foreground">
                                            {{ (bloc.contenu as BlocContenuVideo)?.source === 'upload'
                                                ? ((props.videos ?? []).find(v => v.id === (bloc.contenu as BlocContenuVideo)?.groupe_video_id)?.titre ?? 'Vidéo non sélectionnée')
                                                : ((bloc.contenu as BlocContenuVideo)?.url_externe || 'URL non définie') }}
                                        </span>
                                    </div>

                                    <!-- Aperçu audio -->
                                    <div
                                        v-else-if="bloc.type === 'audio'"
                                        class="flex items-center gap-2"
                                    >
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-pink-50">
                                            <Music class="h-4 w-4 text-pink-500" />
                                        </div>
                                        <span class="truncate text-xs text-muted-foreground">
                                            <template v-if="(bloc.contenu as BlocContenuAudio)?.pistes?.length">
                                                {{ (bloc.contenu as BlocContenuAudio).pistes.filter(p => p.groupe_media_id).length }}
                                                /
                                                {{ (bloc.contenu as BlocContenuAudio).pistes.length }}
                                                piste(s) sélectionnée(s)
                                            </template>
                                            <template v-else>Aucune piste</template>
                                        </span>
                                    </div>

                                    <!-- Séparateur -->
                                    <div
                                        v-else
                                        class="h-px w-full bg-border"
                                    />
                                </div>

                                <!-- Handle de redimensionnement (image, vidéo, audio, carrousel) -->
                                <div
                                    v-if="peutEditer && TYPES_REDIMENSIONNABLES.includes(bloc.type)"
                                    class="group flex cursor-s-resize select-none items-center justify-between border-t border-dashed border-border/50 px-3 py-1 hover:border-border hover:bg-muted/30"
                                    title="Glisser pour ajuster la hauteur"
                                    @mousedown="demarrerRedimensionnement($event, localBlocs.find(b => b.id === bloc.id)!)"
                                >
                                    <span class="text-[10px] text-muted-foreground/60 group-hover:text-muted-foreground">
                                        {{
                                            localBlocs.find(b => b.id === bloc.id)?.hauteur_px
                                                ? `${localBlocs.find(b => b.id === bloc.id)!.hauteur_px}px`
                                                : 'hauteur auto'
                                        }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="localBlocs.find(b => b.id === bloc.id)?.hauteur_px"
                                            type="button"
                                            class="text-[10px] text-muted-foreground/60 hover:text-foreground"
                                            title="Réinitialiser les dimensions"
                                            @click.stop="reinitialiserDimensions(bloc)"
                                        >
                                            Réinitialiser
                                        </button>
                                        <GripVertical class="h-3 w-3 rotate-90 text-muted-foreground/40 group-hover:text-muted-foreground" />
                                    </div>
                                </div>

                                <!-- Éditeur inline (quand ouvert) -->
                                <div
                                    v-if="expandedBlocId === bloc.id && draftContenu !== null"
                                    class="space-y-3 p-3"
                                >
                                    <!-- Éditeur texte -->
                                    <template v-if="bloc.type === 'texte'">
                                        <MuseeRichEditor
                                            v-model="(draftContenu as BlocContenuTexte).html"
                                            :readonly="!peutEditer"
                                        />

                                        <!-- ── Image ancrée (tâche 4.3) ── -->
                                        <div class="rounded border bg-muted/30 p-3 space-y-2">
                                            <p class="text-xs font-medium text-muted-foreground">
                                                Image latérale (optionnelle)
                                            </p>

                                            <!-- Image ancrée sélectionnée -->
                                            <div
                                                v-if="(draftContenu as BlocContenuTexte).image_ancree?.image_id"
                                                class="flex items-center gap-3"
                                            >
                                                <img
                                                    :src="imagePourBloc((draftContenu as BlocContenuTexte).image_ancree!.image_id!)?.url"
                                                    class="h-14 w-20 rounded object-cover"
                                                    alt=""
                                                />
                                                <div class="space-y-1.5">
                                                    <!-- Position gauche / droite -->
                                                    <div class="flex gap-1.5">
                                                        <button
                                                            v-for="pos in [{ val: 'gauche', label: 'Gauche' }, { val: 'droite', label: 'Droite' }]"
                                                            :key="pos.val"
                                                            type="button"
                                                            :class="[
                                                                'rounded border px-2 py-0.5 text-xs',
                                                                (draftContenu as BlocContenuTexte).image_ancree!.position === pos.val
                                                                    ? 'border-primary bg-primary/10 text-primary'
                                                                    : 'border-border text-muted-foreground hover:bg-muted',
                                                            ]"
                                                            @click="(draftContenu as BlocContenuTexte).image_ancree!.position = pos.val as 'gauche' | 'droite'"
                                                        >
                                                            {{ pos.label }}
                                                        </button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="text-xs text-red-500 hover:underline"
                                                        @click="(draftContenu as BlocContenuTexte).image_ancree = null"
                                                    >
                                                        Retirer l'image
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Grille de sélection -->
                                            <div v-if="localImages.length > 0 && !(draftContenu as BlocContenuTexte).image_ancree?.image_id">
                                                <p class="mb-1.5 text-[11px] text-muted-foreground">
                                                    Choisir une image :
                                                </p>
                                                <div class="grid grid-cols-6 gap-1.5">
                                                    <button
                                                        v-for="img in localImages"
                                                        :key="img.id"
                                                        type="button"
                                                        class="overflow-hidden rounded border-2 border-transparent hover:border-primary/40"
                                                        :title="img.alt || img.legende"
                                                        @click="selectionnerImageAncree(img.id)"
                                                    >
                                                        <img
                                                            :src="img.url"
                                                            :alt="img.alt"
                                                            class="h-10 w-full object-cover"
                                                            :style="cropStyle(img)"
                                                        />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Éditeur image -->
                                    <template v-else-if="bloc.type === 'image'">
                                        <!-- Image sélectionnée -->
                                        <div
                                            v-if="(draftContenu as BlocContenuImage).image_id && imagePourBloc((draftContenu as BlocContenuImage).image_id!)"
                                            class="flex items-start gap-3 rounded border p-2"
                                        >
                                            <img
                                                :src="imagePourBloc((draftContenu as BlocContenuImage).image_id!)!.url"
                                                :alt="(draftContenu as BlocContenuImage).alt"
                                                class="h-16 w-24 rounded object-cover"
                                                :style="cropStyle(imagePourBloc((draftContenu as BlocContenuImage).image_id!))"
                                            />
                                            <div class="flex-1 space-y-1">
                                                <p class="text-xs font-medium">Image sélectionnée</p>
                                                <button
                                                    type="button"
                                                    class="text-xs text-red-500 hover:underline"
                                                    @click="(draftContenu as BlocContenuImage).image_id = null"
                                                >
                                                    Retirer
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Bibliothèque d'images -->
                                        <div v-if="localImages.length > 0">
                                            <p class="mb-2 text-xs font-medium text-muted-foreground">
                                                Choisir depuis la bibliothèque :
                                            </p>
                                            <div class="grid grid-cols-4 gap-2">
                                                <button
                                                    v-for="img in localImages"
                                                    :key="img.id"
                                                    type="button"
                                                    :class="[
                                                        'overflow-hidden rounded border-2 transition-colors',
                                                        (draftContenu as BlocContenuImage).image_id === img.id
                                                            ? 'border-primary'
                                                            : 'border-transparent hover:border-muted-foreground/40',
                                                    ]"
                                                    :title="img.alt || img.legende"
                                                    @click="selectionnerImage(img.id)"
                                                >
                                                    <img
                                                        :src="img.url"
                                                        :alt="img.alt"
                                                        class="h-16 w-full object-cover"
                                                        :style="cropStyle(img)"
                                                    />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Upload nouvelle image -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Ou importer une image</Label>
                                            <input
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp,image/gif"
                                                :disabled="isUploadingImage || !peutEditer"
                                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-xs file:border-0 file:bg-transparent file:text-xs file:font-medium file:text-foreground cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                                                @change="uploaderImage"
                                            />
                                            <p v-if="isUploadingImage" class="text-xs text-muted-foreground">
                                                Envoi en cours…
                                            </p>
                                            <p v-if="erreurUpload" class="text-xs text-destructive">
                                                {{ erreurUpload }}
                                            </p>
                                        </div>

                                        <!-- Alt et légende -->
                                        <div class="grid gap-3">
                                            <div class="grid gap-1.5">
                                                <Label for="bloc-alt" class="text-xs">Texte alternatif (alt)</Label>
                                                <Input
                                                    id="bloc-alt"
                                                    v-model="(draftContenu as BlocContenuImage).alt"
                                                    :disabled="!peutEditer"
                                                    class="text-xs"
                                                    placeholder="Description de l'image pour l'accessibilité"
                                                />
                                            </div>
                                            <div class="grid gap-1.5">
                                                <Label for="bloc-legende" class="text-xs">Légende</Label>
                                                <Input
                                                    id="bloc-legende"
                                                    v-model="(draftContenu as BlocContenuImage).legende"
                                                    :disabled="!peutEditer"
                                                    class="text-xs"
                                                    placeholder="Légende affichée sous l'image"
                                                />
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Éditeur carrousel (tâche 4.2) -->
                                    <template v-else-if="bloc.type === 'carrousel'">
                                        <!-- Images dans le carrousel -->
                                        <div v-if="(draftContenu as BlocContenuCarrousel).images.length > 0" class="space-y-2">
                                            <p class="text-xs font-medium text-muted-foreground">
                                                Images du carrousel :
                                            </p>
                                            <VueDraggable
                                                v-model="(draftContenu as BlocContenuCarrousel).images"
                                                handle=".carr-drag"
                                                :animation="150"
                                                class="space-y-1.5"
                                            >
                                                <div
                                                    v-for="(item, idx) in (draftContenu as BlocContenuCarrousel).images"
                                                    :key="item.image_id"
                                                    class="flex items-center gap-2 rounded border bg-muted/20 p-2"
                                                >
                                                    <GripVertical class="carr-drag h-4 w-4 shrink-0 cursor-grab text-muted-foreground/40" />
                                                    <img
                                                        :src="imagePourBloc(item.image_id)?.url"
                                                        class="h-10 w-14 rounded object-cover"
                                                        :style="cropStyle(imagePourBloc(item.image_id))"
                                                        :alt="item.alt"
                                                    />
                                                    <div class="flex-1 grid gap-1">
                                                        <input
                                                            v-model="item.alt"
                                                            class="w-full rounded border border-input bg-background px-2 py-0.5 text-xs"
                                                            placeholder="Texte alt…"
                                                        />
                                                        <input
                                                            v-model="item.legende"
                                                            class="w-full rounded border border-input bg-background px-2 py-0.5 text-xs"
                                                            placeholder="Légende…"
                                                        />
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="rounded p-1 text-muted-foreground hover:bg-red-50 hover:text-red-500"
                                                        @click="retirerImageCarrousel(idx)"
                                                    >
                                                        <Trash2 class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </VueDraggable>
                                        </div>

                                        <!-- Bibliothèque — ajouter au carrousel -->
                                        <div v-if="localImages.length > 0">
                                            <p class="mb-1.5 text-xs font-medium text-muted-foreground">
                                                Ajouter depuis la bibliothèque :
                                            </p>
                                            <div class="grid grid-cols-5 gap-1.5">
                                                <button
                                                    v-for="img in localImages"
                                                    :key="img.id"
                                                    type="button"
                                                    :disabled="(draftContenu as BlocContenuCarrousel).images.some(i => i.image_id === img.id)"
                                                    :class="[
                                                        'overflow-hidden rounded border-2 transition-colors disabled:opacity-40',
                                                        (draftContenu as BlocContenuCarrousel).images.some(i => i.image_id === img.id)
                                                            ? 'border-primary cursor-default'
                                                            : 'border-transparent hover:border-primary/40',
                                                    ]"
                                                    :title="img.alt || img.legende"
                                                    @click="ajouterImageCarrousel(img.id)"
                                                >
                                                    <img
                                                        :src="img.url"
                                                        :alt="img.alt"
                                                        class="h-12 w-full object-cover"
                                                        :style="cropStyle(img)"
                                                    />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Upload nouvelle image pour le carrousel -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Ou importer une image</Label>
                                            <input
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp,image/gif"
                                                :disabled="isUploadingImage || !peutEditer"
                                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-xs file:border-0 file:bg-transparent file:text-xs file:font-medium file:text-foreground cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                                                @change="uploaderImage"
                                            />
                                            <p v-if="isUploadingImage" class="text-xs text-muted-foreground">
                                                Envoi en cours…
                                            </p>
                                            <p v-if="erreurUpload" class="text-xs text-destructive">
                                                {{ erreurUpload }}
                                            </p>
                                        </div>
                                    </template>

                                    <!-- Éditeur vidéo -->
                                    <template v-else-if="bloc.type === 'video'">
                                        <!-- Source -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Source</Label>
                                            <div class="flex gap-2">
                                                <button
                                                    v-for="src in [
                                                        { val: 'upload', label: 'Vidéo du groupe' },
                                                        { val: 'youtube', label: 'YouTube' },
                                                        { val: 'vimeo', label: 'Vimeo' },
                                                    ]"
                                                    :key="src.val"
                                                    type="button"
                                                    :class="[
                                                        'flex-1 rounded border py-1.5 text-xs transition-colors',
                                                        (draftContenu as BlocContenuVideo).source === src.val
                                                            ? 'border-primary bg-primary/10 font-medium text-primary'
                                                            : 'border-border text-muted-foreground hover:bg-muted',
                                                    ]"
                                                    @click="(draftContenu as BlocContenuVideo).source = src.val as 'upload' | 'youtube' | 'vimeo'"
                                                >
                                                    {{ src.label }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Upload : sélecteur vidéo du groupe -->
                                        <div v-if="(draftContenu as BlocContenuVideo).source === 'upload'">
                                            <p v-if="!props.videos || props.videos.length === 0" class="text-xs italic text-muted-foreground">
                                                {{ props.videos === null ? 'Chargement…' : 'Aucune vidéo disponible dans ce groupe.' }}
                                            </p>
                                            <div v-else class="grid grid-cols-2 gap-2">
                                                <button
                                                    v-for="vid in props.videos"
                                                    :key="vid.id"
                                                    type="button"
                                                    :class="[
                                                        'flex items-start gap-2 rounded border p-2 text-left transition-colors',
                                                        (draftContenu as BlocContenuVideo).groupe_video_id === vid.id
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-border hover:bg-muted',
                                                    ]"
                                                    @click="(draftContenu as BlocContenuVideo).groupe_video_id = vid.id"
                                                >
                                                    <img
                                                        v-if="vid.thumbnail_url"
                                                        :src="vid.thumbnail_url"
                                                        class="h-12 w-20 shrink-0 rounded object-cover"
                                                        alt=""
                                                    />
                                                    <div
                                                        v-else
                                                        class="flex h-12 w-20 shrink-0 items-center justify-center rounded bg-muted"
                                                    >
                                                        <Video class="h-5 w-5 text-muted-foreground" />
                                                    </div>
                                                    <span class="line-clamp-2 text-xs">{{ vid.titre }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- YouTube / Vimeo : URL -->
                                        <div v-else class="grid gap-1.5">
                                            <Label class="text-xs">URL de la vidéo</Label>
                                            <Input
                                                v-model="(draftContenu as BlocContenuVideo).url_externe"
                                                :disabled="!peutEditer"
                                                class="text-xs"
                                                :placeholder="(draftContenu as BlocContenuVideo).source === 'youtube'
                                                    ? 'https://www.youtube.com/watch?v=…'
                                                    : 'https://vimeo.com/…'"
                                            />
                                        </div>

                                        <!-- Légende -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Légende (optionnelle)</Label>
                                            <Input
                                                v-model="(draftContenu as BlocContenuVideo).legende"
                                                :disabled="!peutEditer"
                                                class="text-xs"
                                                placeholder="Légende affichée sous la vidéo"
                                            />
                                        </div>

                                        <!-- Segments / Chapitres -->
                                        <div class="space-y-2 rounded border bg-muted/20 p-3">
                                            <p class="text-xs font-medium text-muted-foreground">
                                                Segments (chapitres)
                                            </p>

                                            <!-- Liste des segments existants -->
                                            <div v-if="bloc.segments && bloc.segments.length > 0" class="space-y-1.5">
                                                <div
                                                    v-for="seg in bloc.segments"
                                                    :key="seg.id"
                                                    class="flex items-center gap-2 rounded border bg-background px-2 py-1.5"
                                                >
                                                    <span class="font-mono text-xs text-muted-foreground">
                                                        {{ formatTemps(seg.debut_secondes) }}–{{ formatTemps(seg.fin_secondes) }}
                                                    </span>
                                                    <span class="flex-1 text-xs">{{ seg.label }}</span>
                                                    <button
                                                        type="button"
                                                        class="rounded p-0.5 text-muted-foreground hover:bg-red-50 hover:text-red-500"
                                                        :title="`Supprimer le segment « ${seg.label} »`"
                                                        @click="supprimerSegment(seg.id, bloc.id)"
                                                    >
                                                        <Trash2 class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </div>
                                            <p v-else class="text-[11px] italic text-muted-foreground">
                                                Aucun segment défini.
                                            </p>

                                            <!-- Formulaire d'ajout de segment -->
                                            <template v-if="segmentFormBlocId === bloc.id">
                                                <div class="grid gap-2 rounded border border-dashed bg-background p-2">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div class="grid gap-1">
                                                            <Label class="text-[11px]">Début (s)</Label>
                                                            <Input
                                                                v-model="segmentDraft.debut_secondes"
                                                                type="number"
                                                                min="0"
                                                                class="text-xs"
                                                                placeholder="0"
                                                            />
                                                        </div>
                                                        <div class="grid gap-1">
                                                            <Label class="text-[11px]">Fin (s)</Label>
                                                            <Input
                                                                v-model="segmentDraft.fin_secondes"
                                                                type="number"
                                                                min="1"
                                                                class="text-xs"
                                                                placeholder="60"
                                                            />
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <Label class="text-[11px]">Libellé du segment</Label>
                                                        <Input
                                                            v-model="segmentDraft.label"
                                                            class="text-xs"
                                                            placeholder="Ex : Introduction"
                                                        />
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <Label class="text-[11px]">Section ciblée</Label>
                                                        <Select v-model="segmentDraft.section_id">
                                                            <SelectTrigger class="h-7 text-xs">
                                                                <SelectValue placeholder="Choisir une section…" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    v-for="sec in sections"
                                                                    :key="sec.id"
                                                                    :value="sec.id.toString()"
                                                                >
                                                                    {{ sec.label }}
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <Button
                                                            size="sm"
                                                            class="flex-1 text-xs"
                                                            @click="ajouterSegment(bloc)"
                                                        >
                                                            Ajouter
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            class="text-xs"
                                                            @click="segmentFormBlocId = null"
                                                        >
                                                            Annuler
                                                        </Button>
                                                    </div>
                                                </div>
                                            </template>
                                            <Button
                                                v-else-if="peutEditer"
                                                size="sm"
                                                variant="outline"
                                                class="w-full gap-1 text-xs"
                                                @click="segmentFormBlocId = bloc.id; segmentDraft = { section_id: '', debut_secondes: '', fin_secondes: '', label: '' }"
                                            >
                                                <Plus class="h-3 w-3" />
                                                Ajouter un segment
                                            </Button>
                                        </div>
                                    </template>

                                    <!-- Éditeur audio multi-pistes -->
                                    <template v-else-if="bloc.type === 'audio'">
                                        <!-- En-tête : label + bouton upload -->
                                        <div v-if="peutEditer" class="flex items-center justify-between">
                                            <span class="text-xs font-medium">Pistes audio</span>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                class="h-6 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                                                :disabled="audioUploadEnCours"
                                                @click="ouvrirSelecteurAudio"
                                            >
                                                <Plus class="h-3 w-3" />
                                                {{ audioUploadEnCours ? 'Upload…' : 'Uploader un audio' }}
                                            </Button>
                                            <!-- Input caché pour sélectionner le fichier -->
                                            <input
                                                ref="audioFileInput"
                                                type="file"
                                                accept="audio/*,.mp3,.wav,.ogg,.m4a,.aac"
                                                class="sr-only"
                                                @change="handleAudioChange"
                                            />
                                        </div>
                                        <p v-if="audioUploadErreur" class="text-xs text-destructive">{{ audioUploadErreur }}</p>

                                        <!-- Liste des pistes -->
                                        <div class="flex flex-col gap-3">
                                            <div
                                                v-for="(piste, idx) in (draftContenu as BlocContenuAudio).pistes"
                                                :key="idx"
                                                class="rounded border bg-muted/20 p-2.5 space-y-2"
                                            >
                                                <!-- En-tête piste -->
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Piste {{ idx + 1 }}</span>
                                                    <Button
                                                        v-if="peutEditer && (draftContenu as BlocContenuAudio).pistes.length > 1"
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        class="h-5 w-5 p-0 text-destructive hover:bg-destructive/10"
                                                        @click="retirerPisteAudio(idx)"
                                                    >
                                                        <X class="h-3 w-3" />
                                                    </Button>
                                                </div>

                                                <!-- Titre de la piste -->
                                                <div class="grid gap-1">
                                                    <Label class="text-xs">Titre (optionnel)</Label>
                                                    <Input
                                                        v-model="piste.titre"
                                                        :disabled="!peutEditer"
                                                        class="h-7 text-xs"
                                                        placeholder="Ex. : Entretien avec M. Tremblay"
                                                    />
                                                </div>

                                                <!-- Sélecteur audio -->
                                                <div class="grid gap-1">
                                                    <Label class="text-xs">Fichier audio</Label>
                                                    <p v-if="!props.audios || props.audios.length === 0" class="text-xs italic text-muted-foreground">
                                                        {{ props.audios === null ? 'Chargement…' : 'Aucun audio — uploadez-en un ci-dessus.' }}
                                                    </p>
                                                    <div v-else class="flex flex-col gap-1">
                                                        <button
                                                            v-for="audio in props.audios"
                                                            :key="audio.id"
                                                            type="button"
                                                            :class="[
                                                                'flex items-center gap-2 rounded border p-1.5 text-left transition-colors',
                                                                piste.groupe_media_id === audio.id
                                                                    ? 'border-primary bg-primary/5'
                                                                    : 'border-border hover:bg-muted',
                                                            ]"
                                                            :disabled="!peutEditer"
                                                            @click="piste.groupe_media_id = audio.id"
                                                        >
                                                            <Music class="h-3.5 w-3.5 shrink-0 text-pink-500" />
                                                            <span class="flex-1 truncate text-xs">{{ audio.nom_original }}</span>
                                                            <span
                                                                v-if="audio.transcription_statut === 'terminé'"
                                                                class="shrink-0 text-[10px] text-muted-foreground"
                                                            >
                                                                Transcription ✓
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bouton ajouter une piste -->
                                        <Button
                                            v-if="peutEditer && (draftContenu as BlocContenuAudio).pistes.length < 10"
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="w-full gap-1.5 text-xs"
                                            @click="ajouterPisteAudio"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                            Ajouter une piste
                                        </Button>

                                        <!-- Légende globale du bloc -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Légende globale (optionnelle)</Label>
                                            <Input
                                                v-model="(draftContenu as BlocContenuAudio).legende"
                                                :disabled="!peutEditer"
                                                class="text-xs"
                                                placeholder="Contexte ou description de l'enregistrement"
                                            />
                                        </div>
                                    </template>

                                    <!-- Bouton Sauvegarder -->
                                    <div v-if="peutEditer" class="flex justify-end">
                                        <Button size="sm" @click="sauvegarderBloc(bloc)">
                                            Sauvegarder
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </VueDraggable>

                        <!-- Message liste vide -->
                        <Card v-if="localBlocs.length === 0">
                            <CardContent class="py-8 text-center text-sm text-muted-foreground">
                                Aucun bloc dans cette section. Ajoutez-en un ci-dessous.
                            </CardContent>
                        </Card>

                        <!-- Palette de médias du groupe (mode blocs libre uniquement) -->
                        <div
                            v-if="peutEditer && !hasCanevas(sectionActive) && ((props.videos?.length ?? 0) > 0 || (props.audios?.length ?? 0) > 0 || localImages.length > 0)"
                            class="rounded-lg border bg-background"
                        >
                            <!-- En-tête palette -->
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-4 py-2.5 text-left"
                                @click="paletteOuverte = !paletteOuverte"
                            >
                                <span class="flex items-center gap-2 text-sm font-medium">
                                    <Music class="h-4 w-4 text-muted-foreground" />
                                    Médiathèque du groupe
                                </span>
                                <ChevronRight
                                    class="h-4 w-4 text-muted-foreground transition-transform"
                                    :class="{ 'rotate-90': paletteOuverte }"
                                />
                            </button>

                            <!-- Corps palette -->
                            <div v-if="paletteOuverte" class="border-t">
                                <!-- Onglets -->
                                <div class="flex border-b">
                                    <button
                                        v-for="onglet in ([
                                            { id: 'videos', label: 'Vidéos', count: props.videos?.length ?? 0 },
                                            { id: 'audios', label: 'Audios', count: props.audios?.length ?? 0 },
                                            { id: 'images', label: 'Images', count: localImages.length },
                                        ] as const)"
                                        :key="onglet.id"
                                        type="button"
                                        :class="[
                                            'flex items-center gap-1.5 px-3 py-2 text-xs font-medium transition-colors',
                                            paletteOnglet === onglet.id
                                                ? 'border-b-2 border-primary text-primary'
                                                : 'text-muted-foreground hover:text-foreground',
                                        ]"
                                        @click="paletteOnglet = onglet.id"
                                    >
                                        {{ onglet.label }}
                                        <span class="rounded-full bg-muted px-1.5 py-0.5 text-[10px]">
                                            {{ onglet.count }}
                                        </span>
                                    </button>
                                </div>

                                <!-- Vidéos -->
                                <div v-if="paletteOnglet === 'videos'" class="max-h-56 overflow-y-auto p-2 space-y-1">
                                    <p v-if="!props.videos || props.videos.length === 0" class="px-2 py-3 text-xs italic text-muted-foreground">
                                        Aucune vidéo traitée dans ce groupe.
                                    </p>
                                    <div
                                        v-for="vid in (props.videos ?? [])"
                                        :key="vid.id"
                                        class="flex items-center gap-2 rounded border px-2 py-1.5"
                                    >
                                        <img
                                            v-if="vid.thumbnail_url"
                                            :src="vid.thumbnail_url"
                                            class="h-8 w-12 shrink-0 rounded object-cover"
                                            alt=""
                                        />
                                        <div
                                            v-else
                                            class="flex h-8 w-12 shrink-0 items-center justify-center rounded bg-muted"
                                        >
                                            <Video class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <span class="flex-1 truncate text-xs">{{ vid.titre }}</span>
                                        <Button
                                            v-if="vid.transcription"
                                            size="sm"
                                            variant="ghost"
                                            class="shrink-0 text-xs h-7 px-2"
                                            @click="insererTranscription(vid.transcription!)"
                                        >
                                            <FileText class="h-3 w-3 mr-1" />
                                            Transcription
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="shrink-0 text-xs h-7 px-2"
                                            @click="ajouterBlocAvecMedia('video', { groupe_video_id: vid.id })"
                                        >
                                            <Plus class="h-3 w-3 mr-1" />
                                            Insérer
                                        </Button>
                                    </div>
                                </div>

                                <!-- Audios -->
                                <div v-if="paletteOnglet === 'audios'" class="max-h-56 overflow-y-auto p-2 space-y-1">
                                    <p v-if="!props.audios || props.audios.length === 0" class="px-2 py-3 text-xs italic text-muted-foreground">
                                        Aucun fichier audio dans ce groupe.
                                    </p>
                                    <div
                                        v-for="audio in (props.audios ?? [])"
                                        :key="audio.id"
                                        class="flex items-center gap-2 rounded border px-2 py-1.5"
                                    >
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-pink-50">
                                            <Music class="h-4 w-4 text-pink-500" />
                                        </div>
                                        <span class="flex-1 truncate text-xs">{{ audio.nom_original }}</span>
                                        <Button
                                            v-if="audio.transcription"
                                            size="sm"
                                            variant="ghost"
                                            class="shrink-0 text-xs h-7 px-2"
                                            @click="insererTranscription(audio.transcription!)"
                                        >
                                            <FileText class="h-3 w-3 mr-1" />
                                            Transcription
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="shrink-0 text-xs h-7 px-2"
                                            @click="ajouterBlocAvecMedia('audio', { groupe_media_id: audio.id })"
                                        >
                                            <Plus class="h-3 w-3 mr-1" />
                                            Insérer
                                        </Button>
                                    </div>
                                </div>

                                <!-- Images -->
                                <div v-if="paletteOnglet === 'images'" class="max-h-56 overflow-y-auto p-2">
                                    <p v-if="localImages.length === 0" class="px-2 py-3 text-xs italic text-muted-foreground">
                                        Aucune image dans la bibliothèque de ce musée.
                                    </p>
                                    <div v-else class="grid grid-cols-3 gap-1.5">
                                        <div
                                            v-for="img in localImages"
                                            :key="img.id"
                                            class="group relative aspect-square cursor-pointer overflow-hidden rounded border bg-muted"
                                            @click="sectionActiveId && insererImageCommeBloc(img.id)"
                                        >
                                            <img
                                                :src="img.url"
                                                :alt="img.alt"
                                                class="h-full w-full object-cover transition-opacity group-hover:opacity-70"
                                            />
                                            <!-- Overlay au survol -->
                                            <div class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:opacity-100">
                                                <span class="rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">
                                                    Insérer
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="localImages.length > 0 && !sectionActiveId" class="mt-2 text-[10px] italic text-muted-foreground">
                                        Sélectionnez une section d'abord.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'ajout (mode blocs libre uniquement — en mode canevas, les zones gèrent l'ajout) -->
                        <div v-if="peutEditer && !hasCanevas(sectionActive)" class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('texte')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <Type class="h-3.5 w-3.5" />
                                Texte
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('image')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <ImageIcon class="h-3.5 w-3.5" />
                                Image
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('carrousel')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <ChevronLeft class="h-3 w-3" />
                                <ChevronRight class="h-3 w-3" />
                                Carrousel
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('video')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <Video class="h-3.5 w-3.5" />
                                Vidéo
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('audio')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <Music class="h-3.5 w-3.5" />
                                Audio
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('separateur')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <Minus class="h-3.5 w-3.5" />
                                Séparateur
                            </Button>
                        </div>
                    </template>
                </div>
            </main>
        </div>

        <!-- ─── Modal d'aperçu du musée ────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="apercuOuvert"
                class="fixed inset-0 z-50 flex flex-col bg-background"
            >
                <!-- Barre de contrôle -->
                <div class="flex shrink-0 items-center justify-between border-b bg-background px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <Eye class="h-4 w-4 text-muted-foreground" />
                        <span class="text-sm font-semibold">Aperçu — {{ meta.entete_titre ?? typeProjet.nom }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <p class="text-xs text-muted-foreground">
                            Enregistrez vos modifications pour les voir ici.
                        </p>
                        <a
                            :href="`/musee/${meta.slug}`"
                            target="_blank"
                            rel="noopener"
                            class="text-xs text-primary underline underline-offset-2"
                        >Ouvrir dans un nouvel onglet</a>
                        <button
                            type="button"
                            class="rounded-md p-1 hover:bg-muted"
                            aria-label="Fermer l'aperçu"
                            @click="apercuOuvert = false"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- iframe pleine hauteur -->
                <iframe
                    :src="`/musee/${meta.slug}`"
                    class="flex-1 w-full border-none"
                    title="Aperçu du musée"
                />
            </div>
        </Teleport>
    </AppLayout>
</template>
