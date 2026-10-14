<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FolderOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/shared/Heading.vue';
import ProjetCard from '@/components/projets/ProjetCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

type TypeProjetResume = {
    id: number;
    nom: string;
    description: string | null;
    type: 'standard' | 'musee';
    accessible: boolean;
};

type StatutProjet = 'en_cours' | 'remis' | 'en_retard' | 'remis_en_retard';

type ProjetCardData = {
    typeProjet: TypeProjetResume;
    projet: {
        id: number;
        titre_projet: string | null;
        completion: number;
        statut: StatutProjet;
        statut_publication:
            | 'brouillon'
            | 'soumis'
            | 'approuve'
            | 'rejete'
            | null;
    } | null;
    statut: StatutProjet;
    conclusions: {
        etudiant: {
            id: number;
            prenom: string;
            nom: string;
        };
        a_redige: boolean;
    }[];
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
    nom_cours?: string;
};

type Props = {
    groupe: Groupe;
    classe: Classe;
    projets: ProjetCardData[];
    estEnseignant: boolean;
};

const props = defineProps<Props>();

const projetsVisibles = computed(() =>
    props.estEnseignant
        ? props.projets.filter((card) => card.typeProjet.accessible)
        : props.projets,
);
</script>

<template>
    <AppLayout>
        <Head title="Projets" />

        <div class="flex flex-col gap-6 p-6">
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        :href="`/cours/${classe.cours_id}/classes/${classe.id}`"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour à la classe
                    </Link>
                </Button>
            </div>

            <Heading
                title="Projets"
                :description="`Groupe ${groupe.id} · ${classe.code}`"
            />

            <div
                v-if="projetsVisibles.length === 0"
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

            <div
                v-else
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <ProjetCard
                    v-for="card in projetsVisibles"
                    :key="card.typeProjet.id"
                    :card="card"
                    :cours-id="classe.cours_id"
                    :classe-id="groupe.classe_id"
                    :groupe-id="groupe.id"
                    :est-enseignant="estEnseignant"
                />
            </div>
        </div>
    </AppLayout>
</template>
