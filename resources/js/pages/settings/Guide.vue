<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as indexAdmin } from '@/routes/administration';
import { edit as editAppearance } from '@/routes/appearance';
import { index as indexCours } from '@/routes/cours';
import { index as indexEnseignant } from '@/routes/enseignant';
import { index as guideIndex } from '@/routes/guide';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as indexTemoin } from '@/routes/temoin';
import type { BreadcrumbItem } from '@/types';

const { t, tm } = useI18n();
const page = usePage();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.guide.page_title'),
        href: guideIndex(),
    },
]);

const role = computed(() => page.props.auth.user.role);

/**
 * Clés de sections disponibles par rôle.
 * L'ordre détermine l'affichage dans la page.
 */
const sectionKeysByRole: Record<string, string[]> = {
    enseignant: [
        'creer_cours',
        'gerer_classes',
        'importer_etudiants',
        'types_projets',
        'grille_correction',
        'objectifs',
        'reference_apa',
        'echeancier',
        'visio',
        'approuver_temoin',
        'verrouiller_cours',
        'echanges',
    ],
    etudiant: [
        'rejoindre_cours',
        'former_groupe',
        'editer_projet',
        'remettre_projet',
        'consulter_notes',
        'echanges',
    ],
    personne_agee: ['profil', 'consentement', 'entrevue'],
    admin: ['gerer_enseignants', 'gerer_etablissements', 'approuver_temoins'],
};

const generalKeys = ['profil', 'langue', 'theme', 'securite'];

/**
 * Liens directs pour les sections générales (paramètres).
 * Chaque clé mène à la page de paramètres exacte.
 */
const generalLinks: Record<string, { labelKey: string; href: string }> = {
    profil: { labelKey: 'settings.guide.links.profile', href: editProfile() },
    langue: { labelKey: 'settings.guide.links.profile', href: editProfile() },
    theme: {
        labelKey: 'settings.guide.links.appearance',
        href: editAppearance(),
    },
    securite: {
        labelKey: 'settings.guide.links.security',
        href: editSecurity(),
    },
};

/**
 * Liens vers l'espace de travail par rôle.
 * Les sections spécifiques à un rôle nécessitent des IDs dynamiques,
 * on redirige donc vers le point d'entrée du rôle.
 */
const roleWorkspaceLinks: Record<string, { labelKey: string; href: string }> = {
    enseignant: {
        labelKey: 'settings.guide.links.workspace_enseignant',
        href: indexEnseignant(),
    },
    etudiant: {
        labelKey: 'settings.guide.links.workspace_etudiant',
        href: indexCours(),
    },
    personne_agee: {
        labelKey: 'settings.guide.links.workspace_temoin',
        href: indexTemoin(),
    },
    admin: {
        labelKey: 'settings.guide.links.workspace_admin',
        href: indexAdmin(),
    },
};

/**
 * Retourne les étapes d'une section depuis i18n sous forme de tableau.
 */
function getSteps(path: string): string[] {
    const steps = tm(path);
    return Array.isArray(steps) ? (steps as string[]) : [];
}

const roleSections = computed(() => sectionKeysByRole[role.value] ?? []);
const roleLink = computed(() => roleWorkspaceLinks[role.value] ?? null);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.guide.page_title')" />

        <h1 class="sr-only">{{ $t('settings.guide.page_title') }}</h1>

        <SettingsLayout>
            <div class="space-y-8">
                <Heading
                    variant="small"
                    :title="$t('settings.guide.heading_title')"
                    :description="$t('settings.guide.heading_description')"
                />

                <!-- Section générale : paramètres et compte -->
                <section class="space-y-1">
                    <h2 class="mb-3 text-sm font-semibold text-foreground">
                        {{ $t('settings.guide.section_general') }}
                    </h2>

                    <div
                        class="divide-y divide-input rounded-md border border-input"
                    >
                        <details
                            v-for="key in generalKeys"
                            :key="key"
                            class="group"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-medium select-none hover:bg-muted/40 [&::-webkit-details-marker]:hidden"
                            >
                                {{ $t(`settings.guide.general.${key}.title`) }}
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180"
                                />
                            </summary>

                            <div
                                class="space-y-4 border-t border-input px-4 py-4"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        $t(
                                            `settings.guide.general.${key}.description`,
                                        )
                                    }}
                                </p>
                                <ol
                                    class="ml-4 list-decimal space-y-1 text-sm text-muted-foreground"
                                >
                                    <li
                                        v-for="(step, i) in getSteps(
                                            `settings.guide.general.${key}.steps`,
                                        )"
                                        :key="i"
                                    >
                                        {{ step }}
                                    </li>
                                </ol>
                                <Link
                                    :href="generalLinks[key].href"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                >
                                    {{ $t(generalLinks[key].labelKey) }}
                                    <ArrowRight class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </details>
                    </div>
                </section>

                <!-- Section spécifique au rôle -->
                <section class="space-y-1">
                    <h2 class="mb-3 text-sm font-semibold text-foreground">
                        {{ $t('settings.guide.section_role') }}
                    </h2>

                    <div
                        class="divide-y divide-input rounded-md border border-input"
                    >
                        <details
                            v-for="key in roleSections"
                            :key="key"
                            class="group"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-medium select-none hover:bg-muted/40 [&::-webkit-details-marker]:hidden"
                            >
                                {{ $t(`settings.guide.${role}.${key}.title`) }}
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180"
                                />
                            </summary>

                            <div
                                class="space-y-4 border-t border-input px-4 py-4"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        $t(
                                            `settings.guide.${role}.${key}.description`,
                                        )
                                    }}
                                </p>
                                <ol
                                    class="ml-4 list-decimal space-y-1 text-sm text-muted-foreground"
                                >
                                    <li
                                        v-for="(step, i) in getSteps(
                                            `settings.guide.${role}.${key}.steps`,
                                        )"
                                        :key="i"
                                    >
                                        {{ step }}
                                    </li>
                                </ol>
                                <Link
                                    v-if="roleLink"
                                    :href="roleLink.href"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                >
                                    {{ $t(roleLink.labelKey) }}
                                    <ArrowRight class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </details>
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
