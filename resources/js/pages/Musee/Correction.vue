<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { ArrowLeft, CheckCircle2, ExternalLink, Globe, Lock } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'

// ─── Types ────────────────────────────────────────────────────────────────────

type CategorieMeta = { id: number; nom: string }

type Meta = {
    slug: string
    intro_texte: string | null
    entete_titre: string | null
    entete_sous_titre: string | null
    entete_overlay_couleur: string | null
    entete_image_position: string
    entete_image_path: string | null
    periode: CategorieMeta | null
    thematique: CategorieMeta | null
    region: CategorieMeta | null
}

type BlocContenuTexte = { html: string; image_ancree?: { image_id: number | null; position: string } | null }
type BlocContenuImage = { image_id: number | null; legende: string; alt: string }
type CarrouselItem = { image_id: number; legende: string; alt: string }
type BlocContenuCarrousel = { images: CarrouselItem[] }
type BlocContenuVideo = {
    source: 'upload' | 'youtube' | 'vimeo'
    groupe_video_id: number | null
    url_externe: string | null
    legende: string
}
type VideoSegment = { id: number; section_id: number; debut_secondes: number; fin_secondes: number; label: string }

type Bloc = {
    id: number
    type: 'texte' | 'image' | 'separateur' | 'carrousel' | 'video'
    contenu: BlocContenuTexte | BlocContenuImage | BlocContenuCarrousel | BlocContenuVideo | null
    ordre: number
    segments: VideoSegment[]
    aDesLiensExternes: boolean
}

type Section = { id: number; label: string; ordre: number; blocs: Bloc[] }

type MuseeImage = { id: number; url: string; alt: string; legende: string; crop_data?: { x: number; y: number } | null }

type CorrectionCritere = { id: number; points: string | null; commentaire: string | null; verifie: boolean }

type Critere = {
    id: number
    type: string
    contenu: string
    pointage: number
    section_id: number | null
    ordre: number
    correction: CorrectionCritere | null
    correctionsIndividuelles: (CorrectionCritere & { etudiant: string })[]
}

type Props = {
    cours: { id: number; nom_cours: string; code: string; groupe: string }
    classe: { id: number; code: string; cours_id: number }
    groupe: { id: number; code: string; classe_id: number }
    typeProjet: { id: number; nom: string }
    projet: { id: number; titre_projet: string | null; verrouille: boolean; remis_le: string | null }
    meta: Meta
    sections: Section[]
    images: MuseeImage[]
    cssVars: Record<string, string>
    membres: string[]
    publication: { est_publie: boolean; publie_le: string | null }
    criteres: Critere[]
}

const props = defineProps<Props>()

// ─── Image utilities ──────────────────────────────────────────────────────────

const imageMap = computed(() => {
    const map = new Map<number, MuseeImage>()
    props.images.forEach((img) => map.set(img.id, img))
    return map
})

function imageParId(id: number | null): MuseeImage | undefined {
    return id ? imageMap.value.get(id) : undefined
}

function cropStyle(img: MuseeImage | undefined): string {
    if (!img?.crop_data) return 'object-fit: cover'
    return `object-fit: cover; object-position: ${img.crop_data.x}% ${img.crop_data.y}%`
}

// ─── Carrousel ────────────────────────────────────────────────────────────────

const carrouselIndexes = ref<Record<number, number>>({})

function getCarrouselIndex(blocId: number): number {
    return carrouselIndexes.value[blocId] ?? 0
}

function setCarrouselIndex(blocId: number, index: number, total: number): void {
    const clamped = Math.max(0, Math.min(total - 1, index))
    carrouselIndexes.value = { ...carrouselIndexes.value, [blocId]: clamped }
}

// ─── URLs ──────────────────────────────────────────────────────────────────────

const editeurUrl = computed(() =>
    `/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${props.groupe.id}/projets/${props.typeProjet.id}`,
)

const publicationUrl = computed(() =>
    `/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${props.groupe.id}/projets/${props.typeProjet.id}/musee-publication`,
)

const gallerieUrl = computed(() => (props.meta.slug ? `/musee/${props.meta.slug}` : '/musee'))

// ─── Critères ─────────────────────────────────────────────────────────────────

/** Critères globaux (non liés à une section). */
const criteresGlobaux = computed(() => props.criteres.filter((c) => c.section_id === null))

/** Critères filtrés par section. */
function criteresDeSection(sectionId: number): Critere[] {
    return props.criteres.filter((c) => c.section_id === sectionId)
}

