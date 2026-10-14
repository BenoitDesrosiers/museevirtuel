<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { BookOpen, Handshake, Mic, Search } from 'lucide-vue-next'
import { ref } from 'vue'
import MuseePublicNav from '@/components/musee/MuseePublicNav.vue'

// ─── FAQ accordéon ────────────────────────────────────────────────────────────

type FaqItem = { question: string; reponse: string }

const faq: FaqItem[] = [
    {
        question: 'À qui s\'adresse ce programme ?',
        reponse: 'À toute personne souhaitant partager ses souvenirs et son vécu. Aucune expertise particulière n\'est requise — votre histoire personnelle est la seule condition.',
    },
    {
        question: 'Qui reste propriétaire des archives ?',
        reponse: 'Vous conservez l\'entière propriété de votre témoignage. L\'Archive Boréale obtient une licence d\'utilisation non exclusive à des fins éducatives, que vous pouvez révoquer à tout moment.',
    },
    {
        question: 'Comment se déroule l\'entretien ?',
        reponse: 'L\'entretien peut se faire en personne ou à distance, selon vos préférences. Nos étudiants sont formés pour créer un climat de confiance et de respect. La durée varie de 30 minutes à 2 heures.',
    },
    {
        question: 'Mon témoignage sera-t-il rendu public ?',
        reponse: 'Uniquement avec votre accord explicite. Vous contrôlez ce qui est publié et pouvez demander la modification ou le retrait de votre témoignage à n\'importe quel moment.',
    },
]

const ouvert = ref<number | null>(null)

function toggleFaq(index: number): void {
    ouvert.value = ouvert.value === index ? null : index
}
</script>

<template>
    <Head title="Contribuer — Musée virtuel" />

    <div class="contribuer">
        <!-- ─── Navigation ───────────────────────────────────────────────────── -->
        <MuseePublicNav active="contribuer" />

        <!-- ─── Hero ─────────────────────────────────────────────────────────── -->
        <section class="hero">
            <div class="hero__decoration" aria-hidden="true" />
            <div class="hero__inner">
                <p class="hero__label">L'ARCHIVE BORÉALE</p>
                <h1 class="hero__titre">Préservez<br>Votre Histoire</h1>
                <p class="hero__sous-titre">
                    Devenez le gardien de la mémoire collective.
                    Votre récit est le fil d'or qui tisse notre héritage.
                </p>
                <Link href="/inscription/temoin" class="hero__cta">
                    PARTAGER MON HISTOIRE
                </Link>
            </div>
        </section>

        <!-- ─── Étapes du processus ──────────────────────────────────────────── -->
        <section class="processus">
            <div class="processus__inner">
                <div class="processus__carte">
                    <div class="processus__icone">
                        <Handshake class="processus__icone-svg" />
                    </div>
                    <h3 class="processus__titre">Rencontre</h3>
                    <p class="processus__desc">
                        Premier contact pour définir l'angle de votre témoignage.
                    </p>
                </div>

                <div class="processus__carte">
                    <div class="processus__icone">
                        <Mic class="processus__icone-svg" />
                    </div>
                    <h3 class="processus__titre">Entretien</h3>
                    <p class="processus__desc">
                        Enregistrement de votre récit dans le confort et le respect.
                    </p>
                </div>

                <div class="processus__carte">
                    <div class="processus__icone">
                        <Search class="processus__icone-svg" />
                    </div>
                    <h3 class="processus__titre">Recherche</h3>
                    <p class="processus__desc">
                        Contextualisation historique de vos archives personnelles.
                    </p>
                </div>

                <div class="processus__carte">
                    <div class="processus__icone">
                        <BookOpen class="processus__icone-svg" />
                    </div>
                    <h3 class="processus__titre">Publication</h3>
                    <p class="processus__desc">
                        Intégration de votre histoire au sein de l'Archive Boréale.
                    </p>
                </div>
            </div>
        </section>

        <!-- ─── FAQ ──────────────────────────────────────────────────────────── -->
        <section class="faq">
            <div class="faq__inner">
                <h2 class="faq__titre">Pourquoi<br><span class="faq__titre-accent">Contribuer ?</span></h2>

                <div class="faq__liste">
                    <div
                        v-for="(item, index) in faq"
                        :key="index"
                        class="faq__item"
                    >
                        <button
                            type="button"
                            class="faq__question"
                            :aria-expanded="ouvert === index"
                            @click="toggleFaq(index)"
                        >
                            <span>{{ item.question }}</span>
                            <span
                                class="faq__icone"
                                :class="ouvert === index ? 'faq__icone--ouvert' : ''"
                            >+</span>
                        </button>
                        <Transition name="faq-reponse">
                            <p
                                v-if="ouvert === index"
                                class="faq__reponse"
                            >
                                {{ item.reponse }}
                            </p>
                        </Transition>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─── CTA final ─────────────────────────────────────────────────────── -->
        <section class="cta-final">
            <div class="cta-final__inner">
                <p class="cta-final__label">PRÊT À PARTAGER ?</p>
                <h2 class="cta-final__titre">Votre histoire nous appartient à tous</h2>
                <Link href="/inscription/temoin" class="cta-final__btn">
                    Créer mon compte témoin
                </Link>
            </div>
        </section>
    </div>
