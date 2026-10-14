<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import MuseePublicNav from '@/components/musee/MuseePublicNav.vue'

// ─── Types ────────────────────────────────────────────────────────────────────

type Meta = {
    entete_titre: string | null
    entete_sous_titre: string | null
    entete_image_path: string | null
    entete_image_url: string | null
    entete_overlay_couleur: string | null
    entete_image_position: string | null
    intro_texte: string | null
    intro_image_path: string | null
    periode: { nom: string } | null
    thematique: { nom: string } | null
    region: { nom: string } | null
    slug: string
}

type BlocSegment = {
    id: number
    debut_secondes: number
    fin_secondes: number | null
    label: string
    section_id: number
}

type BlocContenuTexte = {
    html: string
    image_ancree?: {
        image_id: number | null
        position: 'gauche' | 'droite'
    }
}

type BlocContenuImage = {
    image_id: number | null
    alt: string | null
    legende: string | null
}

type BlocContenuCarrousel = {
    images: Array<{
        image_id: number
        alt: string | null
        legende: string | null
    }>
}

type BlocContenuVideo = {
    source: 'upload' | 'youtube' | 'vimeo'
    groupe_video_id?: number | null
    url_externe?: string | null
    legende?: string | null
}

type BlocPisteAudio = {
    groupe_media_id?: number | null
    url: string | null
    titre: string | null
}

type BlocContenuAudio = {
    // Nouveau format multi-pistes
    pistes?: BlocPisteAudio[]
    // Ancien format mono-piste (rétrocompatibilité)
    url?: string | null
    titre?: string | null
    legende: string | null
}

type Bloc = {
    id: number
    type: 'texte' | 'image' | 'carrousel' | 'video' | 'audio' | 'separateur'
    colonne: string | null
    hauteur_px: number | null
    largeur_pct: number | null
    zone_id: string | null
    contenu: BlocContenuTexte | BlocContenuImage | BlocContenuCarrousel | BlocContenuVideo | BlocContenuAudio | null
    segments?: BlocSegment[]
}

type SectionLayout = {
    nb_colonnes: 1 | 2
    ratio: string
} | null

type ZoneCanevas = {
    id: string
    type: string
    label: string
    x: number
    y: number
    w: number
    h: number
    ordre_mobile: number
}

type SectionCanevas = {
    hauteur_vw: number
    /** Écart en px entre zones (4 par défaut). */
    gap?: number
    zones: ZoneCanevas[]
}

type Section = {
    id: number
    label: string
    layout: SectionLayout
    musee_canevas: SectionCanevas | null
    blocs: Bloc[]
}

type MuseeImage = {
    id: number
    url: string
    alt: string
    crop?: { x: number; y: number; width: number; height: number } | null
}

type Membre = {
    id: number
    nom: string
    prenom: string
}

type Props = {
    meta: Meta
    sections: Section[]
    images: MuseeImage[]
    membres: Membre[]
    cssVars: Record<string, string>
    theme: string
    palette: string
    nbVues: number
    typeProjet: { id: number; nom: string }
    sectionsIndex: Record<number, string>
}

const props = defineProps<Props>()

// ─── Helpers images ───────────────────────────────────────────────────────────

function imageParId(id: number): MuseeImage | undefined {
    return props.images.find((img) => img.id === id)
}

function cropStyle(img: MuseeImage | undefined): Record<string, string> {
    if (!img?.crop) return { objectFit: 'cover' }
    const { x, y, width, height } = img.crop
    return {
        objectFit: 'none',
        objectPosition: `${-x}px ${-y}px`,
        width: `${width}px`,
        height: `${height}px`,
        maxWidth: 'none',
    }
}

// ─── CSS vars ────────────────────────────────────────────────────────────────

const cssVarsStyle = computed(() =>
    Object.entries(props.cssVars)
        .map(([k, v]) => `${k}:${v}`)
        .join(';'),
)

// ─── Sections avec contenu ───────────────────────────────────────────────────

const sectionsAvecContenu = computed(() =>
    props.sections.filter((s) => s.blocs.length > 0),
)

// ─── Layout 2 colonnes ───────────────────────────────────────────────────────

function ratioVersGridTemplate(ratio: string): string {
    const map: Record<string, string> = {
        '1:1': '1fr 1fr',
        '1:2': '1fr 2fr',
        '2:1': '2fr 1fr',
        '1:3': '1fr 3fr',
        '3:1': '3fr 1fr',
    }
    return map[ratio] ?? '1fr 1fr'
}

// ─── Rendu canevas de zones ───────────────────────────────────────────────────

/**
 * Classe CSS du conteneur de blocs selon le mode de la section.
 */
function sectionBlocsClass(section: Section): string {
    if (section.musee_canevas?.zones?.length) return 'musee-section__canevas'
    if (section.layout?.nb_colonnes === 2) return 'musee-section__blocs--grille'
    return ''
}

/**
 * Style du conteneur de blocs selon le mode de la section.
 *
 * En mode canevas : position:relative + padding-top (ratio d'aspect fixe).
 * Le padding-top en % est relatif à la largeur du parent — c'est l'astuce
 * CSS classique pour maintenir les proportions du canevas sur toutes les tailles d'écran.
 */
function sectionBlocsStyle(section: Section): Record<string, string> {
    if (section.musee_canevas?.zones?.length) {
        return { position: 'relative', paddingTop: `${section.musee_canevas.hauteur_vw}%` }
    }
    if (section.layout?.nb_colonnes === 2) {
        return { '--grille-template': ratioVersGridTemplate(section.layout!.ratio) }
    }
    return {}
}