/** Score total obtenu selon les corrections existantes. */
const scoreTotal = computed(() =>
    props.criteres.reduce((sum, c) => {
        if (!c.correction) return sum
        if (c.correction.verifie && c.correction.points === null) return sum + c.pointage
        return sum + (parseFloat(c.correction.points ?? '0') || 0)
    }, 0),
)

/** Pointage maximal possible. */
const pointageMax = computed(() => props.criteres.reduce((sum, c) => sum + c.pointage, 0))

/** Nombre de blocs avec des liens externes dans tout le projet. */
const nbBlocsAvecLiensExternes = computed(() =>
    props.sections.flatMap((s) => s.blocs).filter((b) => b.aDesLiensExternes).length,
)

// ─── Utilitaires ──────────────────────────────────────────────────────────────

function youtubeEmbedUrl(url: string): string {
    const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([^&?/\s]+)/)
    return m ? `https://www.youtube.com/embed/${m[1]}` : ''
}

function vimeoEmbedUrl(url: string): string {
    const m = url.match(/vimeo\.com\/(\d+)/)
    return m ? `https://player.vimeo.com/video/${m[1]}` : ''
}

function formatTemps(secondes: number): string {
    const m = Math.floor(secondes / 60)
    const s = secondes % 60
    return `${m}:${s.toString().padStart(2, '0')}`
}

function togglePublication() {
    router.post(publicationUrl.value, {}, { preserveScroll: true })
}
</script>

