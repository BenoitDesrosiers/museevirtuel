<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { BookOpen, ChevronRight, Landmark } from 'lucide-vue-next'

// ─── Auth ─────────────────────────────────────────────────────────────────────

const page = usePage()
const user = computed(() => (page.props.auth as any)?.user ?? null)

/** Destination du CTA éducation — dashboard si déjà connecté, sinon login. */
const lienEducation = computed(() => (user.value ? '/dashboard' : '/login'))
const labelEducation = computed(() => (user.value ? 'Accéder au tableau de bord' : 'Connexion'))
</script>

<template>
    <Head title="L'Archive" />

    <div class="accueil">
        <!-- ─── Panneau gauche : Musée virtuel ──────────────────────────────── -->
        <Link href="/musee" class="panneau panneau--musee" aria-label="Explorer le musée virtuel">
            <!-- Fond décoratif -->
            <div class="panneau__fond panneau__fond--musee" aria-hidden="true" />

            <!-- Contenu -->
            <div class="panneau__contenu">
                <div class="panneau__icone">
                    <Landmark class="panneau__icone-svg" />
                </div>

                <p class="panneau__surtitre">Bienvenue dans</p>
                <h2 class="panneau__titre panneau__titre--musee">
                    Le Musée<br>virtuel
                </h2>
                <p class="panneau__description">
                    Explorez les projets publiés par les étudiants — recherches historiques,
                    galeries thématiques et récits documentés.
                </p>

                <span class="panneau__cta panneau__cta--musee">
                    Explorer la galerie
                    <ChevronRight class="panneau__cta-icone" />
                </span>
            </div>
        </Link>

        <!-- ─── Séparateur central ──────────────────────────────────────────── -->
        <div class="separateur" aria-hidden="true">
            <span class="separateur__texte">L'Archive</span>
        </div>

        <!-- ─── Panneau droit : Plateforme éducative ───────────────────────── -->
        <Link :href="lienEducation" class="panneau panneau--education" aria-label="Accéder à la plateforme éducative">
            <!-- Fond décoratif -->
            <div class="panneau__fond panneau__fond--education" aria-hidden="true" />

            <!-- Contenu -->
            <div class="panneau__contenu">
                <div class="panneau__icone">
                    <BookOpen class="panneau__icone-svg" />
                </div>

                <p class="panneau__surtitre">Espace</p>
                <h2 class="panneau__titre panneau__titre--education">
                    Plateforme<br>éducative
                </h2>
                <p class="panneau__description">
                    Enseignants et étudiants — créez, gérez et soumettez
                    vos projets de recherche documentaire.
                </p>

                <span class="panneau__cta panneau__cta--education">
                    {{ labelEducation }}
                    <ChevronRight class="panneau__cta-icone" />
                </span>
            </div>
        </Link>
    </div>
</template>

<style scoped>
/* ─── Layout split screen ───────────────────────────────────────────────────── */

.accueil {
    display: flex;
    min-height: 100vh;
    position: relative;
}

/* ─── Panneau commun ────────────────────────────────────────────────────────── */

