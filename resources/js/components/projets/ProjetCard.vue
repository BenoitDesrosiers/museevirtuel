<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    CheckCircle2,
    ChevronRight,
    Clock,
    FileEdit,
    Landmark,
    Settings2,
    XCircle,
} from 'lucide-vue-next';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import typesProjets from '@/routes/types-projets';

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type ConclusionResume = {
    etudiant: Etudiant;
    a_redige: boolean;
};

type StatutPublication = 'brouillon' | 'soumis' | 'approuve' | 'rejete';
type StatutProjet = 'en_cours' | 'remis' | 'en_retard' | 'remis_en_retard';

type ProjetResume = {
    id: number;
    titre_projet: string | null;
    completion: number;
    statut: StatutProjet;
    statut_publication: StatutPublication | null;
} | null;

type ProjetCardData = {
    typeProjet: {
        id: number;
        nom: string;
        description: string | null;
        type: 'standard' | 'musee';
        accessible: boolean;
    };
    projet: ProjetResume;
    statut: StatutProjet;
    conclusions: ConclusionResume[];
};

type Props = {
    card: ProjetCardData;
    coursId: number;
    classeId: number;
    groupeId: number;
    estEnseignant: boolean;
};

const props = defineProps<Props>();

function projetUrl(typeProjetId: number): string {
    return `/cours/${props.coursId}/classes/${props.classeId}/groupes/${props.groupeId}/projets/${typeProjetId}/edit`;
}

function statutPublicationLabel(statut: StatutPublication): string {
    return {
        brouillon: 'Brouillon',
        soumis: 'En attente',
        approuve: 'Approuvé',
        rejete: 'Rejeté',
    }[statut];
}

function statutPublicationClass(statut: StatutPublication): string {
    return {
        brouillon: 'bg-muted text-muted-foreground',
        soumis: 'bg-amber-100 text-amber-800',
        approuve: 'bg-emerald-100 text-emerald-800',
        rejete: 'bg-red-100 text-red-800',
    }[statut];
}

function statutProjetLabel(statut: StatutProjet): string {
    return {
        en_cours: 'En cours',
        remis: 'Remis',
        en_retard: 'En retard',
        remis_en_retard: 'Remis en retard',
    }[statut];
}

function statutProjetClass(statut: StatutProjet): string {
    return {
        en_cours:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        remis: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
        en_retard:
            'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        remis_en_retard:
            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }[statut];
}
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-2">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <CardTitle class="text-base">
                        {{ card.typeProjet.nom }}
                    </CardTitle>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                            statutProjetClass(card.statut),
                        ]"
                    >
                        {{ statutProjetLabel(card.statut) }}
                    </span>
                </div>
                <span
                    v-if="card.typeProjet.type === 'musee'"
                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-800 dark:bg-violet-900 dark:text-violet-200"
                >
                    <Landmark class="h-2.5 w-2.5" />
                    Musée
                </span>
            </div>
            <p
                v-if="card.typeProjet.description"
                class="text-xs text-muted-foreground"
            >
                {{ card.typeProjet.description }}
            </p>
        </CardHeader>

        <CardContent class="flex flex-1 flex-col gap-4">
            <div v-if="estEnseignant" class="flex items-center justify-end">
                <BoutonTooltip
                    texte="Gérer les sections disponibles pour ce type de projet"
                    variant="ghost"
                    size="default"
                    class="h-7 gap-1.5 px-2 text-xs text-muted-foreground"
                    as-child
                >
                    <Link
                        :href="
                            typesProjets.edit.url({
                                cours: coursId,
                                typeProjet: card.typeProjet.id,
                            })
                        "
                    >
                        <Settings2 class="h-3 w-3" />
                        Configurer les sections
                    </Link>
                </BoutonTooltip>
            </div>

            <div v-if="card.typeProjet.type === 'musee'">
                <p
                    class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Publication
                </p>
                <div
                    v-if="card.projet && card.projet.statut_publication"
                    class="flex items-center gap-2"
                >
                    <CheckCircle2
                        v-if="card.projet.statut_publication === 'approuve'"
                        class="h-4 w-4 shrink-0 text-emerald-500"
                    />
                    <Clock
                        v-else-if="
                            card.projet.statut_publication === 'soumis'
                        "
                        class="h-4 w-4 shrink-0 text-amber-500"
                    />
                    <XCircle
                        v-else
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                            statutPublicationClass(
                                card.projet.statut_publication,
                            ),
                        ]"
                    >
                        {{
                            statutPublicationLabel(
                                card.projet.statut_publication,
                            )
                        }}
                    </span>
                </div>
                <p v-else class="text-xs text-muted-foreground italic">
                    Musée non démarré
                </p>
            </div>

            <div v-else>
                <p
                    class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
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
                        <span
                            :class="
                                item.a_redige ? '' : 'text-muted-foreground'
                            "
                        >
                            {{ item.etudiant.prenom }}
                            {{ item.etudiant.nom }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-auto">
                <BoutonTooltip
                    size="sm"
                    :texte="
                        !estEnseignant
                            ? card.typeProjet.type === 'musee'
                                ? 'Ouvrir l\'éditeur du musée'
                                : 'Ouvrir et éditer votre projet'
                            : 'Consulter le projet du groupe'
                    "
                    :variant="!estEnseignant ? 'default' : 'outline'"
                    class="w-full"
                    as-child
                >
                    <Link :href="projetUrl(card.typeProjet.id)">
                        <component
                            :is="
                                card.typeProjet.type === 'musee'
                                    ? Landmark
                                    : !estEnseignant
                                      ? FileEdit
                                      : BookOpen
                            "
                            class="mr-2 h-4 w-4"
                        />
                        {{
                            card.typeProjet.type === 'musee'
                                ? estEnseignant
                                    ? 'Voir le musée'
                                    : 'Mon musée'
                                : !estEnseignant
                                  ? 'Ouvrir le projet'
                                  : 'Consulter'
                        }}
                        <ChevronRight class="ml-auto h-4 w-4" />
                    </Link>
                </BoutonTooltip>
            </div>
        </CardContent>
    </Card>
</template>