</template>

<style scoped>
/* ─── Palette : fond #1A1A2E · titre #E8D5A3 · corps #C8C8D8 · accent #C9A040 */

.contribuer {
    min-height: 100vh;
    background-color: #1a1a2e;
    color: #c8c8d8;
    font-family: system-ui, -apple-system, sans-serif;
}

/* ─── Hero ──────────────────────────────────────────────────────────────────── */

.hero {
    position: relative;
    padding: 7rem 1.5rem 6rem;
    background: linear-gradient(160deg, #0d0d22 0%, #141432 50%, #1a1a3a 100%);
    border-bottom: 1px solid rgba(201, 160, 64, 0.2);
    overflow: hidden;
    text-align: center;
}

.hero__decoration {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at 50% 30%, rgba(201, 160, 64, 0.09) 0%, transparent 65%),
        radial-gradient(ellipse at 80% 70%, rgba(201, 160, 64, 0.04) 0%, transparent 50%);
    pointer-events: none;
}

.hero__inner {
    max-width: 44rem;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.hero__label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.22em;
    color: rgba(201, 160, 64, 0.7);
    margin: 0 0 1.5rem;
}

.hero__titre {
    font-size: clamp(2.75rem, 6vw, 4.5rem);
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: -0.03em;
    color: #e8d5a3;
    margin: 0 0 1.5rem;
}

.hero__sous-titre {
    font-size: 1.1rem;
    line-height: 1.7;
    color: rgba(200, 200, 216, 0.65);
    margin: 0 0 3rem;
}

.hero__cta {
    display: inline-flex;
    align-items: center;
    padding: 0.9rem 2.25rem;
    background-color: #c9a040;
    color: #0d0d22;
    font-size: 0.875rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    border-radius: 0.5rem;
    text-decoration: none;
    transition: box-shadow 0.2s, transform 0.2s;
    box-shadow: 0 4px 24px rgba(201, 160, 64, 0.3);
}

.hero__cta:hover {
    box-shadow: 0 6px 32px rgba(201, 160, 64, 0.5);
    transform: translateY(-1px);
}

/* ─── Processus ─────────────────────────────────────────────────────────────── */

.processus {
    padding: 4rem 1.5rem;
    background-color: #141428;
    border-bottom: 1px solid rgba(201, 160, 64, 0.1);
}

.processus__inner {
    max-width: 72rem;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
}

