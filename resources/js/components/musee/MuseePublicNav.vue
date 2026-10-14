<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { LayoutDashboard, LogIn, LogOut, UserCircle } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

// ─── Props ────────────────────────────────────────────────────────────────────

defineProps<{
    /** Lien actif dans la navigation (colore le lien en or). */
    active?: 'accueil' | 'explorer' | 'contribuer'
}>()

// ─── Auth ─────────────────────────────────────────────────────────────────────

const page = usePage()
const user = computed(() => (page.props.auth as any)?.user ?? null)

// ─── Dropdown profil ──────────────────────────────────────────────────────────

const menuOuvert = ref(false)
const menuRef = ref<HTMLElement | null>(null)

function toggleMenu(): void {
    menuOuvert.value = !menuOuvert.value
}

function fermerMenu(): void {
    menuOuvert.value = false
}

/** Ferme le menu si le clic est en dehors du composant. */
function onClickExterieur(event: MouseEvent): void {
    if (menuRef.value && !menuRef.value.contains(event.target as Node)) {
        fermerMenu()
    }
}

onMounted(() => document.addEventListener('click', onClickExterieur))
onBeforeUnmount(() => document.removeEventListener('click', onClickExterieur))

/**
 * Déconnecte l'utilisateur via POST /logout puis redirige vers /musee.
 * Fortify attend un POST pour la déconnexion.
 */
function seDeconnecter(): void {
    fermerMenu()
    router.post('/logout', {}, { onFinish: () => router.visit('/musee') })
}
</script>

<template>
    <nav class="musee-nav" aria-label="Navigation principale">
        <div class="musee-nav__inner">
            <!-- Marque -->
            <Link href="/musee" class="musee-nav__marque">
                L'Archive
            </Link>

            <!-- Liens principaux -->
            <ul class="musee-nav__liens" role="list">
                <li>
                    <Link
                        href="/musee"
                        :class="['musee-nav__lien', active === 'accueil' ? 'musee-nav__lien--actif' : '']"
                    >
                        Accueil
                    </Link>
                </li>
                <li>
                    <Link
                        href="/musee/explorer"
                        :class="['musee-nav__lien', active === 'explorer' ? 'musee-nav__lien--actif' : '']"
                    >
                        Explorer
                    </Link>
                </li>
                <li>
                    <Link
                        href="/musee/contribuer"
                        :class="['musee-nav__lien', active === 'contribuer' ? 'musee-nav__lien--actif' : '']"
                    >
                        Contribuer
                    </Link>
                </li>
            </ul>

            <!-- Zone profil / connexion -->
            <div ref="menuRef" class="musee-nav__profil">
                <!-- Bouton déclencheur -->
                <button
                    type="button"
                    class="musee-nav__profil-btn"
                    :aria-expanded="menuOuvert"
                    aria-haspopup="true"
                    :title="user ? `${user.prenom} ${user.nom}` : 'Compte'"
                    @click="toggleMenu"
                >
                    <UserCircle class="musee-nav__profil-icone" />
                </button>

                <!-- Menu déroulant -->
                <Transition name="menu">
                    <div
                        v-if="menuOuvert"
                        class="musee-nav__dropdown"
                        role="menu"
                    >
                        <!-- Utilisateur connecté -->
                        <template v-if="user">
                            <div class="musee-nav__dropdown-entete">
                                <p class="musee-nav__dropdown-nom">{{ user.prenom }} {{ user.nom }}</p>
                                <p class="musee-nav__dropdown-role">{{ user.role }}</p>
                            </div>

                            <div class="musee-nav__dropdown-separateur" />

                            <Link
                                href="/dashboard"
                                class="musee-nav__dropdown-item"
                                role="menuitem"
                                @click="fermerMenu"
                            >
                                <LayoutDashboard class="musee-nav__dropdown-icone" />
                                Tableau de bord
                            </Link>

                            <button
                                type="button"
                                class="musee-nav__dropdown-item musee-nav__dropdown-item--danger"
                                role="menuitem"
                                @click="seDeconnecter"
                            >
                                <LogOut class="musee-nav__dropdown-icone" />
                                Se déconnecter
                            </button>
                        </template>

                        <!-- Invité -->
                        <template v-else>
                            <p class="musee-nav__dropdown-invite">
                                Accédez à votre espace ou connectez-vous pour participer.
                            </p>

                            <div class="musee-nav__dropdown-separateur" />

                            <Link
                                href="/login"
                                class="musee-nav__dropdown-item"
                                role="menuitem"
                                @click="fermerMenu"
                            >
                                <LogIn class="musee-nav__dropdown-icone" />
                                Se connecter
                            </Link>
                        </template>
                    </div>
                </Transition>
            </div>
        </div>
    </nav>
