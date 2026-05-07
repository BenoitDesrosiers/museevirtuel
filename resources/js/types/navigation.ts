import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    /** Absent pour les items collapsibles sans destination propre. */
    href?: string;
    icon?: LucideIcon;
    isActive?: boolean;
    children?: NavItem[];
};

/** Données de navigation partagées via le middleware Inertia. */
export type ProjetSidebar = {
    type_projet_id: number;
    titre: string;
};

export type GroupeSidebar = {
    id: number;
    numero: number;
    hasTemoin: boolean;
    projets: ProjetSidebar[];
};

export type ClasseSidebar = {
    id: number;
    nom: string;
    numero: string;
    groupes: GroupeSidebar[];
};

export type CoursSidebar = {
    id: number;
    nom: string;
    code: string;
    groupe: string;
    annee: number;
    session: string;
    classes: ClasseSidebar[];
};

export type NavData = {
    cours: CoursSidebar[];
};
