<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Eye } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

type Etudiant = {
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
    introduction_amener: string | null;
    introduction_poser: string | null;
    introduction_diviser: string | null;
};

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    contenu: string | null;
};

type Developpement = {
    id: number;
    ordre: number;
    titre: string | null;
    contenu: string | null;
};

type ConclusionMembre = {
    etudiant: Etudiant;
    contenu: string;
};

const props = defineProps<{
    groupe: Groupe;
    classe: Classe;
    thematiques: Thematique[];
    projet: Projet | null;
    sections: Section[];
    developpements: Developpement[];
    conclusions: ConclusionMembre[];
    estEnseignant: boolean;
}>();

/** Construit l'URL de base pour les routes du projet de ce groupe. */
const baseUrl = `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets`;
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
                <!-- ── Sections dynamiques (définies par le professeur) ── -->
                <template v-if="sections.length > 0">
                    <section
                        v-for="section in sections"
                        :key="section.id"
                        class="space-y-3"
                    >
                        <h2 class="text-xl font-semibold border-b pb-2">{{ section.label }}</h2>
                        <p v-if="section.description" class="text-xs text-muted-foreground italic">
                            {{ section.description }}
                        </p>
                        <div
                            v-if="section.contenu && section.contenu.trim()"
                            class="prose prose-sm max-w-none dark:prose-invert"
                            v-html="section.contenu"
                        />
                        <p v-else class="text-muted-foreground text-sm italic">
                            (Section non rédigée)
                        </p>
                    </section>
                </template>

                <!-- ── Introduction (fallback si aucune section) ─────────── -->
                <template v-else>
                <section class="space-y-6">
                    <h2 class="text-xl font-semibold border-b pb-2">Introduction</h2>

                    <div
                        v-if="projet.introduction_amener"
                        class="prose prose-sm max-w-none dark:prose-invert"
                        v-html="projet.introduction_amener"
                    />
                    <div
                        v-if="projet.introduction_poser"
                        class="prose prose-sm max-w-none dark:prose-invert"
                        v-html="projet.introduction_poser"
                    />
                    <div
                        v-if="projet.introduction_diviser"
                        class="prose prose-sm max-w-none dark:prose-invert"
                        v-html="projet.introduction_diviser"
                    />

                    <p
                        v-if="!projet.introduction_amener && !projet.introduction_poser && !projet.introduction_diviser"
                        class="text-muted-foreground text-sm italic"
                    >
                        Aucun contenu d'introduction rédigé.
                    </p>
                </section>
                </template>

                <!-- ── Développements ───────────────────────────────────── -->
                <section v-if="developpements.length > 0" class="space-y-8">
                    <h2 class="text-xl font-semibold border-b pb-2">Développement</h2>

                    <article
                        v-for="dev in developpements"
                        :key="dev.id"
                        class="space-y-3"
                    >
                        <h3 v-if="dev.titre" class="text-base font-semibold">
                            {{ dev.titre }}
                        </h3>
                        <div
                            v-if="dev.contenu"
                            class="prose prose-sm max-w-none dark:prose-invert"
                            v-html="dev.contenu"
                        />
                    </article>
                </section>

                <!-- ── Conclusions individuelles ────────────────────────── -->
                <section v-if="conclusions.length > 0" class="space-y-6">
                    <h2 class="text-xl font-semibold border-b pb-2">Conclusions</h2>

                    <article
                        v-for="conclusion in conclusions"
                        :key="conclusion.etudiant.id"
                        class="space-y-2"
                    >
                        <h3 class="text-sm font-semibold text-muted-foreground">
                            {{ conclusion.etudiant.prenom }} {{ conclusion.etudiant.nom }}
                        </h3>
                        <div
                            class="prose prose-sm max-w-none dark:prose-invert"
                            v-html="conclusion.contenu"
                        />
                    </article>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
