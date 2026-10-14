<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { BookOpen, Clock, Compass } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import MuseePublicNav from '@/components/musee/MuseePublicNav.vue'

// ─── Types ────────────────────────────────────────────────────────────────────

type Categorie = { id: number; nom: string }

type Musee = {
    slug: string
    titre: string | null
    intro_texte: string | null
    image_path: string | null
    periode: Categorie | null
    thematique: Categorie | null
    region: Categorie | null
    membres: string[]
}

type PaginationLink = { url: string | null; label: string; active: boolean }

type Pagination = {
    data: Musee[]
    links: PaginationLink[]
    current_page: number
    last_page: number
    total: number
    from: number | null
    to: number | null
}

type Filtres = {
    epoque_ids: number[]
    region_ids: number[]
    thematique_ids: number[]
}

type Props = {
    musees: Pagination
    filtres: Filtres
    options: {
        epoques: Categorie[]
        regions: Categorie[]
        thematiques: Categorie[]
    }
}

const props = defineProps<Props>()

// ─── Filtres ──────────────────────────────────────────────────────────────────

const filtresActifs = ref<Filtres>({
    epoque_ids: [...props.filtres.epoque_ids],
    region_ids: [...props.filtres.region_ids],
    thematique_ids: [...props.filtres.thematique_ids],
})

/** Catégorie de filtre ouverte dans le panneau déroulant. */
const categorieOuverte = ref<'epoque' | 'region' | 'theme' | null>(null)

const aucunFiltre = computed(
    () =>
        !filtresActifs.value.epoque_ids.length &&
        !filtresActifs.value.region_ids.length &&
        !filtresActifs.value.thematique_ids.length,
)

// Debounce pour éviter des navigations en rafale lors de clics rapides en multi-sélection
let navigationTimer: ReturnType<typeof setTimeout> | null = null

watch(
    filtresActifs,
    (val) => {
        if (navigationTimer) clearTimeout(navigationTimer)
        navigationTimer = setTimeout(() => {
            const params: Record<string, number[]> = {}
            if (val.epoque_ids.length) params.epoque_ids = val.epoque_ids
            if (val.region_ids.length) params.region_ids = val.region_ids
            if (val.thematique_ids.length) params.thematique_ids = val.thematique_ids
            router.get('/musee/explorer', params, { preserveState: true, replace: true })
        }, 300)
    },
    { deep: true },
)

function reinitialiserFiltres() {
    filtresActifs.value = { epoque_ids: [], region_ids: [], thematique_ids: [] }
    categorieOuverte.value = null
}

/**
 * Ouvre ou ferme le panneau d'une catégorie de filtre.
 * Si la même est cliquée deux fois, on la ferme.
 */
function toggleCategorie(categorie: 'epoque' | 'region' | 'theme'): void {
    categorieOuverte.value = categorieOuverte.value === categorie ? null : categorie
}

/**
 * Ajoute ou retire un ID du tableau de filtre correspondant.
 * Le panneau reste volontairement ouvert après sélection pour permettre
 * la multi-sélection sans avoir à le rouvrir entre chaque choix.
 */
function toggleFiltre(key: keyof Filtres, id: number): void {
    const arr = filtresActifs.value[key]
    const idx = arr.indexOf(id)
    if (idx === -1) {
        arr.push(id)
    } else {
        arr.splice(idx, 1)
    }
}

// ─── Utilitaires ──────────────────────────────────────────────────────────────

/**
 * Retourne l'URL de stockage de l'image de couverture d'un musée.
 */
function imageUrl(path: string | null): string | null {
    return path ? `/storage/${path}` : null
}
</script>

