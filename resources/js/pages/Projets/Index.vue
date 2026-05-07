<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, CheckCircle2, ChevronRight, FileEdit, FolderOpen, Settings2, XCircle } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type ConclusionResume = {
    etudiant: Etudiant;
    a_redige: boolean;
};

type TypeProjetResume = {
    id: number;
    nom: string;
    description: string | null;
};

type ProjetResume = {
    id: number;
    titre_projet: string | null;
    completion: number;
} | null;

type ProjetCard = {
    typeProjet: TypeProjetResume;
    projet: ProjetResume;
    conclusions: ConclusionResume[];
};

type Groupe = {
    id: number;
    nom: string | null;
    classe_id: number;
};

type Classe = {
    id: number;
    code: string;
    cours_id: number;
};

type Props = {
    groupe: Groupe;
    classe: Classe;
    projets: ProjetCard[];
    estEnseignant: boolean;
};

const props = defineProps<Props>();

function completionColor(pct: number): string {
    if (pct >= 80) return 'text-green-600 dark:text-green-400';
    if (pct >= 40) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-muted-foreground';
}

function completionBarClass(pct: number): string {
    if (pct >= 80) return 'bg-green-500';
    if (pct >= 40) return 'bg-yellow-500';
    return 'bg-primary/40';
}

function projetUrl(typeProjetId: number): string {
    return `/cours/${props.classe.cours_id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets/${typeProjetId}/edit`;
}
</script>

<template>
    <AppLayout>
        <Head title="Projets" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour à la classe -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/cours/${classe.cours_id}/classes/${classe.id}`">
                        ← Retour à la classe
                    </Link>
                </Button>
            </div>

            <Heading
                title="Projets"
                :description="`Groupe ${groupe.id} · ${classe.code} — ${classe.nom_cours}`"
            />

            <!-- Aucun projet disponible -->
            <div
                v-if="projets.length === 0"
                class="flex flex-col items-center gap-3 rounded-lg border border-dashed p-10 text-center"
            >
                <FolderOpen class="h-10 w-10 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">
                    Aucun projet disponible pour l'instant.
                </p>
                <p v-if="!estEnseignant" class="text-xs text-muted-foreground">
                    L'enseignant n'a pas encore rendu de projet accessible.
                </p>
            </div>

            <!-- Grille des projets — une carte par TypeProjet -->
            <div
                v-else
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="card in projets"
                    :key="card.typeProjet.id"
                    class="flex flex-col"
                >
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">
                            {{ card.typeProjet.nom }}
                        </CardTitle>
                        <p
                            v-if="card.typeProjet.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ card.typeProjet.description }}
                        </p>
                    </CardHeader>

                    <CardContent class="flex flex-1 flex-col gap-4">
                        <!-- Lien configuration sections (enseignant seulement) -->
                        <div v-if="estEnseignant" class="flex items-center justify-end">
                            <BoutonTooltip
                                texte="Gérer les sections disponibles pour ce type de projet"
                                variant="ghost"
                                size="default"
                                class="h-7 gap-1.5 px-2 text-xs text-muted-foreground"
                                as-child
                            >
                                <Link href="/types-projets">
                                    <Settings2 class="h-3 w-3" />
                                    Configurer les sections
                                </Link>
                            </BoutonTooltip>
                        </div>
                        <!-- Barre de progression -->
                        <div v-if="card.projet">
                            <div class="mb-1 flex items-center justify-between">
                                <span class="text-xs text-muted-foreground">
                                    {{ card.projet.titre_projet ?? 'Sans titre' }}
                                </span>
                                <span
                                    class="text-xs font-medium"
                                    :class="completionColor(card.projet.completion)"
                                >
                                    {{ card.projet.completion }}%
                                </span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="completionBarClass(card.projet.completion)"
                                    :style="{ width: `${card.projet.completion}%` }"
                                />
                            </div>
                        </div>

                        <div v-else class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                            Pas encore démarré
                        </div>

                        <!-- Conclusions par membre -->
                        <div>
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Conclusions individuelles
                            </p>
                            <div class="space-y-1">
                                <div
                                    v-for="item in card.conclusions"
                                    :key="item.etudiant.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <CheckCircle2
                                        v-if="item.a_redige"
                                        class="h-4 w-4 shrink-0 text-green-500"
                                    />
                                    <XCircle
                                        v-else
                                        class="h-4 w-4 shrink-0 text-muted-foreground"
                                    />
                                    <span :class="item.a_redige ? '' : 'text-muted-foreground'">
                                        {{ item.etudiant.prenom }} {{ item.etudiant.nom }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Bouton d'accès (poussé vers le bas) -->
                        <div class="mt-auto pt-2">
                            <BoutonTooltip
                                size="sm"
                                :texte="!estEnseignant ? 'Ouvrir et éditer votre projet' : 'Consulter le projet du groupe'"
                                :variant="!estEnseignant ? 'default' : 'outline'"
                                class="w-full"
                                as-child
                            >
                                <Link :href="projetUrl(card.typeProjet.id)">
                                    <component
                                        :is="!estEnseignant ? FileEdit : BookOpen"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ !estEnseignant ? 'Ouvrir le projet' : 'Consulter' }}
                                    <ChevronRight class="ml-auto h-4 w-4" />
                                </Link>
                            </BoutonTooltip>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