.processus__carte {
    padding: 2rem 1.5rem;
    background-color: #1a1a2e;
    border: 1px solid rgba(201, 160, 64, 0.12);
    border-radius: 0.875rem;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.processus__icone {
    width: 2.75rem;
    height: 2.75rem;
    background: rgba(201, 160, 64, 0.1);
    border: 1px solid rgba(201, 160, 64, 0.22);
    border-radius: 0.625rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.processus__icone-svg {
    width: 1.25rem;
    height: 1.25rem;
    color: #c9a040;
}

.processus__titre {
    font-size: 0.9rem;
    font-weight: 700;
    color: #c9a040;
    margin: 0;
    letter-spacing: 0.02em;
}

.processus__desc {
    font-size: 0.875rem;
    color: rgba(200, 200, 216, 0.6);
    line-height: 1.6;
    margin: 0;
}

/* ─── FAQ ───────────────────────────────────────────────────────────────────── */

.faq {
    padding: 5rem 1.5rem;
    border-bottom: 1px solid rgba(201, 160, 64, 0.1);
}

.faq__inner {
    max-width: 72rem;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 4rem;
    align-items: start;
}

.faq__titre {
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.025em;
    color: #e8d5a3;
    margin: 0;
    position: sticky;
    top: 5rem;
}

.faq__titre-accent {
    color: #c9a040;
}

.faq__liste {
    display: flex;
    flex-direction: column;
    gap: 0;
    border: 1px solid rgba(201, 160, 64, 0.15);
    border-radius: 0.75rem;
    overflow: hidden;
}

.faq__item {
    border-bottom: 1px solid rgba(201, 160, 64, 0.1);
}

.faq__item:last-child {
    border-bottom: none;
}

.faq__question {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    background: none;
    border: none;
    color: #e8d5a3;
    font-size: 0.9375rem;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    transition: background-color 0.15s;
}

.faq__question:hover {
    background-color: rgba(201, 160, 64, 0.04);
}

.faq__icone {
    font-size: 1.5rem;
    color: #c9a040;
    flex-shrink: 0;
    transition: transform 0.2s;
    line-height: 1;
}

.faq__icone--ouvert {
    transform: rotate(45deg);
}

.faq__reponse {
    padding: 0 1.5rem 1.25rem;
    font-size: 0.9rem;
    color: rgba(200, 200, 216, 0.65);
    line-height: 1.7;
    margin: 0;
}

/* Transition FAQ */
.faq-reponse-enter-active,
.faq-reponse-leave-active {
    transition: opacity 0.2s ease;
}

.faq-reponse-enter-from,
.faq-reponse-leave-to {
    opacity: 0;
}

/* ─── CTA final ─────────────────────────────────────────────────────────────── */

.cta-final {
    padding: 5rem 1.5rem;
    background: linear-gradient(160deg, #0f0f22 0%, #141430 100%);
    text-align: center;
}

.cta-final__inner {
    max-width: 36rem;
    margin: 0 auto;
}

.cta-final__label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    color: rgba(201, 160, 64, 0.55);
    margin: 0 0 1rem;
}

.cta-final__titre {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.025em;
    color: #e8d5a3;
    line-height: 1.2;
    margin: 0 0 2rem;
}

.cta-final__btn {
    display: inline-flex;
    padding: 0.875rem 2rem;
    background-color: #c9a040;
    color: #0d0d22;
    font-size: 0.9375rem;
    font-weight: 700;
    border-radius: 0.5rem;
    text-decoration: none;
    transition: box-shadow 0.2s, transform 0.2s;
    box-shadow: 0 4px 20px rgba(201, 160, 64, 0.25);
}

.cta-final__btn:hover {
    box-shadow: 0 6px 28px rgba(201, 160, 64, 0.45);
    transform: translateY(-1px);
}

/* ─── Mobile ────────────────────────────────────────────────────────────────── */

@media (max-width: 768px) {
    .processus__inner {
        grid-template-columns: repeat(2, 1fr);
    }

    .faq__inner {
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }

    .faq__titre {
        position: static;
        font-size: 2rem;
    }
}

@media (max-width: 480px) {
    .processus__inner {
        grid-template-columns: 1fr;
    }
}
</style>