<template>
    <Head title="L'Explorateur — Musée virtuel" />

    <div class="explorateur">
        <!-- ─── Navigation ───────────────────────────────────────────────────── -->
        <MuseePublicNav active="explorer" />

        <!-- ─── En-tête ──────────────────────────────────────────────────────── -->
        <header class="explorateur-entete">
            <div class="explorateur-entete__inner">
                <p class="explorateur-entete__fil">
                    <Link href="/musee" class="explorateur-entete__fil-lien">NAVIGATION</Link>
                    <span class="explorateur-entete__fil-sep">/</span>
                    <Link href="/musee" class="explorateur-entete__fil-lien">ARCHIVE BORÉALE</Link>
                    <span class="explorateur-entete__fil-sep">/</span>
                    <span>EXPLORATEUR</span>
                </p>
                <h1 class="explorateur-entete__titre">L'Explorateur</h1>
                <p class="explorateur-entete__sous-titre">
                    Naviguez à travers les strates du temps et de l'espace boréal.
                    Découvrez les récits qui ont forgé notre identité nordique.
                </p>
            </div>
        </header>

        <!-- ─── Tuiles de catégorie / filtres ──────────────────────────────── -->
        <div class="filtres-tuiles">
            <div class="filtres-tuiles__inner">
                <!-- Époques -->
                <button
                    type="button"
                    :class="['filtres-tuile', filtresActifs.epoque_ids.length > 0 || categorieOuverte === 'epoque' ? 'filtres-tuile--actif' : '']"
                    @click="toggleCategorie('epoque')"
                >
                    <Clock class="filtres-tuile__icone" />
                    <span class="filtres-tuile__label">ÉPOQUES</span>
                    <span
                        v-if="filtresActifs.epoque_ids.length > 0"
                        class="filtres-tuile__badge"
                    >{{ filtresActifs.epoque_ids.length }}</span>
                </button>

                <!-- Régions -->
                <button
                    type="button"
                    :class="['filtres-tuile', filtresActifs.region_ids.length > 0 || categorieOuverte === 'region' ? 'filtres-tuile--actif' : '']"
                    @click="toggleCategorie('region')"
                >
                    <Compass class="filtres-tuile__icone" />
                    <span class="filtres-tuile__label">RÉGIONS</span>
                    <span
                        v-if="filtresActifs.region_ids.length > 0"
                        class="filtres-tuile__badge"
                    >{{ filtresActifs.region_ids.length }}</span>
                </button>

                <!-- Thèmes -->
                <button
                    type="button"
                    :class="['filtres-tuile', filtresActifs.thematique_ids.length > 0 || categorieOuverte === 'theme' ? 'filtres-tuile--actif' : '']"
                    @click="toggleCategorie('theme')"
                >
                    <BookOpen class="filtres-tuile__icone" />
                    <span class="filtres-tuile__label">THÈMES</span>
                    <span
                        v-if="filtresActifs.thematique_ids.length > 0"
                        class="filtres-tuile__badge"
                    >{{ filtresActifs.thematique_ids.length }}</span>
                </button>
            </div>

            <!-- Réinitialiser -->
            <div v-if="!aucunFiltre" class="filtres-reinit-wrap">
                <button
                    type="button"
                    class="filtres-reinit"
                    @click="reinitialiserFiltres"
                >
                    Réinitialiser les filtres
                </button>
            </div>

            <!-- Panneau déroulant de sélection -->
            <Transition name="panneau">
                <div
                    v-if="categorieOuverte !== null"
                    class="filtres-panneau"
                >
                    <div class="filtres-panneau__inner">
                        <!-- Époques -->
                        <template v-if="categorieOuverte === 'epoque'">
                            <template v-if="options.epoques.length > 0">
                                <button
                                    type="button"
                                    :class="['filtres-chip', filtresActifs.epoque_ids.length === 0 ? 'filtres-chip--actif' : '']"
                                    @click="filtresActifs.epoque_ids = []"
                                >
                                    Toutes les époques
                                </button>
                                <button
                                    v-for="e in options.epoques"
                                    :key="e.id"
                                    type="button"
                                    :class="['filtres-chip', filtresActifs.epoque_ids.includes(e.id) ? 'filtres-chip--actif' : '']"
                                    @click="toggleFiltre('epoque_ids', e.id)"
                                >
                                    {{ e.nom }}
                                </button>
                            </template>
                            <span v-else class="filtres-vide">Aucune époque disponible pour le moment.</span>
                        </template>

                        <!-- Régions -->
                        <template v-if="categorieOuverte === 'region'">
                            <template v-if="options.regions.length > 0">
                                <button
                                    type="button"
                                    :class="['filtres-chip', filtresActifs.region_ids.length === 0 ? 'filtres-chip--actif' : '']"
                                    @click="filtresActifs.region_ids = []"
                                >
                                    Toutes les régions
                                </button>
                                <button
                                    v-for="r in options.regions"
                                    :key="r.id"
                                    type="button"
                                    :class="['filtres-chip', filtresActifs.region_ids.includes(r.id) ? 'filtres-chip--actif' : '']"
                                    @click="toggleFiltre('region_ids', r.id)"
                                >
                                    {{ r.nom }}
                                </button>
                            </template>
                            <span v-else class="filtres-vide">Aucune région disponible pour le moment.</span>
                        </template>

                        <!-- Thèmes -->
                        <template v-if="categorieOuverte === 'theme'">
                            <template v-if="options.thematiques.length > 0">
                                <button
                                    type="button"
                                    :class="['filtres-chip', filtresActifs.thematique_ids.length === 0 ? 'filtres-chip--actif' : '']"
                                    @click="filtresActifs.thematique_ids = []"
                                >
                                    Tous les thèmes
                                </button>
                                <button
                                    v-for="t in options.thematiques"
                                    :key="t.id"
                                    type="button"
                                    :class="['filtres-chip', filtresActifs.thematique_ids.includes(t.id) ? 'filtres-chip--actif' : '']"
                                    @click="toggleFiltre('thematique_ids', t.id)"
                                >
                                    {{ t.nom }}
                                </button>
                            </template>
                            <span v-else class="filtres-vide">Aucun thème disponible pour le moment.</span>
                        </template>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- ─── Grille de récits ─────────────────────────────────────────────── -->
        <main class="explorer-main">
            <div class="explorer-main__inner">
                <!-- Récit vedette (premier résultat en grand) -->
                <template v-if="musees.data.length > 0">
                    <p
                        v-if="musees.total > 0"
                        class="explorer-compteur"
                    >
                        {{ musees.total }} récit{{ musees.total > 1 ? 's' : '' }}
                        <template v-if="!aucunFiltre"> correspondant{{ musees.total > 1 ? 's' : '' }}</template>
                    </p>

                    <div class="explorer-grille">
                        <Link
                            v-for="musee in musees.data"
                            :key="musee.slug"
                            :href="`/musee/${musee.slug}`"
                            class="explorer-carte"
                        >
                            <!-- Image -->
                            <div class="explorer-carte__image-wrap">
                                <img
                                    v-if="imageUrl(musee.image_path)"
                                    :src="imageUrl(musee.image_path)!"
                                    :alt="musee.titre ?? 'Récit archivé'"
                                    class="explorer-carte__image"
                                />
                                <div
                                    v-else
                                    class="explorer-carte__image-placeholder"
                                />
                            </div>

                            <!-- Contenu -->
                            <div class="explorer-carte__corps">
                                <div
                                    v-if="musee.periode || musee.thematique || musee.region"
                                    class="explorer-carte__tags"
                                >
                                    <span v-if="musee.periode" class="explorer-carte__tag">{{ musee.periode.nom }}</span>
                                    <span v-if="musee.thematique" class="explorer-carte__tag">{{ musee.thematique.nom }}</span>
                                    <span v-if="musee.region" class="explorer-carte__tag">{{ musee.region.nom }}</span>
                                </div>

                                <h2 class="explorer-carte__titre">
                                    {{ musee.titre ?? 'Récit archivé' }}
                                </h2>

                                <p
                                    v-if="musee.intro_texte"
                                    class="explorer-carte__intro"
                                >
                                    {{ musee.intro_texte }}
                                </p>

                                <p
                                    v-if="musee.membres.length > 0"
                                    class="explorer-carte__auteurs"
                                >
                                    {{ musee.membres.join(' · ') }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </template>

                <!-- Aucun résultat -->
                <div
                    v-else
                    class="explorer-vide"
                >
                    <p v-if="aucunFiltre">Aucun récit n'est publié pour le moment.</p>
                    <p v-else>Aucun récit ne correspond à ces filtres.</p>
                    <button
                        v-if="!aucunFiltre"
                        type="button"
                        class="explorer-vide__reinit"
                        @click="reinitialiserFiltres"
                    >
                        Réinitialiser les filtres
                    </button>
                </div>

                <!-- Pagination -->
                <nav
                    v-if="musees.last_page > 1"
                    class="explorer-pagination"
                    aria-label="Pagination"
                >
                    <template
                        v-for="link in musees.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="['explorer-pagination__lien', link.active ? 'explorer-pagination__lien--actif' : '']"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="explorer-pagination__lien explorer-pagination__lien--inactif"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </main>
    </div>
</template>

<style scoped>
/* ─── Palette : fond #1A1A2E · titre #E8D5A3 · corps #C8C8D8 · accent #C9A040 */

.explorateur {
    min-height: 100vh;
    background-color: #1a1a2e;
    color: #c8c8d8;
    font-family: system-ui, -apple-system, sans-serif;
}

/* ─── En-tête ───────────────────────────────────────────────────────────────── */

.explorateur-entete {
    padding: 3.5rem 1.5rem 2.5rem;
    background: linear-gradient(160deg, #0f0f24 0%, #141432 60%, #1a1a2e 100%);
    border-bottom: 1px solid rgba(201, 160, 64, 0.15);
}

.explorateur-entete__inner {
    max-width: 72rem;
    margin: 0 auto;
}

.explorateur-entete__fil {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(200, 200, 216, 0.4);
    margin: 0 0 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.explorateur-entete__fil-lien {
    color: rgba(200, 200, 216, 0.4);
    text-decoration: none;
    transition: color 0.15s;
}

.explorateur-entete__fil-lien:hover {
    color: rgba(200, 200, 216, 0.7);
}

.explorateur-entete__fil-sep {
    color: rgba(201, 160, 64, 0.3);
}

.explorateur-entete__titre {
    font-size: clamp(2.5rem, 5vw, 3.75rem);
    font-weight: 800;
    letter-spacing: -0.03em;
    color: #e8d5a3;
    line-height: 1.1;
    margin: 0 0 1rem;
}

.explorateur-entete__sous-titre {
    font-size: 1rem;
    line-height: 1.65;
    color: rgba(200, 200, 216, 0.6);
    max-width: 40rem;
    margin: 0;
}

/* ─── Tuiles de filtre ──────────────────────────────────────────────────────── */

.filtres-tuiles {
    background-color: #0f0f24;
    border-top: 1px solid rgba(201, 160, 64, 0.1);
    border-bottom: 1px solid rgba(201, 160, 64, 0.1);
    padding: 2rem 1.5rem;
}

.filtres-tuiles__inner {
    max-width: 72rem;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.filtres-tuile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.875rem;
    padding: 2rem 1rem;
    border: 1px solid rgba(201, 160, 64, 0.25);
    border-radius: 0.625rem;
    background: rgba(201, 160, 64, 0.04);
    color: rgba(200, 200, 216, 0.6);
    cursor: pointer;
    transition: border-color 0.2s, background-color 0.2s, color 0.2s, box-shadow 0.2s;
}

.filtres-tuile:hover {
    border-color: rgba(201, 160, 64, 0.55);
    color: #e8d5a3;
    background: rgba(201, 160, 64, 0.08);
    box-shadow: 0 0 0 1px rgba(201, 160, 64, 0.15);
}

.filtres-tuile--actif {
    border-color: #c9a040;
    background: rgba(201, 160, 64, 0.1);
    color: #c9a040;
    box-shadow: 0 0 0 1px rgba(201, 160, 64, 0.2);
}

.filtres-tuile__icone {
    width: 2rem;
    height: 2rem;
    stroke-width: 1.5;
}

.filtres-tuile__label {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.filtres-tuile__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.25rem;
    height: 1.25rem;
    padding: 0 0.3rem;
    border-radius: 999px;
    background-color: #c9a040;
    color: #1a1a2e;
    font-size: 0.7rem;
    font-weight: 800;
    line-height: 1;
}

.filtres-reinit-wrap {
    max-width: 72rem;
    margin: 0.875rem auto 0;
    display: flex;
    justify-content: center;
}

.filtres-reinit {
    background: none;
    border: none;
    color: rgba(200, 200, 216, 0.35);
    font-size: 0.78rem;
    cursor: pointer;
    transition: color 0.15s;
    padding: 0.25rem 0.5rem;
}

.filtres-reinit:hover {
    color: rgba(200, 200, 216, 0.65);
}

/* ─── Panneau déroulant de filtre ───────────────────────────────────────────── */

.filtres-panneau {
    border-top: 1px solid rgba(201, 160, 64, 0.1);
    background-color: #0a0a1e;
    overflow: hidden;
}

.filtres-panneau__inner {
    max-width: 72rem;
    margin: 0 auto;
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filtres-chip {
    padding: 0.375rem 0.875rem;
    border: 1px solid rgba(201, 160, 64, 0.2);
    border-radius: 999px;
    background: none;
    color: rgba(200, 200, 216, 0.6);
    font-size: 0.8125rem;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background-color 0.15s;
}

.filtres-chip:hover {
    border-color: rgba(201, 160, 64, 0.45);
    color: #e8d5a3;
}

.filtres-chip--actif {
    background-color: rgba(201, 160, 64, 0.15);
    border-color: #c9a040;
    color: #c9a040;
}

.filtres-vide {
    font-size: 0.8rem;
    color: rgba(200, 200, 216, 0.35);
    font-style: italic;
    padding: 0.25rem 0;
}

/* Transition panneau */
.panneau-enter-active,
.panneau-leave-active {
    transition: max-height 0.3s ease, opacity 0.2s ease;
    /* Hauteur généreuse pour les 17 régions avec wrapping */
    max-height: 24rem;
}

.panneau-enter-from,
.panneau-leave-to {
    max-height: 0;
    opacity: 0;
}

/* ─── Contenu principal ─────────────────────────────────────────────────────── */

.explorer-main {
    padding: 2.5rem 1.5rem 4rem;
}

.explorer-main__inner {
    max-width: 72rem;
    margin: 0 auto;
}

.explorer-compteur {
    font-size: 0.78rem;
    color: rgba(200, 200, 216, 0.45);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 1.75rem;
}

/* ─── Grille ────────────────────────────────────────────────────────────────── */

.explorer-grille {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
    gap: 1.375rem;
}

/* ─── Carte ─────────────────────────────────────────────────────────────────── */

.explorer-carte {
    display: flex;
    flex-direction: column;
    background-color: #12122a;
    border-radius: 0.75rem;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    border: 1px solid rgba(201, 160, 64, 0.12);
    transition: box-shadow 0.25s, transform 0.25s, border-color 0.25s;
}

.explorer-carte:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(201, 160, 64, 0.35);
    transform: translateY(-3px);
    border-color: rgba(201, 160, 64, 0.35);
}

.explorer-carte__image-wrap {
    height: 12rem;
    overflow: hidden;
    background-color: #0f0f24;
}

.explorer-carte__image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s;
    opacity: 0.9;
}

.explorer-carte:hover .explorer-carte__image {
    transform: scale(1.04);
    opacity: 1;
}

.explorer-carte__image-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #12122a 0%, #1a1a3e 50%, #1e1a3a 100%);
}

.explorer-carte__corps {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1.25rem;
    flex: 1;
}

.explorer-carte__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.explorer-carte__tag {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background-color: rgba(201, 160, 64, 0.12);
    color: #c9a040;
    border: 1px solid rgba(201, 160, 64, 0.25);
}

.explorer-carte__titre {
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.35;
    color: #e8d5a3;
    margin: 0;
}

.explorer-carte__intro {
    font-size: 0.875rem;
    color: rgba(200, 200, 216, 0.65);
    line-height: 1.55;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    margin: 0;
}

.explorer-carte__auteurs {
    font-size: 0.78rem;
    color: rgba(200, 200, 216, 0.4);
    margin-top: auto;
    padding-top: 0.625rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

/* ─── Vide ──────────────────────────────────────────────────────────────────── */

.explorer-vide {
    text-align: center;
    padding: 5rem 0;
    color: rgba(200, 200, 216, 0.35);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
}

.explorer-vide__reinit {
    padding: 0.5rem 1.25rem;
    border: 1px solid rgba(201, 160, 64, 0.3);
    border-radius: 0.375rem;
    background: none;
    color: #c9a040;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background-color 0.15s;
}

.explorer-vide__reinit:hover {
    background-color: rgba(201, 160, 64, 0.08);
}

/* ─── Pagination ────────────────────────────────────────────────────────────── */

.explorer-pagination {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.25rem;
    margin-top: 3rem;
}

.explorer-pagination__lien {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.25rem;
    height: 2.25rem;
    padding: 0 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    text-decoration: none;
    border: 1px solid rgba(201, 160, 64, 0.2);
    background-color: transparent;
    color: rgba(200, 200, 216, 0.65);
    transition: background-color 0.15s, border-color 0.15s;
}

.explorer-pagination__lien:hover:not(.explorer-pagination__lien--actif):not(.explorer-pagination__lien--inactif) {
    background-color: rgba(201, 160, 64, 0.1);
    border-color: rgba(201, 160, 64, 0.4);
}

.explorer-pagination__lien--actif {
    background-color: #c9a040;
    color: #1a1a2e;
    border-color: #c9a040;
    font-weight: 700;
}

.explorer-pagination__lien--inactif {
    color: rgba(200, 200, 216, 0.25);
    cursor: default;
    border-color: transparent;
    background: none;
}

/* ─── Mobile ────────────────────────────────────────────────────────────────── */

@media (max-width: 640px) {
    .explorateur-entete {
        padding: 2.5rem 1.25rem 2rem;
    }

    .filtres-tuiles__inner {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.625rem;
    }

    .filtres-tuile {
        padding: 1.25rem 0.5rem;
        gap: 0.625rem;
    }

    .filtres-tuile__icone {
        width: 1.5rem;
        height: 1.5rem;
    }

    .filtres-tuile__label {
        font-size: 0.65rem;
    }
}
</style>