<template>
    <AppLayout :title="`Correction — ${meta.entete_titre ?? typeProjet.nom}`">
        <Head :title="`Correction — ${meta.entete_titre ?? typeProjet.nom}`" />

        <div class="flex h-[calc(100vh-3.5rem)] overflow-hidden">
            <!-- ─── Panneau gauche — rendu du musée ──────────────────────────── -->
            <div class="flex-1 overflow-y-auto border-r">
                <div class="musee-correction-rendu" :style="cssVars">
                    <!-- En-tête du musée -->
                    <header
                        class="relative flex min-h-[20vh] flex-col items-center justify-end overflow-hidden pb-6"
                        :style="{
                            backgroundImage: meta.entete_image_path
                                ? `url(/storage/${meta.entete_image_path})`
                                : undefined,
                            backgroundSize: 'cover',
                            backgroundPosition:
                                meta.entete_image_position === 'top'
                                    ? 'center top'
                                    : meta.entete_image_position === 'bottom'
                                      ? 'center bottom'
                                      : 'center center',
                            backgroundColor: meta.entete_image_path
                                ? undefined
                                : 'var(--musee-couleur-accent, hsl(220 14% 28%))',
                        }"
                    >
                        <div
                            v-if="meta.entete_image_path"
                            class="absolute inset-0"
                            :style="{ backgroundColor: meta.entete_overlay_couleur ?? 'rgba(0,0,0,0.4)' }"
                        />
                        <div class="relative z-10 px-6 text-center">
                            <h1
                                v-if="meta.entete_titre"
                                class="text-2xl font-bold text-white drop-shadow-md"
                                style="font-family: var(--musee-font-titre-page, inherit)"
                            >
                                {{ meta.entete_titre }}
                            </h1>
                            <p
                                v-if="meta.entete_sous_titre"
                                class="mt-1 text-sm text-white/90"
                            >
                                {{ meta.entete_sous_titre }}
                            </p>
                        </div>
                    </header>

                    <!-- Contenu principal -->
                    <main class="mx-auto max-w-2xl px-6 py-8">
                        <div
                            v-if="meta.intro_texte"
                            class="mb-8 rounded-lg border-l-4 py-3 pl-4 text-sm"
                            style="border-color: var(--musee-couleur-accent, currentColor)"
                        >
                            {{ meta.intro_texte }}
                        </div>

                        <section
                            v-for="section in sections"
                            :key="section.id"
                            :id="`section-${section.id}`"
                            class="mb-10"
                        >
                            <h2
                                class="mb-4 text-lg font-semibold"
                                style="color: var(--musee-couleur-titre, inherit); font-family: var(--musee-font-titre-section, inherit)"
                            >
                                {{ section.label }}
                            </h2>

                            <div
                                v-for="bloc in section.blocs"
                                :key="bloc.id"
                                :class="['mb-5', bloc.aDesLiensExternes ? 'correction-bloc-liens' : '']"
                            >
                                <!-- Badge liens externes -->
                                <div
                                    v-if="bloc.aDesLiensExternes"
                                    class="correction-lien-badge"
                                >
                                    <ExternalLink class="h-3 w-3" />
                                    Contient des liens externes
                                </div>

                                <!-- Bloc texte -->
                                <div
                                    v-if="bloc.type === 'texte' && bloc.contenu"
                                    :class="['musee-prose correction-prose', bloc.aDesLiensExternes ? 'correction-prose--liens' : '']"
                                    v-html="(bloc.contenu as BlocContenuTexte).html"
                                />

                                <!-- Bloc image -->
                                <figure
                                    v-else-if="bloc.type === 'image' && bloc.contenu && (bloc.contenu as BlocContenuImage).image_id"
                                    class="mx-auto max-w-lg"
                                >
                                    <img
                                        v-if="imageParId((bloc.contenu as BlocContenuImage).image_id!)"
                                        :src="imageParId((bloc.contenu as BlocContenuImage).image_id!)!.url"
                                        :alt="(bloc.contenu as BlocContenuImage).alt"
                                        class="w-full rounded-lg"
                                        :style="cropStyle(imageParId((bloc.contenu as BlocContenuImage).image_id!))"
                                    />
                                    <figcaption
                                        v-if="(bloc.contenu as BlocContenuImage).legende"
                                        class="mt-1 text-center text-xs opacity-70"
                                    >
                                        {{ (bloc.contenu as BlocContenuImage).legende }}
                                    </figcaption>
                                </figure>

                                <!-- Bloc carrousel -->
                                <div
                                    v-else-if="bloc.type === 'carrousel' && bloc.contenu && (bloc.contenu as BlocContenuCarrousel).images.length > 0"
                                    class="relative overflow-hidden rounded-lg"
                                >
                                    <div class="flex overflow-hidden">
                                        <figure
                                            v-for="(item, idx) in (bloc.contenu as BlocContenuCarrousel).images"
                                            v-show="getCarrouselIndex(bloc.id) === idx"
                                            :key="idx"
                                            class="min-w-full"
                                        >
                                            <img
                                                v-if="imageParId(item.image_id)"
                                                :src="imageParId(item.image_id)!.url"
                                                :alt="item.alt"
                                                class="h-56 w-full object-cover"
                                                :style="cropStyle(imageParId(item.image_id))"
                                            />
                                        </figure>
                                    </div>
                                    <div class="flex justify-center gap-1 py-2">
                                        <button
                                            v-for="(_, idx) in (bloc.contenu as BlocContenuCarrousel).images"
                                            :key="idx"
                                            type="button"
                                            :class="['h-1.5 w-1.5 rounded-full', getCarrouselIndex(bloc.id) === idx ? 'bg-gray-700' : 'bg-gray-300']"
                                            @click="setCarrouselIndex(bloc.id, idx, (bloc.contenu as BlocContenuCarrousel).images.length)"
                                        />
                                    </div>
                                </div>

                                <!-- Bloc vidéo -->
                                <figure
                                    v-else-if="bloc.type === 'video' && bloc.contenu"
                                    class="w-full"
                                >
                                    <div
                                        v-if="(bloc.contenu as BlocContenuVideo).source === 'youtube' && youtubeEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                        class="relative h-0 overflow-hidden rounded-lg"
                                        style="padding-bottom: 56.25%"
                                    >
                                        <iframe
                                            :src="youtubeEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                            class="absolute inset-0 h-full w-full border-0"
                                            allowfullscreen
                                            title="YouTube"
                                        />
                                    </div>
                                    <div
                                        v-else-if="(bloc.contenu as BlocContenuVideo).source === 'vimeo' && vimeoEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                        class="relative h-0 overflow-hidden rounded-lg"
                                        style="padding-bottom: 56.25%"
                                    >
                                        <iframe
                                            :src="vimeoEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                            class="absolute inset-0 h-full w-full border-0"
                                            allowfullscreen
                                            title="Vimeo"
                                        />
                                    </div>
                                    <div
                                        v-else
                                        class="flex h-28 items-center justify-center rounded-lg bg-gray-100 text-sm text-gray-400"
                                    >
                                        Vidéo uploadée ({{ (bloc.contenu as BlocContenuVideo).groupe_video_id ? 'ID ' + (bloc.contenu as BlocContenuVideo).groupe_video_id : 'non liée' }})
                                    </div>
                                    <!-- Segments -->
                                    <div
                                        v-if="bloc.segments && bloc.segments.length > 0"
                                        class="mt-2 rounded border text-xs"
                                    >
                                        <p class="border-b bg-gray-50 px-2 py-1 font-semibold">Chapitres</p>
                                        <ul>
                                            <li
                                                v-for="seg in bloc.segments"
                                                :key="seg.id"
                                                class="flex items-center gap-2 border-b px-2 py-1 last:border-b-0"
                                            >
                                                <span class="font-mono text-gray-500">{{ formatTemps(seg.debut_secondes) }}</span>
                                                <span>{{ seg.label }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </figure>

                                <!-- Séparateur -->
                                <hr
                                    v-else-if="bloc.type === 'separateur'"
                                    class="my-6 border-t opacity-30"
                                    style="border-color: var(--musee-couleur-accent, currentColor)"
                                />
                            </div>
                        </section>
                    </main>
                </div>
            </div>

            <!-- ─── Panneau droit — correction ───────────────────────────────── -->
            <aside class="flex w-80 shrink-0 flex-col overflow-hidden bg-background">
                <!-- Header panneau correction -->
                <div class="border-b px-4 py-3">
                    <Link
                        :href="editeurUrl"
                        class="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft class="h-3 w-3" />
                        Retour à l'éditeur
                    </Link>
                    <p class="text-sm font-semibold">{{ meta.entete_titre ?? typeProjet.nom }}</p>
                    <p class="text-xs text-muted-foreground">Groupe {{ groupe.code }}</p>
                    <p v-if="membres.length > 0" class="mt-0.5 text-xs text-muted-foreground">
                        {{ membres.join(' · ') }}
                    </p>
                </div>

                <!-- Publication -->
                <div class="border-b px-4 py-3">
                    <p class="mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Publication
                    </p>
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                                publication.est_publie
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-gray-100 text-gray-600',
                            ]"
                        >
                            <CheckCircle2 v-if="publication.est_publie" class="h-3.5 w-3.5" />
                            <Lock v-else class="h-3.5 w-3.5" />
                            {{ publication.est_publie ? 'Publié' : 'Non publié' }}
                        </span>
                        <button
                            type="button"
                            class="rounded border px-2.5 py-1 text-xs transition-colors hover:bg-muted"
                            @click="togglePublication"
                        >
                            {{ publication.est_publie ? 'Dépublier' : 'Publier' }}
                        </button>
                    </div>
                    <a
                        v-if="publication.est_publie"
                        :href="gallerieUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-2 flex items-center gap-1 text-xs text-blue-600 hover:underline"
                    >
                        <Globe class="h-3 w-3" />
                        Voir dans la galerie
                    </a>
                </div>

                <!-- Liens externes -->
                <div
                    v-if="nbBlocsAvecLiensExternes > 0"
                    class="border-b bg-orange-50 px-4 py-3"
                >
                    <div class="flex items-center gap-1.5 text-xs font-medium text-orange-700">
                        <ExternalLink class="h-3.5 w-3.5" />
                        {{ nbBlocsAvecLiensExternes }} bloc{{ nbBlocsAvecLiensExternes > 1 ? 's' : '' }} avec liens externes
                    </div>
                    <p class="mt-0.5 text-xs text-orange-600">
                        Surlignés en orange dans le rendu.
                    </p>
                </div>

                <!-- Score global -->
                <div
                    v-if="criteres.length > 0"
                    class="border-b px-4 py-3"
                >
                    <p class="mb-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Score
                    </p>
                    <p class="text-lg font-bold">
                        {{ scoreTotal.toFixed(1) }}
                        <span class="text-sm font-normal text-muted-foreground">/ {{ pointageMax.toFixed(1) }}</span>
                    </p>
                </div>

                <!-- Critères -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="criteres.length === 0" class="px-4 py-6 text-center text-xs text-muted-foreground">
                        Aucun critère de correction défini pour ce type de projet.
                    </div>

                    <template v-else>
                        <!-- Critères globaux -->
                        <div
                            v-if="criteresGlobaux.length > 0"
                            class="border-b"
                        >
                            <p class="px-4 pt-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                Critères globaux
                            </p>
                            <div
                                v-for="critere in criteresGlobaux"
                                :key="critere.id"
                                class="border-t px-4 py-2"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs leading-snug">{{ critere.contenu }}</p>
                                    <span class="shrink-0 text-xs font-semibold text-muted-foreground">
                                        {{ critere.pointage }}pts
                                    </span>
                                </div>
                                <div
                                    v-if="critere.correction"
                                    :class="[
                                        'mt-1 flex items-center gap-1 text-xs',
                                        critere.correction.verifie ? 'text-emerald-600' : 'text-muted-foreground',
                                    ]"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    <span>{{ critere.correction.verifie ? 'Accordé' : '' }}</span>
                                    <span v-if="critere.correction.points">{{ critere.correction.points }}pts</span>
                                </div>
                                <ul v-if="critere.correctionsIndividuelles.length > 0" class="mt-1 space-y-0.5">
                                    <li
                                        v-for="individuelle in critere.correctionsIndividuelles"
                                        :key="individuelle.id"
                                        :class="['flex items-center gap-1 text-xs', individuelle.verifie ? 'text-emerald-600' : 'text-muted-foreground']"
                                    >
                                        <CheckCircle2 class="h-3 w-3" />
                                        <span class="font-medium">{{ individuelle.etudiant }}</span>
                                        <span v-if="individuelle.points">{{ individuelle.points }}pts</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Critères par section -->
                        <div
                            v-for="section in sections"
                            :key="section.id"
                        >
                            <div
                                v-if="criteresDeSection(section.id).length > 0"
                                class="border-b"
                            >
                                <p class="px-4 pt-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                    {{ section.label }}
                                </p>
                                <div
                                    v-for="critere in criteresDeSection(section.id)"
                                    :key="critere.id"
                                    class="border-t px-4 py-2"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-xs leading-snug">{{ critere.contenu }}</p>
                                        <span class="shrink-0 text-xs font-semibold text-muted-foreground">
                                            {{ critere.pointage }}pts
                                        </span>
                                    </div>
                                    <div
                                        v-if="critere.correction"
                                        :class="[
                                            'mt-1 flex items-center gap-1 text-xs',
                                            critere.correction.verifie ? 'text-emerald-600' : 'text-muted-foreground',
                                        ]"
                                    >
                                        <CheckCircle2 class="h-3 w-3" />
                                        <span v-if="critere.correction.points">{{ critere.correction.points }}pts</span>
                                    </div>
                                    <ul v-if="critere.correctionsIndividuelles.length > 0" class="mt-1 space-y-0.5">
                                        <li
                                            v-for="individuelle in critere.correctionsIndividuelles"
                                            :key="individuelle.id"
                                            :class="['flex items-center gap-1 text-xs', individuelle.verifie ? 'text-emerald-600' : 'text-muted-foreground']"
                                        >
                                            <CheckCircle2 class="h-3 w-3" />
                                            <span class="font-medium">{{ individuelle.etudiant }}</span>
                                            <span v-if="individuelle.points">{{ individuelle.points }}pts</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Rendu du musée côté correction */
