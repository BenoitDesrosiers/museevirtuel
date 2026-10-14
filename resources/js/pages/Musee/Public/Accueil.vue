<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'
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

type Props = {
    miseEnAvant: Musee[]
    total: number
}

defineProps<Props>()

/**
 * Retourne l'URL de stockage de l'image de couverture d'un musée.
 */
function imageUrl(path: string | null): string | null {
    return path ? `/storage/${path}` : null
}
</script>

<template>
    <Head title="L'Archive — Musée virtuel" />

    <div class="accueil-musee">
        <!-- ─── Navigation ───────────────────────────────────────────────────── -->
        <MuseePublicNav active="accueil" />

        <!-- ─── Hero ────────────────────────────────────────────────────────── -->
        <section class="hero">
            <div class="hero__decoration" aria-hidden="true" />
            <div class="hero__inner">
                <p class="hero__label">L'ARCHIVE BORÉALE</p>
                <h1 class="hero__titre">
                    La mémoire vivante<br>de notre histoire
                </h1>
                <p class="hero__sous-titre">
                    Des récits authentiques, recueillis auprès de témoins,
                    mis en valeur par des étudiants passionnés.
                </p>
                <div class="hero__actions">
                    <Link href="/musee/explorer" class="hero__cta hero__cta--principal">
                        Explorer les récits
                        <ArrowRight class="hero__cta-icone" />
                    </Link>
                    <Link href="/musee/contribuer" class="hero__cta hero__cta--secondaire">
                        Partager votre histoire
                    </Link>
                </div>
            </div>

            <!-- Compteur flottant -->
            <div
                v-if="total > 0"
                class="hero__compteur"
            >
                <span class="hero__compteur-chiffre">{{ total }}</span>
                <span class="hero__compteur-label">récit{{ total > 1 ? 's' : '' }} archivé{{ total > 1 ? 's' : '' }}</span>
            </div>
        </section>

        <!-- ─── Récits récents ───────────────────────────────────────────────── -->
        <section
            v-if="miseEnAvant.length > 0"
            class="recits"
        >
            <div class="recits__inner">
                <div class="recits__entete">
                    <h2 class="recits__titre">Récits récents</h2>
                    <Link href="/musee/explorer" class="recits__voir-tout">
                        Voir tout
                        <ArrowRight class="recits__voir-tout-icone" />
                    </Link>
                </div>

                <div class="recits__grille">
                    <Link
                        v-for="musee in miseEnAvant"
                        :key="musee.slug"
                        :href="`/musee/${musee.slug}`"
                        class="recit-carte"
                    >
                        <!-- Image -->
                        <div class="recit-carte__image-wrap">
                            <img
                                v-if="imageUrl(musee.image_path)"
                                :src="imageUrl(musee.image_path)!"
                                :alt="musee.titre ?? 'Musée virtuel'"
                                class="recit-carte__image"
                            />
                            <div
                                v-else
                                class="recit-carte__image-placeholder"
                            />
                        </div>

                        <!-- Contenu -->
                        <div class="recit-carte__corps">
                            <div
                                v-if="musee.periode || musee.thematique || musee.region"
                                class="recit-carte__tags"
                            >
                                <span v-if="musee.periode" class="recit-carte__tag">{{ musee.periode.nom }}</span>
                                <span v-if="musee.thematique" class="recit-carte__tag">{{ musee.thematique.nom }}</span>
                                <span v-if="musee.region" class="recit-carte__tag">{{ musee.region.nom }}</span>
                            </div>

                            <h3 class="recit-carte__titre">{{ musee.titre ?? 'Récit archivé' }}</h3>

                            <p
                                v-if="musee.intro_texte"
                                class="recit-carte__intro"
                            >
                                {{ musee.intro_texte }}
                            </p>

                            <p
                                v-if="musee.membres.length > 0"
                                class="recit-carte__auteurs"
                            >
                                {{ musee.membres.join(' · ') }}
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <!-- ─── Appel à contribution ─────────────────────────────────────────── -->
        <section class="appel">
            <div class="appel__inner">
                <p class="appel__label">VOTRE HISTOIRE MÉRITE D'ÊTRE ENTENDUE</p>
                <h2 class="appel__titre">Devenez gardien de la mémoire collective</h2>
                <p class="appel__desc">
                    Vous avez vécu des événements qui méritent d'être transmis aux générations futures.
                    Nos étudiants sont prêts à recueillir et à mettre en valeur votre témoignage.
                </p>
                <Link href="/musee/contribuer" class="appel__cta">
                    Partager mon histoire
                    <ArrowRight class="appel__cta-icone" />
                </Link>
            </div>
        </section>
    </div>
</template>

<style scoped>
/* ─── Palette : fond #1A1A2E · titre #E8D5A3 · corps #C8C8D8 · accent #C9A040 */

.accueil-musee {
    min-height: 100vh;
    background-color: #1a1a2e;
    color: #c8c8d8;
    font-family: system-ui, -apple-system, sans-serif;
}

/* ─── Hero ──────────────────────────────────────────────────────────────────── */

.hero {
    position: relative;
    padding: 6rem 1.5rem 5rem;
    background: linear-gradient(160deg, #0d0d22 0%, #141432 50%, #1a1a3a 100%);
    border-bottom: 1px solid rgba(201, 160, 64, 0.2);
    overflow: hidden;
}

.hero__decoration {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at 20% 50%, rgba(201, 160, 64, 0.07) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 20%, rgba(201, 160, 64, 0.04) 0%, transparent 50%);
    pointer-events: none;
}

