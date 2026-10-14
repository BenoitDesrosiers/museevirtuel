<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Eye } from 'lucide-vue-next';
import Heading from '@/components/shared/Heading.vue';
import ApercuSection from '@/components/projets/ApercuSection.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
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
    code: string;
    cours_id: number;
};

type Projet = {
    id: number;
    titre_projet: string | null;
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

type Renvoi = {
    id: number;
    numero: number;
    contenu: string | null;
};

const props = defineProps<{
    groupe: Groupe;
    classe: Classe;
    typeProjet: { id: number; nom: string };
    thematiques: Thematique[];
    membres: Membre[];
    projet: Projet | null;
    sections: Section[];
    renvois: Renvoi[];
    estEnseignant: boolean;
}>();

/** Construit l'URL de base pour les routes du projet de ce groupe. */
const baseUrl = `/cours/${props.classe.cours_id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets/${props.typeProjet.id}`;

</script>

<template>
    <AppLayout>
        <Head
            :title="`${$t('apercu.preview_label')} — ${projet?.titre_projet ?? $t('projets.index.heading_title')}`"
        />

        <div class="mx-auto flex max-w-4xl flex-col gap-6 p-6">
            <!-- Retour -->
            <div class="flex items-center justify-between">
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`${baseUrl}/edit`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('apercu.back_to_editor') }}
                    </Link>
                </Button>

                <!-- Boutons export (enseignant seulement) -->
                <div v-if="estEnseignant" class="flex gap-2">
                    <BoutonTooltip
                        texte="Télécharger en PDF (s'ouvre dans un nouvel onglet)"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <a :href="`${baseUrl}/pdf`" target="_blank">
                            <Download class="mr-2 h-4 w-4" />
                            PDF
                        </a>
                    </BoutonTooltip>
                    <BoutonTooltip
                        texte="Télécharger en Word"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <a :href="`${baseUrl}/word`" target="_blank">
                            <Download class="mr-2 h-4 w-4" />
                            Word
                        </a>
                    </BoutonTooltip>
                </div>
            </div>

            <!-- Heading -->
            <div>
                <div class="mb-1 flex items-center gap-2">
                    <Eye class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">{{
                        $t('apercu.preview_label')
                    }}</span>
                </div>
                <Heading
                    :title="
                        projet?.titre_projet ??
                        $t('projets.index.heading_title')
                    "
                    :description="`${classe.code} — Groupe ${classe.groupe} · ${classe.nom_cours} · Groupe ${groupe.numero}`"
                />
                <div
                    v-if="thematiques.length > 0"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <span
                        v-for="thematique in thematiques"
                        :key="thematique.id"
                        class="rounded-full bg-primary/10 px-3 py-1 text-sm text-primary"
                    >
                        {{ thematique.nom }}
                    </span>
                </div>
            </div>

            <!-- Contenu vide -->
            <div
                v-if="!projet"
                class="py-12 text-center text-sm text-muted-foreground"
            >
                {{ $t('apercu.no_project') }}
            </div>

            <template v-else>
                <p
                    v-if="sections.length === 0"
                    class="text-sm text-muted-foreground italic"
                >
                    {{ $t('apercu.no_sections') }}
                </p>

                <ApercuSection
                    v-for="section in sections"
                    :key="section.id"
                    :section="section"
                    :membres="membres"
                />
            </template>

            <!-- ─── Références (renvois / endnotes) ──────────────────────── -->
            <section v-if="renvois.length > 0" class="space-y-3 border-t pt-6">
                <h2 class="border-b pb-2 text-xl font-semibold">Références</h2>
                <ol class="space-y-2 text-sm">
                    <li
                        v-for="renvoi in renvois"
                        :id="`renvoi-${renvoi.numero}`"
                        :key="renvoi.id"
                        class="flex items-start gap-2"
                    >
                        <span
                            class="min-w-[1.5rem] text-right font-bold text-blue-600 dark:text-blue-400"
                        >
                            {{ renvoi.numero }}.
                        </span>
                        <span class="text-muted-foreground">{{
                            renvoi.contenu || '—'
                        }}</span>
                    </li>
                </ol>
            </section>
        </div>
    </AppLayout>
</template>