.musee-correction-rendu {
    font-family: system-ui, sans-serif;
    color: var(--musee-couleur-corps, inherit);
}

/* Prose standard */
:deep(.musee-prose p) {
    margin-bottom: 0.75rem;
    line-height: 1.75;
    font-size: 0.9375rem;
}

:deep(.musee-prose h2) {
    font-family: var(--musee-font-titre-section, inherit);
    font-size: 1.15rem;
    font-weight: 600;
    margin-top: 1.25rem;
    margin-bottom: 0.375rem;
}

:deep(.musee-prose ul) {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin-bottom: 0.75rem;
}

:deep(.musee-prose ol) {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin-bottom: 0.75rem;
}

:deep(.musee-prose strong) {
    font-weight: 700;
}

/* Liens externes — style normal */
:deep(.musee-prose a[data-externe='true']) {
    color: #2563eb;
    text-decoration: underline;
}

/* Liens externes — mode correction : surlignés en orange */
:deep(.correction-prose--liens a[data-externe='true']) {
    background-color: rgba(234, 88, 12, 0.12);
    color: #c2410c;
    border-bottom: 2px solid #ea580c;
    text-decoration: none;
    padding: 0 2px;
    border-radius: 2px;
}

:deep(.correction-prose--liens a[data-externe='true'])::after {
    content: ' ↗';
    font-size: 0.75em;
}

/* Indicateur de bloc avec liens externes */
.correction-bloc-liens {
    border-left: 3px solid #ea580c;
    padding-left: 0.75rem;
    background-color: rgba(234, 88, 12, 0.04);
    border-radius: 0 0.25rem 0.25rem 0;
}

.correction-lien-badge {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: #c2410c;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.375rem;
}
</style>
