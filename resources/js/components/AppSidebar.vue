<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, FolderKanban, Home, LayoutGrid } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { CoursSidebar, GroupeSidebar, NavData, NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);
const navData = computed(() => page.props.navData as NavData | null);

const sessionLabels: Record<string, string> = {
    hiver: 'Hiver',
    ete: 'Été',
    automne: 'Automne',
};

/**
 * Construit les items de navigation hiérarchiques pour un enseignant
 * à partir des données navData partagées par le middleware.
 */
function buildEnseignantItems(): NavItem[] {
    const cours = navData.value?.cours ?? [];

    return cours.map((c: CoursSidebar) => ({
        title: `${c.code} ${sessionLabels[c.session] ?? c.session} ${c.annee}`,
        href: `/cours/${c.id}`,
        icon: BookOpen,
        children: [
            {
                title: t('sidebar.project_types'),
                href: `/cours/${c.id}/types-projets`,
                icon: FolderKanban,
            },
            ...c.classes.map((cl) => ({
                title: cl.nom || `${t('sidebar.section')} ${cl.numero}`,
                href: `/cours/${c.id}/classes/${cl.id}`,
                children: cl.groupes.map((g: GroupeSidebar) => ({
                    title: `${t('sidebar.group')} ${g.numero}`,
                    href: `/cours/${c.id}/classes/${cl.id}/groupes/${g.id}`,
                    children: [
                        {
                            title: t('sidebar.projects'),
                            href: `/cours/${c.id}/classes/${cl.id}/groupes/${g.id}/projets`,
                            children: g.projets.map((p) => ({
                                title: p.titre,
                                href: `/cours/${c.id}/classes/${cl.id}/groupes/${g.id}/projets/${p.type_projet_id}/edit`,
                            })),
                        },
                        ...(g.hasTemoin
                            ? [
                                  {
                                      title: t('sidebar.discussion_temoin'),
                                      href: `/cours/${c.id}/classes/${cl.id}/groupes/${g.id}/echanges`,
                                  },
                              ]
                            : []),
                    ],
                })),
            })),
        ],
    }));
}

/**
 * Construit les items de navigation hiérarchiques pour un étudiant
 * à partir des données navData partagées par le middleware.
 */
function buildEtudiantItems(): NavItem[] {
    const cours = navData.value?.cours ?? [];

    // L'étudiant n'est que dans une seule classe par cours :
    // - le cours mène directement à sa classe (href sur la classe)
    // - le groupe est un lien direct vers la page du groupe
    return cours.map((c: CoursSidebar) => ({
        title: `${c.code}–${c.groupe}`,
        href: c.classes[0]
            ? `/cours/${c.id}/classes/${c.classes[0].id}`
            : undefined,
        icon: BookOpen,
        children: c.classes.flatMap((cl) =>
            cl.groupes.map((g: GroupeSidebar) => ({
                title: `${t('sidebar.group')} ${g.numero}`,
                href: `/cours/${c.id}/classes/${cl.id}/groupes/${g.id}`,
                children: [
                    {
                        title: t('sidebar.projects'),
                        href: `/cours/${c.id}/classes/${cl.id}/groupes/${g.id}/projets`,
                        children: g.projets.map((p) => ({
                            title: p.titre,
                            href: `/cours/${c.id}/classes/${cl.id}/groupes/${g.id}/projets/${p.type_projet_id}/edit`,
                        })),
                    },
                    ...(g.hasTemoin
                        ? [
                              {
                                  title: t('sidebar.discussion_temoin'),
                                  href: `/cours/${c.id}/classes/${cl.id}/groupes/${g.id}/echanges`,
                              },
                          ]
                        : []),
                ],
            })),
        ),
    }));
}

const mainNavItems = computed((): NavItem[] => {
    const role = user.value?.role;

    if (role === 'admin') {
        return [
            {
                title: t('sidebar.administration'),
                href: '/administration',
                icon: LayoutGrid,
            },
            {
                title: t('sidebar.home'),
                href: '/enseignant',
                icon: Home,
            },
            ...buildEnseignantItems(),
        ];
    }

    if (role === 'enseignant') {
        return [
            {
                title: t('sidebar.home'),
                href: '/enseignant',
                icon: Home,
            },
            ...buildEnseignantItems(),
        ];
    }

    if (role === 'etudiant') {
        return [
            {
                title: t('sidebar.home'),
                href: '/cours',
                icon: Home,
            },
            ...buildEtudiantItems(),
        ];
    }

    return [];
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