.hero__inner {
    max-width: 56rem;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.hero__label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    color: rgba(201, 160, 64, 0.75);
    margin: 0 0 1.25rem;
}

.hero__titre {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
    color: #e8d5a3;
    margin: 0 0 1.5rem;
}

.hero__sous-titre {
    font-size: 1.1rem;
    line-height: 1.7;
    color: rgba(200, 200, 216, 0.7);
    max-width: 42rem;
    margin: 0 0 2.5rem;
}

.hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.hero__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9375rem;
    font-weight: 600;
    padding: 0.75rem 1.625rem;
    border-radius: 0.5rem;
    text-decoration: none;
    transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
}

.hero__cta:hover {
    transform: translateY(-1px);
}

.hero__cta--principal {
    background-color: #c9a040;
    color: #0e0e24;
    box-shadow: 0 4px 20px rgba(201, 160, 64, 0.25);
}

.hero__cta--principal:hover {
    box-shadow: 0 6px 28px rgba(201, 160, 64, 0.4);
}

.hero__cta--secondaire {
    background: rgba(201, 160, 64, 0.08);
    color: #e8d5a3;
    border: 1px solid rgba(201, 160, 64, 0.3);
}

.hero__cta--secondaire:hover {
    background: rgba(201, 160, 64, 0.14);
    border-color: rgba(201, 160, 64, 0.5);
}

.hero__cta-icone {
    width: 1rem;
    height: 1rem;
}

.hero__compteur {
    position: absolute;
    bottom: 2.5rem;
    right: 2.5rem;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.1rem;
}

.hero__compteur-chiffre {
    font-size: 3rem;
    font-weight: 800;
    letter-spacing: -0.04em;
    color: rgba(201, 160, 64, 0.35);
    line-height: 1;
}

.hero__compteur-label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: rgba(201, 160, 64, 0.4);
}

/* ─── Récits récents ────────────────────────────────────────────────────────── */

.recits {
    padding: 4rem 1.5rem;
}

.recits__inner {
    max-width: 72rem;
    margin: 0 auto;
}

.recits__entete {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 2rem;
}

.recits__titre {
    font-size: 1.5rem;
    font-weight: 700;
    color: #e8d5a3;
    letter-spacing: -0.02em;
    margin: 0;
}

.recits__voir-tout {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #c9a040;
    text-decoration: none;
    transition: gap 0.2s;
}

.recits__voir-tout:hover {
    gap: 0.625rem;
}

.recits__voir-tout-icone {
    width: 0.875rem;
    height: 0.875rem;
}

.recits__grille {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
    gap: 1.25rem;
}

/* ─── Carte récit ───────────────────────────────────────────────────────────── */

.recit-carte {
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

.recit-carte:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(201, 160, 64, 0.35);
    transform: translateY(-3px);
    border-color: rgba(201, 160, 64, 0.35);
}

.recit-carte__image-wrap {
    height: 11rem;
    overflow: hidden;
    background-color: #0f0f24;
}

.recit-carte__image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s;
    opacity: 0.9;
}

.recit-carte:hover .recit-carte__image {
    transform: scale(1.04);
    opacity: 1;
}

.recit-carte__image-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #12122a 0%, #1a1a3e 50%, #1e1a3a 100%);
}

.recit-carte__corps {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1.125rem;
    flex: 1;
}

.recit-carte__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.recit-carte__tag {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.175rem 0.5rem;
    border-radius: 999px;
    background-color: rgba(201, 160, 64, 0.12);
    color: #c9a040;
    border: 1px solid rgba(201, 160, 64, 0.25);
}

.recit-carte__titre {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.35;
    color: #e8d5a3;
    margin: 0;
}

.recit-carte__intro {
    font-size: 0.85rem;
    color: rgba(200, 200, 216, 0.65);
    line-height: 1.55;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    margin: 0;
}

.recit-carte__auteurs {
    font-size: 0.75rem;
    color: rgba(200, 200, 216, 0.4);
    margin-top: auto;
    padding-top: 0.625rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

/* ─── Appel à contribution ──────────────────────────────────────────────────── */

.appel {
    padding: 5rem 1.5rem;
    background: linear-gradient(160deg, #10101f 0%, #14142e 60%, #181830 100%);
    border-top: 1px solid rgba(201, 160, 64, 0.12);
}

.appel__inner {
    max-width: 48rem;
    margin: 0 auto;
    text-align: center;
}

.appel__label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    color: rgba(201, 160, 64, 0.6);
    margin: 0 0 1.25rem;
}

.appel__titre {
    font-size: clamp(1.75rem, 3.5vw, 2.5rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    color: #e8d5a3;
    line-height: 1.2;
    margin: 0 0 1.25rem;
}

.appel__desc {
    font-size: 1rem;
    line-height: 1.7;
    color: rgba(200, 200, 216, 0.65);
    margin: 0 0 2.5rem;
}

.appel__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    background-color: #c9a040;
    color: #0e0e24;
    font-size: 0.9375rem;
    font-weight: 700;
    border-radius: 0.5rem;
    text-decoration: none;
    transition: box-shadow 0.2s, transform 0.2s;
}

.appel__cta:hover {
    box-shadow: 0 6px 28px rgba(201, 160, 64, 0.45);
    transform: translateY(-1px);
}

.appel__cta-icone {
    width: 1rem;
    height: 1rem;
}

/* ─── Mobile ────────────────────────────────────────────────────────────────── */

@media (max-width: 640px) {
    .hero {
        padding: 4rem 1.25rem 3.5rem;
    }

    .hero__compteur {
        display: none;
    }

    .recits__entete {
        flex-direction: column;
        gap: 0.75rem;
    }
}
</style>