/**
 * Style du wrapper de chaque bloc selon le mode.
 *
 * En mode canevas : position absolue calée sur la zone via les coordonnées %.
 * En mode libre : gridColumn + largeur optionnelle (comme avant).
 */
function blocWrapperStyle(section: Section, bloc: Bloc): Record<string, string> {
    if (section.musee_canevas?.zones?.length && bloc.zone_id) {
        const zone = section.musee_canevas.zones.find((z) => z.id === bloc.zone_id)
        // Les zones de type 'vide' sont des espaceurs — aucun contenu ne doit y être rendu.
        if (zone && zone.type !== 'vide') {
            const gap = section.musee_canevas.gap ?? 4
            return {
                position: 'absolute',
                left: `calc(${zone.x}% + ${gap}px)`,
                top: `calc(${zone.y}% + ${gap}px)`,
                width: `calc(${zone.w}% - ${gap * 2}px)`,
                height: `calc(${zone.h}% - ${gap * 2}px)`,
                overflow: 'hidden',
            }
        }
    }
    return {
        ...(section.layout?.nb_colonnes === 2 && bloc.colonne ? { gridColumn: bloc.colonne.toString() } : {}),
        ...(bloc.largeur_pct && bloc.largeur_pct < 100 ? { maxWidth: `${bloc.largeur_pct}%` } : {}),
    }
}

// ─── Titre onglet ────────────────────────────────────────────────────────────

const titreOnglet = computed(
    () => props.meta.entete_titre ?? props.typeProjet.nom,
)

// ─── Image d'en-tête ─────────────────────────────────────────────────────────

// Utilise l'URL fournie par le modèle (Storage::disk()->url()) plutôt que de
// construire /storage/... en dur — compatible avec un disque S3 en production.
const enteteImageUrl = computed(() => props.meta.entete_image_url ?? null)

const enteteBackgroundPosition = computed(
    () => props.meta.entete_image_position ?? 'center',
)

// ─── Carrousel ───────────────────────────────────────────────────────────────

const carrouselIndexes = ref<Record<number, number>>({})

function getCarrouselIndex(blocId: number): number {
    return carrouselIndexes.value[blocId] ?? 0
}

function setCarrouselIndex(blocId: number, index: number, total: number): void {
    // Modulo avec gestion des indices négatifs → boucle infinie dans les deux sens.
    const wrapped = ((index % total) + total) % total
    carrouselIndexes.value = { ...carrouselIndexes.value, [blocId]: wrapped }
}

// ─── Vidéo ───────────────────────────────────────────────────────────────────

const VITESSES_DISPONIBLES = [0.5, 0.75, 1, 1.25, 1.5, 2]

const videoElems = ref<Record<number, HTMLVideoElement>>({})
const videoCurrentTimes = ref<Record<number, number>>({})
const videoSpeeds = ref<Record<number, number>>({})

function setVideoElem(blocId: number) {
    return (el: HTMLVideoElement | null) => {
        if (el) videoElems.value[blocId] = el
    }
}

function changerVitesse(blocId: number, vitesse: number): void {
    videoSpeeds.value = { ...videoSpeeds.value, [blocId]: vitesse }
    const video = videoElems.value[blocId]
    if (video) video.playbackRate = vitesse
}

function onTimeUpdate(blocId: number, event: Event): void {
    const video = event.target as HTMLVideoElement
    videoCurrentTimes.value = { ...videoCurrentTimes.value, [blocId]: video.currentTime }
}

function seekTo(blocId: number, secondes: number): void {
    const video = videoElems.value[blocId]
    if (video) {
        video.currentTime = secondes
        video.play()
    }
}

function activeSegmentId(bloc: Bloc): number | null {
    if (!bloc.segments?.length) return null
    const t = videoCurrentTimes.value[bloc.id] ?? 0
    for (let i = bloc.segments.length - 1; i >= 0; i--) {
        if (t >= bloc.segments[i].debut_secondes) return bloc.segments[i].id
    }
    return null
}

// ─── Table des matières ───────────────────────────────────────────────────────

const sectionsToc = computed(() =>
    sectionsAvecContenu.value.map((s) => ({ id: s.id, label: s.label })),
)

const hasToc = computed(() => sectionsToc.value.length > 1)

const sectionActiveId = ref<number | null>(null)
const tocMobileOuvert = ref(false)

let observer: IntersectionObserver | null = null

onMounted(() => {
    if (!hasToc.value) return

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    const id = Number(entry.target.id.replace('section-', ''))
                    sectionActiveId.value = id
                }
            }
        },
        { rootMargin: '-20% 0px -70% 0px', threshold: 0 },
    )

    for (const s of sectionsAvecContenu.value) {
        const el = document.getElementById(`section-${s.id}`)
        if (el) observer.observe(el)
    }
})

onUnmounted(() => {
    observer?.disconnect()
})

function naviguerVersSection(sectionId: number): void {
    tocMobileOuvert.value = false
    const el = document.getElementById(`section-${sectionId}`)
    if (!el) return
    const navH = 52 + (hasToc.value ? 41 : 0)
    const y = el.getBoundingClientRect().top + window.scrollY - navH - 12
    window.scrollTo({ top: y, behavior: 'smooth' })
}

// ─── Liens internes ───────────────────────────────────────────────────────────

function interceptLiensInternes(event: MouseEvent): void {
    const target = event.target as HTMLElement
    const anchor = target.closest('a[data-section-id]') as HTMLAnchorElement | null
    if (!anchor) return
    const sectionId = Number(anchor.dataset.sectionId)
    if (!sectionId) return
    event.preventDefault()
    naviguerVersSection(sectionId)
}