</template>

<style scoped>
/* ─── Barre de navigation ───────────────────────────────────────────────────── */

.musee-nav {
    position: sticky;
    top: 0;
    z-index: 50;
    background-color: #0e0e24;
    border-bottom: 1px solid rgba(201, 160, 64, 0.15);
}

.musee-nav__inner {
    max-width: 72rem;
    margin: 0 auto;
    padding: 0 1.5rem;
    height: 3.25rem;
    display: flex;
    align-items: center;
    gap: 2rem;
}

/* ─── Marque ────────────────────────────────────────────────────────────────── */

.musee-nav__marque {
    font-size: 1.05rem;
    font-weight: 700;
    color: #e8d5a3;
    text-decoration: none;
    letter-spacing: -0.01em;
    white-space: nowrap;
    flex-shrink: 0;
}

.musee-nav__marque:hover {
    color: #f0e0b0;
}

/* ─── Liens ─────────────────────────────────────────────────────────────────── */

.musee-nav__liens {
    display: flex;
    align-items: center;
    gap: 0.125rem;
    list-style: none;
    margin: 0;
    padding: 0;
    flex: 1;
}

.musee-nav__lien {
    display: inline-flex;
    align-items: center;
    height: 3.25rem;
    padding: 0 0.875rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: rgba(200, 200, 216, 0.65);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    transition: color 0.15s, border-color 0.15s;
}

.musee-nav__lien:hover {
    color: rgba(200, 200, 216, 0.95);
}

.musee-nav__lien--actif {
    color: #c9a040;
    border-bottom-color: #c9a040;
    font-weight: 600;
}

/* ─── Profil ────────────────────────────────────────────────────────────────── */

.musee-nav__profil {
    position: relative;
    margin-left: auto;
    flex-shrink: 0;
}

.musee-nav__profil-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    color: rgba(200, 200, 216, 0.55);
    cursor: pointer;
    transition: color 0.15s;
    border-radius: 50%;
    padding: 0.25rem;
}

.musee-nav__profil-btn:hover {
    color: #c9a040;
}

.musee-nav__profil-icone {
    width: 1.625rem;
    height: 1.625rem;
}

/* ─── Dropdown ──────────────────────────────────────────────────────────────── */

.musee-nav__dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    min-width: 13rem;
    background-color: #131330;
    border: 1px solid rgba(201, 160, 64, 0.2);
    border-radius: 0.625rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
    overflow: hidden;
    z-index: 60;
}

.musee-nav__dropdown-entete {
    padding: 0.875rem 1rem 0.75rem;
}

.musee-nav__dropdown-nom {
    font-size: 0.875rem;
    font-weight: 600;
    color: #e8d5a3;
    margin: 0;
}

.musee-nav__dropdown-role {
    font-size: 0.7rem;
    color: rgba(200, 200, 216, 0.45);
    text-transform: capitalize;
    margin: 0.125rem 0 0;
}

.musee-nav__dropdown-invite {
    padding: 0.875rem 1rem 0.625rem;
    font-size: 0.8rem;
    color: rgba(200, 200, 216, 0.6);
    line-height: 1.5;
    margin: 0;
}

.musee-nav__dropdown-separateur {
    height: 1px;
    background-color: rgba(201, 160, 64, 0.12);
    margin: 0;
}

.musee-nav__dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    color: rgba(200, 200, 216, 0.75);
    text-decoration: none;
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background-color 0.12s, color 0.12s;
}

.musee-nav__dropdown-item:hover {
    background-color: rgba(201, 160, 64, 0.08);
    color: #e8d5a3;
}

.musee-nav__dropdown-item--danger:hover {
    background-color: rgba(220, 60, 60, 0.1);
    color: #f87171;
}

.musee-nav__dropdown-icone {
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
    opacity: 0.7;
}

/* ─── Transition du menu ────────────────────────────────────────────────────── */

.menu-enter-active,
.menu-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.menu-enter-from,
.menu-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}
</style>
