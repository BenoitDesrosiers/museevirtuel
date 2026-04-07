<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Eye } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

type Membre = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
};

type Groupe = {
    id: number;
    numero: number;
    classe_id: number;
};

type Classe = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Projet = {
    id: number;
    titre_projet: string | null;
};

type Paragraphe = {
    id: number;
    ordre: number;
    titre: string | null;
    contenu: string | null;
};

type ConclusionMembre = {
    userId: number;
    contenu: string;
};

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    type: 'texte' | 'paragraphes' | 'individuel';
    contenu: string | null;
    paragraphes: Paragraphe[] | null;
    conclusionsParMembre: ConclusionMembre[] | null;
};

const props = defineProps<{
    groupe: Groupe;
    classe: Classe;
    thematiques: Thematique[];
    membres: Membre[];
    projet: Projet | null;
    sections: Section[];
    estEnseignant: boolean;
}>();

/** Construit l'URL de base pour les routes du projet de ce groupe. */
const baseUrl = `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets`;

/** Retrouve le nom d'un membre par son userId. */
function nomMembre(userId: number): string {
    const m = props.membres.find((m) => m.id === userId);
    return m ? `${m.prenom} ${m.nom}` : '—';
}
</script>

<template>
    <AppLayout>
        <Head :title="`Aperçu — ${projet?.titre_projet ?? 'Projet de recherche'}`" />

        <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto">
            <!-- Retour -->
            <div class="flex items-center justify-between">
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`${baseUrl}/edit`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour à l'éditeur
                    </Link>
                </Button>

                <!-- Boutons export (enseignant seulement) -->
                <div v-if="estEnseignant" class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`${baseUrl}/pdf`" target="_blank">
                            <Download class="mr-2 h-4 w-4" />
                            PDF
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`${baseUrl}/word`" target="_blank">
                            <Download class="mr-2 h-4 w-4" />
                            Word
                        </a>
                    </Button>
                </div>
            </div>

            <!-- Heading -->
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Eye class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">Aperçu du projet</span>
                </div>
                <Heading
                    :title="projet?.titre_projet ?? 'Projet de recherche'"
                    :description="`${classe.code} — Groupe ${classe.groupe} · ${classe.nom_cours} · Groupe ${groupe.numero}`"
                />
                <div v-if="thematiques.length > 0" class="flex flex-wrap gap-2 mt-3">
                    <span
                        v-for="thematique in thematiques"
                        :key="thematique.id"
                        class="bg-primary/10 text-primary rounded-full px-3 py-1 text-sm"
                    >
                        {{ thematique.nom }}
                    </span>
                </div>
            </div>

            <!-- Contenu vide -->
            <div v-if="!projet" class="text-muted-foreground py-12 text-center text-sm">
                Le projet de recherche n'a pas encore été créé.
            </div>

            <template v-else>
                <p v-if="sections.length === 0" class="text-muted-foreground text-sm italic">
                    Aucune section définie pour ce type de projet.
                </p>

                <section
                    v-for="section in sections"
                    :key="section.id"
                    class="space-y-3"
                >
                    <h2 class="text-xl font-semibold border-b pb-2">{{ section.label }}</h2>
                    <p v-if="section.description" class="text-xs text-muted-foreground italic">
                        {{ section.description }}
                    </p>

                    <!-- Type texte -->
                    <template v-if="section.type === 'texte'">
                        <div
                            v-if="section.contenu && section.contenu.trim()"
                            class="prose prose-sm max-w-none dark:prose-invert"
                            v-html="section.contenu"
                        />
                        <p v-else class="text-muted-foreground text-sm italic">
                            (Section non rédigée)
                        </p>
                    </template>

                    <!-- Type paragraphes -->
                    <template v-else-if="section.type === 'paragraphes'">
                        <template v-if="section.paragraphes && section.paragraphes.length > 0">
                            <article
                                v-for="p in section.paragraphes"
                                :key="p.id"
                                class="space-y-2"
                            >
                                <h3 v-if="p.titre" class="text-base font-semibold">{{ p.titre }}</h3>
                                <div
                                    v-if="p.contenu && p.contenu.trim()"
                                    class="prose prose-sm max-w-none dark:prose-invert"
                                    v-html="p.contenu"
                                />
                            </article>
                        </template>
                        <p v-else class="text-muted-foreground text-sm italic">
                            (Aucun paragraphe rédigé)
                        </p>
                    </template>

                    <!-- Type individuel -->
                    <template v-else-if="section.type === 'individuel'">
                        <template v-if="section.conclusionsParMembre && section.conclusionsParMembre.length > 0">
                            <article
                                v-for="c in section.conclusionsParMembre"
                                :key="c.userId"
                                class="space-y-2"
                            >
                                <h3 class="text-sm font-semibold text-muted-foreground">
                                    {{ nomMembre(c.userId) }}
                                </h3>
                                <div
                                    class="prose prose-sm max-w-none dark:prose-invert"
                                    v-html="c.contenu"
                                />
                            </article>
                        </template>
                        <p v-else class="text-muted-foreground text-sm italic">
                            (Aucune conclusion rédigée)
                        </p>
                    </template>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