function cliqueSurSegment(bloc: Bloc, seg: BlocSegment): void {
    seekTo(bloc.id, seg.debut_secondes)
}

// ─── YouTube / Vimeo ─────────────────────────────────────────────────────────

function youtubeEmbedUrl(url: string): string | null {
    const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{11})/)
    return match ? `https://www.youtube.com/embed/${match[1]}` : null
}

function vimeoEmbedUrl(url: string): string | null {
    const match = url.match(/vimeo\.com\/(\d+)/)
    return match ? `https://player.vimeo.com/video/${match[1]}` : null
}

/** Formate des secondes entières en mm:ss pour l'affichage dans les segments. */
function formatTemps(secondes: number): string {
    const m = Math.floor(secondes / 60)
    const s = secondes % 60
    return `${m}:${s.toString().padStart(2, '0')}`
}
</script>

<template>
    <div class="musee-public" :style="cssVarsStyle">
        <Head :title="titreOnglet" />

        <!-- ─── Navigation ──────────────────────────────────────────────────── -->
        <MuseePublicNav />

        <!-- ─── En-tête ─────────────────────────────────────────────────────── -->
        <header
            class="musee-hero"
            :style="{
                backgroundImage: enteteImageUrl ? `url(${enteteImageUrl})` : undefined,
                backgroundSize: 'cover',
                backgroundPosition: enteteBackgroundPosition,
                backgroundColor: enteteImageUrl ? undefined : 'var(--musee-couleur-accent, hsl(220 14% 28%))',
            }"
        >
            <!-- Overlay -->
            <div
                v-if="enteteImageUrl"
                class="musee-hero__overlay"
                :style="{ backgroundColor: meta.entete_overlay_couleur ?? 'rgba(0,0,0,0.45)' }"
            />

            <div class="musee-hero__contenu">
                <div
                    v-if="meta.periode || meta.thematique || meta.region"
                    class="musee-hero__tags"
                >
                    <span v-if="meta.periode" class="musee-hero__tag">{{ meta.periode.nom }}</span>
                    <span v-if="meta.thematique" class="musee-hero__tag">{{ meta.thematique.nom }}</span>
                    <span v-if="meta.region" class="musee-hero__tag">{{ meta.region.nom }}</span>
                </div>

                <h1
                    v-if="meta.entete_titre"
                    class="musee-hero__titre"
                    style="font-family: var(--musee-font-titre-page, inherit)"
                >
                    {{ meta.entete_titre }}
                </h1>
                <p
                    v-if="meta.entete_sous_titre"
                    class="musee-hero__sous-titre"
                    style="font-family: var(--musee-font-sous-titre, inherit)"
                >
                    {{ meta.entete_sous_titre }}
                </p>
            </div>
        </header>

        <!-- ─── Table des matières (onglets horizontaux scrollables) ────────── -->
        <nav
            v-if="hasToc"
            class="musee-toc sticky z-30 border-b"
            style="top: 3.25rem; background-color: var(--musee-couleur-fond, var(--background, white))"
            aria-label="Table des matières"
        >
            <ul class="musee-toc__liste">
                <li
                    v-for="s in sectionsToc"
                    :key="s.id"
                >
                    <button
                        type="button"
                        :class="[
                            'musee-toc__lien',
                            sectionActiveId === s.id ? 'musee-toc__lien--actif' : '',
                        ]"
                        @click="naviguerVersSection(s.id)"
                    >
                        {{ s.label }}
                    </button>
                </li>
            </ul>
        </nav>

        <!-- ─── Contenu principal ───────────────────────────────────────────── -->
        <main
            class="musee-main"
            style="
                color: var(--musee-couleur-corps, inherit);
                background-color: var(--musee-couleur-fond, transparent);
                font-family: var(--musee-font-corps, inherit);
            "
            @click.capture="interceptLiensInternes"
        >
            <!-- Texte d'introduction -->
            <div
                v-if="meta.intro_texte"
                class="musee-intro"
                style="border-color: var(--musee-couleur-accent, currentColor)"
            >
                <p>{{ meta.intro_texte }}</p>
            </div>

            <!-- Sections avec leurs blocs -->
            <section
                v-for="section in sectionsAvecContenu"
                :key="section.id"
                :id="`section-${section.id}`"
                class="musee-section"
            >
                <!-- Titre de section -->
                <h2
                    class="musee-section__titre"
                    style="
                        color: var(--musee-couleur-titre, inherit);
                        font-family: var(--musee-font-titre-section, inherit);
                    "
                >
                    {{ section.label }}
                </h2>

                <!--
                    Conteneur de blocs : supporte deux modes.
                    - Mode canevas (musee_canevas défini) : position:relative + padding-top pour l'aspect ratio.
                      Les blocs sont positionnés en absolute selon leur zone.
                    - Mode libre (backward compat) : grille 1 ou 2 colonnes linéaire.

                    L'inner wrapper utilise display:contents en mode libre pour que les blocs
                    soient des enfants directs de la grille, et position:absolute inset:0 en mode canevas.
                -->
                <div
                    class="musee-section__blocs"
                    :class="sectionBlocsClass(section)"
                    :style="sectionBlocsStyle(section)"
                >
                    <div :style="section.musee_canevas?.zones?.length ? 'position: absolute; inset: 0;' : 'display: contents;'">
                        <div
                            v-for="bloc in section.blocs"
                            :key="bloc.id"
                            :style="blocWrapperStyle(section, bloc)"
                        >
                        <!-- Bloc texte (avec image ancrée optionnelle) -->
                        <div
                            v-if="bloc.type === 'texte' && bloc.contenu"
                            :class="[
                                'musee-texte-bloc',
                                (bloc.contenu as BlocContenuTexte).image_ancree?.image_id ? 'musee-avec-image-ancree' : '',
                            ]"
                        >
                            <!-- Image ancrée flottante -->
                            <figure
                                v-if="(bloc.contenu as BlocContenuTexte).image_ancree?.image_id && imageParId((bloc.contenu as BlocContenuTexte).image_ancree!.image_id!)"
                                :class="[
                                    'musee-image-ancree',
                                    (bloc.contenu as BlocContenuTexte).image_ancree!.position === 'gauche'
                                        ? 'musee-image-ancree--gauche'
                                        : 'musee-image-ancree--droite',
                                ]"
                            >
                                <img
                                    :src="imageParId((bloc.contenu as BlocContenuTexte).image_ancree!.image_id!)!.url"
                                    :alt="imageParId((bloc.contenu as BlocContenuTexte).image_ancree!.image_id!)!.alt"
                                    class="musee-image-ancree__img"
                                    :style="cropStyle(imageParId((bloc.contenu as BlocContenuTexte).image_ancree!.image_id!))"
                                />
                            </figure>

                            <div
                                class="musee-prose"
                                style="font-family: var(--musee-font-corps, inherit)"
                                v-html="(bloc.contenu as BlocContenuTexte).html"
                            />
                            <div class="musee-clearfix" />
                        </div>

                        <!-- Bloc image -->
                        <figure
                            v-else-if="bloc.type === 'image' && bloc.contenu && (bloc.contenu as BlocContenuImage).image_id"
                            class="musee-image-bloc"
                        >
                            <img
                                v-if="imageParId((bloc.contenu as BlocContenuImage).image_id!)"
                                :src="imageParId((bloc.contenu as BlocContenuImage).image_id!)!.url"
                                :alt="(bloc.contenu as BlocContenuImage).alt || imageParId((bloc.contenu as BlocContenuImage).image_id!)!.alt"
                                class="musee-image-bloc__img"
                                :style="{
                                    ...cropStyle(imageParId((bloc.contenu as BlocContenuImage).image_id!)),
                                    ...(bloc.hauteur_px ? { height: `${bloc.hauteur_px}px` } : {}),
                                }"
                            />
                            <figcaption
                                v-if="(bloc.contenu as BlocContenuImage).legende"
                                class="musee-image-bloc__legende"
                                style="
                                    color: var(--musee-couleur-corps, inherit);
                                    font-family: var(--musee-font-legende, inherit);
                                "
                            >
                                {{ (bloc.contenu as BlocContenuImage).legende }}
                            </figcaption>
                        </figure>

                        <!-- Bloc carrousel -->
                        <div
                            v-else-if="bloc.type === 'carrousel' && bloc.contenu && (bloc.contenu as BlocContenuCarrousel).images.length > 0"
                            class="musee-carrousel"
                        >
                            <div class="musee-carrousel__piste-wrap">
                                <div
                                    class="musee-carrousel__piste"
                                    :style="{
                                        transform: `translateX(-${getCarrouselIndex(bloc.id) * 100}%)`,
                                    }"
                                >
                                    <figure
                                        v-for="(item, idx) in (bloc.contenu as BlocContenuCarrousel).images"
                                        :key="idx"
                                        class="musee-carrousel__diapo"
                                    >
                                        <img
                                            v-if="imageParId(item.image_id)"
                                            :src="imageParId(item.image_id)!.url"
                                            :alt="item.alt || imageParId(item.image_id)!.alt"
                                            class="musee-carrousel__img"
                                            :style="{
                                                ...cropStyle(imageParId(item.image_id)),
                                                ...(bloc.hauteur_px ? { height: `${bloc.hauteur_px}px`, maxHeight: `${bloc.hauteur_px}px` } : {}),
                                            }"
                                        />
                                        <figcaption
                                            v-if="item.legende"
                                            class="musee-carrousel__legende"
                                            style="font-family: var(--musee-font-legende, inherit)"
                                        >
                                            {{ item.legende }}
                                        </figcaption>
                                    </figure>
                                </div>

                                <!-- Flèches -->
                                <button
                                    v-if="(bloc.contenu as BlocContenuCarrousel).images.length > 1"
                                    type="button"
                                    class="musee-carrousel__fleche musee-carrousel__fleche--gauche"
                                    aria-label="Image précédente"
                                    @click="setCarrouselIndex(bloc.id, getCarrouselIndex(bloc.id) - 1, (bloc.contenu as BlocContenuCarrousel).images.length)"
                                >
                                    ‹
                                </button>
                                <button
                                    v-if="(bloc.contenu as BlocContenuCarrousel).images.length > 1"
                                    type="button"
                                    class="musee-carrousel__fleche musee-carrousel__fleche--droite"
                                    aria-label="Image suivante"
                                    @click="setCarrouselIndex(bloc.id, getCarrouselIndex(bloc.id) + 1, (bloc.contenu as BlocContenuCarrousel).images.length)"
                                >
                                    ›
                                </button>
                            </div>

                            <!-- Points de navigation -->
                            <div
                                v-if="(bloc.contenu as BlocContenuCarrousel).images.length > 1"
                                class="musee-carrousel__points"
                            >
                                <button
                                    v-for="(_, idx) in (bloc.contenu as BlocContenuCarrousel).images"
                                    :key="idx"
                                    type="button"
                                    :class="[
                                        'musee-carrousel__point',
                                        getCarrouselIndex(bloc.id) === idx ? 'musee-carrousel__point--actif' : '',
                                    ]"
                                    :aria-label="`Image ${idx + 1}`"
                                    @click="setCarrouselIndex(bloc.id, idx, (bloc.contenu as BlocContenuCarrousel).images.length)"
                                />
                            </div>
                        </div>

                        <!-- Bloc vidéo -->
                        <figure
                            v-else-if="bloc.type === 'video' && bloc.contenu"
                            class="musee-video-bloc"
                        >
                            <!-- Vidéo uploadée (HTML5 player) -->
                            <div
                                v-if="(bloc.contenu as BlocContenuVideo).source === 'upload' && (bloc.contenu as BlocContenuVideo).groupe_video_id"
                                class="musee-video__wrap"
                            >
                                <video
                                    :ref="setVideoElem(bloc.id)"
                                    controls
                                    class="musee-video__player"
                                    :src="`/musee/${meta.slug}/video/${bloc.id}`"
                                    preload="metadata"
                                    :style="bloc.hauteur_px ? { maxHeight: `${bloc.hauteur_px}px` } : {}"
                                    @timeupdate="onTimeUpdate(bloc.id, $event)"
                                />

                                <!-- Contrôle de vitesse de lecture -->
                                <div class="musee-video__vitesse">
                                    <span class="musee-video__vitesse-label">Vitesse</span>
                                    <div class="musee-video__vitesse-boutons">
                                        <button
                                            v-for="v in VITESSES_DISPONIBLES"
                                            :key="v"
                                            type="button"
                                            :class="[
                                                'musee-video__vitesse-btn',
                                                (videoSpeeds[bloc.id] ?? 1) === v
                                                    ? 'musee-video__vitesse-btn--actif'
                                                    : '',
                                            ]"
                                            @click="changerVitesse(bloc.id, v)"
                                        >
                                            {{ v === 1 ? '1×' : `${v}×` }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Embed YouTube -->
                            <div
                                v-else-if="(bloc.contenu as BlocContenuVideo).source === 'youtube' && youtubeEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                class="musee-video__embed-wrap"
                            >
                                <iframe
                                    :src="youtubeEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                    class="musee-video__embed"
                                    frameborder="0"
                                    allowfullscreen
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    title="Vidéo YouTube"
                                />
                            </div>

                            <!-- Embed Vimeo -->
                            <div
                                v-else-if="(bloc.contenu as BlocContenuVideo).source === 'vimeo' && vimeoEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                class="musee-video__embed-wrap"
                            >
                                <iframe
                                    :src="vimeoEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                    class="musee-video__embed"
                                    frameborder="0"
                                    allowfullscreen
                                    title="Vidéo Vimeo"
                                />
                            </div>

                            <!-- Légende -->
                            <figcaption
                                v-if="(bloc.contenu as BlocContenuVideo).legende"
                                class="musee-media__legende"
                                style="color: var(--musee-couleur-corps, inherit); font-family: var(--musee-font-legende, inherit);"
                            >
                                {{ (bloc.contenu as BlocContenuVideo).legende }}
                            </figcaption>

                            <!-- Segments / chapitres -->
                            <div
                                v-if="bloc.segments && bloc.segments.length > 0"
                                class="musee-video__segments"
                            >
                                <p class="musee-video__segments-titre">Chapitres</p>
                                <ul class="musee-video__segments-liste">
                                    <li
                                        v-for="seg in bloc.segments"
                                        :key="seg.id"
                                        :class="[
                                            'musee-video__segment',
                                            activeSegmentId(bloc) === seg.id ? 'musee-video__segment--actif' : '',
                                        ]"
                                    >
                                        <button
                                            type="button"
                                            class="musee-video__segment-btn"
                                            @click="cliqueSurSegment(bloc, seg)"
                                        >
                                            <span class="musee-video__segment-temps">
                                                {{ formatTemps(seg.debut_secondes) }}
                                            </span>
                                            <span class="musee-video__segment-label">{{ seg.label }}</span>
                                            <span
                                                v-if="props.sectionsIndex[seg.section_id]"
                                                class="musee-video__segment-section"
                                            >
                                                → {{ props.sectionsIndex[seg.section_id] }}
                                            </span>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </figure>

                        <!-- Bloc audio -->
                        <figure
                            v-else-if="bloc.type === 'audio' && bloc.contenu && ((bloc.contenu as BlocContenuAudio).pistes?.length || (bloc.contenu as BlocContenuAudio).url)"
                            class="musee-audio-bloc"
                        >
                            <!-- Nouveau format : plusieurs pistes, chacune avec titre + lecteur -->
                            <template v-if="(bloc.contenu as BlocContenuAudio).pistes?.length">
                                <div
                                    v-for="(piste, idx) in (bloc.contenu as BlocContenuAudio).pistes"
                                    :key="idx"
                                    class="musee-audio-piste"
                                >
                                    <p
                                        v-if="piste.titre"
                                        class="musee-audio-bloc__titre"
                                        style="color: var(--musee-couleur-titre, inherit); font-family: var(--musee-font-corps, inherit)"
                                    >
                                        {{ piste.titre }}
                                    </p>
                                    <audio
                                        v-if="piste.url"
                                        controls
                                        class="musee-audio-bloc__player"
                                        :src="piste.url"
                                        preload="metadata"
                                    />
                                </div>
                            </template>
                            <!-- Ancien format mono-piste (rétrocompatibilité) -->
                            <template v-else-if="(bloc.contenu as BlocContenuAudio).url">
                                <p
                                    v-if="(bloc.contenu as BlocContenuAudio).titre"
                                    class="musee-audio-bloc__titre"
                                    style="color: var(--musee-couleur-titre, inherit); font-family: var(--musee-font-corps, inherit)"
                                >
                                    {{ (bloc.contenu as BlocContenuAudio).titre }}
                                </p>
                                <audio
                                    controls
                                    class="musee-audio-bloc__player"
                                    :src="(bloc.contenu as BlocContenuAudio).url!"
                                    preload="metadata"
                                />
                            </template>
                            <figcaption
                                v-if="(bloc.contenu as BlocContenuAudio).legende"
                                class="musee-media__legende"
                                style="color: var(--musee-couleur-corps, inherit); font-family: var(--musee-font-legende, inherit);"
                            >
                                {{ (bloc.contenu as BlocContenuAudio).legende }}
                            </figcaption>
                        </figure>

                        <!-- Bloc séparateur -->
                        <hr
                            v-else-if="bloc.type === 'separateur'"
                            class="musee-separateur"
                            style="border-color: var(--musee-couleur-accent, currentColor)"
                        />
                    </div>
                    </div><!-- /inner wrapper (display:contents ou absolute inset-0) -->
                </div>
            </section>

            <!-- Message si aucun contenu -->
            <div
                v-if="sectionsAvecContenu.length === 0"
                class="py-16 text-center text-base"
                style="color: var(--musee-couleur-corps, inherit); opacity: 0.5"
            >
                Ce musée virtuel est en cours de construction.
            </div>
        </main>

        <!-- ─── Pied de page ────────────────────────────────────────────────── -->
        <footer
            class="musee-footer"
            style="color: var(--musee-couleur-corps, inherit)"
        >
            <p class="musee-footer__nom">{{ typeProjet.nom }}</p>
            <p v-if="membres.length > 0" class="musee-footer__membres">
                {{ membres.map((m) => `${m.prenom} ${m.nom}`).join(' · ') }}
            </p>
            <p class="musee-footer__vues">{{ nbVues }} {{ nbVues === 1 ? 'visite' : 'visites' }}</p>
        </footer>
    </div>