.panneau {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    text-decoration: none;
    transition: flex 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.accueil:has(.panneau--musee:hover) .panneau--musee {
    flex: 1.35;
}

.accueil:has(.panneau--education:hover) .panneau--education {
    flex: 1.35;
}

/* ─── Fond décoratif ────────────────────────────────────────────────────────── */

.panneau__fond {
    position: absolute;
    inset: 0;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s;
}

.panneau--musee .panneau__fond--musee {
    background:
        radial-gradient(ellipse at 30% 50%, rgba(201, 160, 64, 0.12) 0%, transparent 65%),
        radial-gradient(ellipse at 80% 20%, rgba(201, 160, 64, 0.06) 0%, transparent 50%),
        linear-gradient(160deg, #0a0a1e 0%, #111228 40%, #0e0e28 100%);
}

.panneau--musee:hover .panneau__fond--musee {
    transform: scale(1.04);
    opacity: 0.9;
}

.panneau--education .panneau__fond--education {
    background:
        radial-gradient(ellipse at 70% 50%, rgba(56, 139, 255, 0.1) 0%, transparent 65%),
        radial-gradient(ellipse at 20% 80%, rgba(56, 139, 255, 0.05) 0%, transparent 50%),
        linear-gradient(160deg, #0a111e 0%, #0f1e30 40%, #0b1828 100%);
}

.panneau--education:hover .panneau__fond--education {
    transform: scale(1.04);
    opacity: 0.9;
}

/* ─── Contenu ───────────────────────────────────────────────────────────────── */

.panneau__contenu {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    max-width: 26rem;
    padding: 3rem 3rem 3rem 4rem;
}

.panneau__icone {
    width: 3rem;
    height: 3rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.75rem;
}

.panneau--musee .panneau__icone {
    background: rgba(201, 160, 64, 0.15);
    border: 1px solid rgba(201, 160, 64, 0.3);
}

.panneau--education .panneau__icone {
    background: rgba(56, 139, 255, 0.12);
    border: 1px solid rgba(56, 139, 255, 0.25);
}

.panneau__icone-svg {
    width: 1.5rem;
    height: 1.5rem;
}

.panneau--musee .panneau__icone-svg {
    color: #c9a040;
}

.panneau--education .panneau__icone-svg {
    color: #5b9ff8;
}

.panneau__surtitre {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    margin-bottom: 0.625rem;
}

.panneau--musee .panneau__surtitre {
    color: rgba(201, 160, 64, 0.7);
}

.panneau--education .panneau__surtitre {
    color: rgba(91, 159, 248, 0.7);
}

.panneau__titre {
    font-size: clamp(2.25rem, 4vw, 3.25rem);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
    margin: 0 0 1.25rem;
}

.panneau--musee .panneau__titre--musee {
    color: #e8d5a3;
}

.panneau--education .panneau__titre--education {
    color: #c8d8f0;
}

.panneau__description {
    font-size: 0.9375rem;
    line-height: 1.65;
    margin: 0 0 2rem;
    max-width: 22rem;
}

.panneau--musee .panneau__description {
    color: rgba(200, 200, 216, 0.6);
}

.panneau--education .panneau__description {
    color: rgba(180, 200, 230, 0.6);
}

.panneau__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.9rem;
    font-weight: 600;
    padding: 0.625rem 1.375rem;
    border-radius: 0.5rem;
    transition: transform 0.2s, box-shadow 0.2s;
}

.panneau--musee .panneau__cta--musee {
    background-color: #c9a040;
    color: #0e0e24;
}

.panneau--education .panneau__cta--education {
    background-color: #5b9ff8;
    color: #05111e;
}

.panneau:hover .panneau__cta {
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
}

.panneau__cta-icone {
    width: 1rem;
    height: 1rem;
    transition: transform 0.2s;
}

.panneau:hover .panneau__cta-icone {
    transform: translateX(2px);
}

/* ─── Séparateur central ────────────────────────────────────────────────────── */

.separateur {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    z-index: 20;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    pointer-events: none;
}

.separateur__texte {
    display: block;
    background: #0d0d22;
    border: 1px solid rgba(201, 160, 64, 0.25);
    color: #c9a040;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    padding: 0.5rem 0.875rem;
    border-radius: 999px;
    white-space: nowrap;
}

/* ─── Ligne verticale ───────────────────────────────────────────────────────── */

.accueil::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 0;
    bottom: 0;
    width: 1px;
    background: linear-gradient(
        to bottom,
        transparent 0%,
        rgba(201, 160, 64, 0.2) 20%,
        rgba(201, 160, 64, 0.3) 50%,
        rgba(201, 160, 64, 0.2) 80%,
        transparent 100%
    );
    pointer-events: none;
}

/* ─── Mobile ────────────────────────────────────────────────────────────────── */

@media (max-width: 640px) {
    .accueil {
        flex-direction: column;
    }

    .accueil::after {
        display: none;
    }

    .panneau {
        min-height: 50vh;
    }

    .panneau__contenu {
        padding: 2.5rem 1.5rem;
    }

    .separateur {
        display: none;
    }

    /* Sur mobile, pas d'effet flex hover */
    .accueil:has(.panneau--musee:hover) .panneau--musee,
    .accueil:has(.panneau--education:hover) .panneau--education {
        flex: 1;
    }
}
</style>