</template>

<style scoped>
/* ─── Conteneur racine ─────────────────────────────────────────────────────── */

.musee-public {
    background-color: var(--musee-couleur-fond, #fff);
    min-height: 100vh;
}

/* ─── Hero ─────────────────────────────────────────────────────────────────── */

.musee-hero {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    min-height: 55vw; /* ratio naturel sur mobile */
    max-height: 70vh;
    overflow: hidden;
}

@media (min-width: 640px) {
    .musee-hero {
        min-height: 340px;
    }
}

@media (min-width: 1024px) {
    .musee-hero {
        min-height: 480px;
    }
}

.musee-hero__overlay {
    position: absolute;
    inset: 0;
}

.musee-hero__contenu {
    position: relative;
    z-index: 10;
    padding: 1.25rem 1rem 1.5rem;
}

@media (min-width: 640px) {
    .musee-hero__contenu {
        padding: 2rem 2rem 2.5rem;
        max-width: 56rem;
        width: 100%;
    }
}

.musee-hero__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
    margin-bottom: 0.625rem;
}

.musee-hero__tag {
    display: inline-block;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    background-color: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(4px);
    font-size: 0.6875rem;
    font-weight: 500;
    color: #fff;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.musee-hero__titre {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
    color: #fff;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

@media (min-width: 640px) {
    .musee-hero__titre {
        font-size: 2.25rem;
    }
}

@media (min-width: 1024px) {
    .musee-hero__titre {
        font-size: 2.75rem;
    }
}

.musee-hero__sous-titre {
    margin-top: 0.375rem;
    font-size: 0.9375rem;
    color: rgba(255, 255, 255, 0.88);
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}

@media (min-width: 640px) {
    .musee-hero__sous-titre {
        font-size: 1.125rem;
    }
}

/* ─── Table des matières (onglets horizontaux) ─────────────────────────────── */

.musee-toc__liste {
    display: flex;
    overflow-x: auto;
    white-space: nowrap;
    padding: 0 0.75rem;
    gap: 0;
    /* Cacher la scrollbar tout en gardant le défilement */
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.musee-toc__liste::-webkit-scrollbar {
    display: none;
}

.musee-toc__lien {
    display: inline-block;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--musee-couleur-corps, inherit);
    opacity: 0.6;
    border: none;
    border-bottom: 2px solid transparent;
    background: none;
    cursor: pointer;
    white-space: nowrap;
    transition:
        opacity 0.15s,
        border-color 0.15s;
    flex-shrink: 0;
}

.musee-toc__lien:hover {
    opacity: 0.9;
}

.musee-toc__lien--actif {
    opacity: 1;
    font-weight: 600;
    color: var(--musee-couleur-accent, inherit);
    border-bottom-color: var(--musee-couleur-accent, currentColor);
}

/* ─── Contenu principal ─────────────────────────────────────────────────────── */

.musee-main {
    padding: 1.25rem 1rem 3rem;
}

@media (min-width: 640px) {
    .musee-main {
        padding: 2rem 1.5rem 4rem;
        max-width: 52rem;
        margin-inline: auto;
    }
}

@media (min-width: 1024px) {
    .musee-main {
        padding: 2.5rem 2rem 5rem;
    }
}

/* ─── Introduction ─────────────────────────────────────────────────────────── */

.musee-intro {
    margin-bottom: 2rem;
    padding: 0.875rem 1rem 0.875rem 1.125rem;
    border-left: 3px solid var(--musee-couleur-accent, currentColor);
    font-size: 1rem;
    line-height: 1.7;
}

@media (min-width: 640px) {
    .musee-intro {
        margin-bottom: 2.5rem;
        font-size: 1.0625rem;
    }
}

/* ─── Section ──────────────────────────────────────────────────────────────── */

.musee-section {
    margin-bottom: 2.5rem;
}

@media (min-width: 640px) {
    .musee-section {
        margin-bottom: 3.5rem;
    }
}

.musee-section__titre {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

@media (min-width: 640px) {
    .musee-section__titre {
        font-size: 1.375rem;
        margin-bottom: 1.5rem;
    }
}

/* Blocs : empilement vertical (mobile) / grille sur desktop */

.musee-section__blocs {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

@media (min-width: 768px) {
    .musee-section__blocs--grille {
        display: grid;
        grid-template-columns: var(--grille-template, 1fr 1fr);
        gap: 1.5rem 2rem;
    }
}

/* Mode canevas : ratio d'aspect fixe via padding-top, blocs positionnés en absolute */
.musee-section__canevas {
    /* Le display:flex hérité de .musee-section__blocs est overridden ici */
    display: block;
    width: 100%;
    /* Le padding-top est injecté via le style inline (hauteur_vw%) */
    overflow: hidden;
}

/* Sur mobile : on annule le mode canevas et on empile les blocs verticalement */
@media (max-width: 767px) {
    .musee-section__canevas {
        padding-top: 0 !important;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    /* Inner wrapper absolu → flux normal */
    .musee-section__canevas > div {
        position: static !important;
        width: 100% !important;
        height: auto !important;
        inset: auto !important;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    /* Chaque wrapper de bloc → empilé pleine largeur */
    .musee-section__canevas > div > div {
        position: static !important;
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
    }
}

/* ─── Bloc texte (prose) ───────────────────────────────────────────────────── */

:deep(.musee-prose h2) {
    font-family: var(--musee-font-titre-section, inherit);
    color: var(--musee-couleur-titre, inherit);
    font-size: 1.125rem;
    font-weight: 600;
    margin-top: 1.375rem;
    margin-bottom: 0.5rem;
}

:deep(.musee-prose h3) {
    font-family: var(--musee-font-titre-section, inherit);
    color: var(--musee-couleur-titre, inherit);
    font-size: 1rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.375rem;
}

:deep(.musee-prose p) {
    margin-bottom: 0.875rem;
    line-height: 1.8;
}

:deep(.musee-prose ul) {
    list-style-type: disc;
    padding-left: 1.375rem;
    margin-bottom: 0.875rem;
}

:deep(.musee-prose ol) {
    list-style-type: decimal;
    padding-left: 1.375rem;
    margin-bottom: 0.875rem;
}

:deep(.musee-prose blockquote) {
    border-left: 4px solid var(--musee-couleur-accent, #ccc);
    padding-left: 1rem;
    font-style: italic;
    opacity: 0.85;
    margin: 1rem 0;
}

:deep(.musee-prose a[data-externe='true']) {
    color: var(--musee-couleur-lien-externe, #2563eb);
    text-decoration: underline;
}

:deep(.musee-prose a[data-externe='true'])::after {
    content: ' ↗';
    font-size: 0.75em;
}

:deep(.musee-prose strong) {
    font-weight: 700;
}

:deep(.musee-prose em) {
    font-style: italic;
}

/* ─── Image ancrée / flottante ─────────────────────────────────────────────── */

/* Sur mobile : pas de float, image pleine largeur avant le texte */
.musee-image-ancree {
    width: 100%;
    margin-bottom: 0.75rem;
}

@media (min-width: 640px) {
    .musee-image-ancree {
        max-width: 40%;
        margin-bottom: 0.5rem;
    }

    .musee-image-ancree--gauche {
        float: left;
        margin-right: 1.25rem;
    }

    .musee-image-ancree--droite {
        float: right;
        margin-left: 1.25rem;
    }
}

.musee-image-ancree__img {
    width: 100%;
    height: auto;
    border-radius: 0.375rem;
    object-fit: cover;
}

.musee-clearfix::after {
    content: '';
    display: table;
    clear: both;
}

/* ─── Bloc image autonome ──────────────────────────────────────────────────── */

.musee-image-bloc {
    margin: 0;
    width: 100%;
}

.musee-image-bloc__img {
    width: 100%;
    height: auto;
    border-radius: 0.375rem;
    display: block;
    object-fit: cover;
}

.musee-image-bloc__legende {
    margin-top: 0.5rem;
    text-align: center;
    font-size: 0.8125rem;
    opacity: 0.65;
}

/* ─── Légende commune aux médias ───────────────────────────────────────────── */

.musee-media__legende {
    display: block;
    margin-top: 0.5rem;
    text-align: center;
    font-size: 0.8125rem;
    opacity: 0.65;
}

/* ─── Carrousel ───────────────────────────────────────────────────────────── */

.musee-carrousel {
    width: 100%;
}

.musee-carrousel__piste-wrap {
    position: relative;
    overflow: hidden;
    border-radius: 0.5rem;
}

.musee-carrousel__piste {
    display: flex;
    transition: transform 0.4s ease;
}

.musee-carrousel__diapo {
    min-width: 100%;
    margin: 0;
}

.musee-carrousel__img {
    width: 100%;
    height: 56vw; /* ratio naturel mobile */
    max-height: 22rem;
    object-fit: cover;
    display: block;
}

@media (min-width: 640px) {
    .musee-carrousel__img {
        height: 22rem;
    }
}

/* Hauteur personnalisée via drag (héritée du style inline du wrapper) */
.musee-carrousel__img[style*="--hauteur-px"] {
    height: var(--hauteur-px);
    max-height: var(--hauteur-px);
}

.musee-carrousel__legende {
    text-align: center;
    padding: 0.5rem 1rem;
    font-size: 0.8125rem;
    opacity: 0.75;
    color: var(--musee-couleur-corps, inherit);
}

.musee-carrousel__fleche {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0, 0, 0, 0.45);
    color: white;
    border: none;
    font-size: 2rem;
    line-height: 1;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.musee-carrousel__fleche:hover {
    background-color: rgba(0, 0, 0, 0.65);
}

.musee-carrousel__fleche--gauche {
    left: 0.5rem;
}

.musee-carrousel__fleche--droite {
    right: 0.5rem;
}

.musee-carrousel__points {
    display: flex;
    justify-content: center;
    gap: 0.375rem;
    margin-top: 0.625rem;
}

.musee-carrousel__point {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    border: none;
    background-color: var(--musee-couleur-corps, #999);
    opacity: 0.3;
    cursor: pointer;
    padding: 0;
    transition: opacity 0.2s;
}

.musee-carrousel__point--actif {
    opacity: 1;
    background-color: var(--musee-couleur-accent, currentColor);
}

/* ─── Bloc vidéo ───────────────────────────────────────────────────────────── */

.musee-video-bloc {
    margin: 0;
    width: 100%;
}

.musee-video__wrap {
    border-radius: 0.5rem;
    overflow: hidden;
    background: #000;
}

.musee-video__player {
    width: 100%;
    display: block;
    max-height: 56vw; /* 16:9 approx sur mobile */
}

@media (min-width: 640px) {
    .musee-video__player {
        max-height: 28rem;
    }
}

/* Contrôle de vitesse */
.musee-video__vitesse {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.5rem;
    background-color: rgba(0, 0, 0, 0.75);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.musee-video__vitesse-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255, 255, 255, 0.55);
    white-space: nowrap;
}

.musee-video__vitesse-boutons {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.musee-video__vitesse-btn {
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: none;
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.75rem;
    cursor: pointer;
    transition:
        background-color 0.15s,
        color 0.15s;
}

.musee-video__vitesse-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
}

.musee-video__vitesse-btn--actif {
    background-color: var(--musee-couleur-accent, #c9a040);
    border-color: var(--musee-couleur-accent, #c9a040);
    color: #1a1a2e;
    font-weight: 700;
}

/* Embeds YouTube / Vimeo — ratio 16:9 */
.musee-video__embed-wrap {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    border-radius: 0.5rem;
    overflow: hidden;
}

.musee-video__embed {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: none;
}

/* Segments */
.musee-video__segments {
    margin-top: 0.75rem;
    border: 1px solid var(--musee-couleur-accent, #ccc);
    border-radius: 0.5rem;
    overflow: hidden;
}

.musee-video__segments-titre {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background-color: var(--musee-couleur-accent, #555);
    color: #fff;
    opacity: 0.9;
}

.musee-video__segments-liste {
    list-style: none;
    padding: 0;
    margin: 0;
}

.musee-video__segment {
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    transition: background-color 0.15s;
}

.musee-video__segment:last-child {
    border-bottom: none;
}

.musee-video__segment--actif {
    background-color: color-mix(in srgb, var(--musee-couleur-accent, #4f6ef7) 12%, transparent);
}

.musee-video__segment-btn {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.625rem 0.75rem;
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    font-size: 0.875rem;
    color: var(--musee-couleur-corps, inherit);
    transition: background-color 0.15s;
}

.musee-video__segment-btn:hover {
    background-color: rgba(0, 0, 0, 0.04);
}

.musee-video__segment-temps {
    font-family: monospace;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--musee-couleur-accent, #4f6ef7);
    white-space: nowrap;
    min-width: 2.5rem;
}

.musee-video__segment-label {
    flex: 1;
    font-weight: 500;
}

.musee-video__segment-section {
    font-size: 0.75rem;
    opacity: 0.6;
    white-space: nowrap;
}

/* ─── Bloc audio ────────────────────────────────────────────────────────────── */

.musee-audio-bloc {
    margin: 0;
    width: 100%;
}

.musee-audio-bloc__titre {
    font-size: 0.9375rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.musee-audio-bloc__player {
    width: 100%;
    display: block;
}

.musee-audio-piste {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.musee-audio-piste + .musee-audio-piste {
    margin-top: 0.875rem;
    padding-top: 0.875rem;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
}

/* ─── Séparateur ───────────────────────────────────────────────────────────── */

.musee-separateur {
    border: none;
    border-top: 1px solid;
    margin: 0.5rem 0;
    opacity: 0.25;
}

/* ─── Pied de page ─────────────────────────────────────────────────────────── */

.musee-footer {
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    padding: 2rem 1rem 3rem;
    text-align: center;
    opacity: 0.65;
    max-width: 52rem;
    margin-inline: auto;
}

.musee-footer__nom {
    font-size: 0.9375rem;
    font-weight: 600;
}

.musee-footer__membres {
    margin-top: 0.375rem;
    font-size: 0.875rem;
}

.musee-footer__vues {
    margin-top: 0.625rem;
    font-size: 0.75rem;
    opacity: 0.7;
}
</style>
