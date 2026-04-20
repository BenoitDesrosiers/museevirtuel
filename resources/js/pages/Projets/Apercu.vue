<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Eye } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

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

type Renvoi = {
    id: number;
    numero: number;
    contenu: string | null;
};

const props = defineProps<{
    groupe: Groupe;
    classe: Classe;
    thematiques: Thematique[];
    membres: Membre[];
    projet: Projet | null;
    sections: Section[];
    renvois: Renvoi[];
    estEnseignant: boolean;
}>();

/** Construit l'URL de base pour les routes du projet de ce groupe. */
const baseUrl = `/cours/${props.classe.cours_id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets`;

/** Retrouve le nom d'un membre par son userId. */
function nomMembre(userId: number): string {
    const m = props.membres.find((m) => m.id === userId);
    return m ? `${m.prenom} ${m.nom}` : '—';
}
</script>

<template>
    <AppLayout>
        <Head :title="`${$t('apercu.preview_label')} — ${projet?.titre_projet ?? $t('projets.index.heading_title')}`" />

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
                <div class="mb-1 flex items-center gap-2">
                    <Eye class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">{{ $t('apercu.preview_label') }}</span>
                </div>
                <Heading
                    :title="projet?.titre_projet ?? $t('projets.index.heading_title')"
                    :description="`${classe.code} — Groupe ${classe.groupe} · ${classe.nom_cours} · Groupe ${groupe.numero}`"
                />
                <div v-if="thematiques.length > 0" class="mt-3 flex flex-wrap gap-2">
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
            <div v-if="!projet" class="py-12 text-center text-sm text-muted-foreground">
                {{ $t('apercu.no_project') }}
            </div>

            <template v-else>
                <p v-if="sections.length === 0" class="text-sm italic text-muted-foreground">
                    {{ $t('apercu.no_sections') }}
                </p>

                <section
                    v-for="section in sections"
                    :key="section.id"
                    class="space-y-3"
                >
                    <h2 class="border-b pb-2 text-xl font-semibold">{{ section.label }}</h2>
                    <p v-if="section.description" class="text-xs italic text-muted-foreground">
                        {{ section.description }}
                    </p>

                    <!-- Type texte -->
                    <template v-if="section.type === 'texte'">
                        <div
                            v-if="section.contenu && section.contenu.trim()"
                            class="prose prose-sm max-w-none dark:prose-invert"
                            v-html="section.contenu"
                        />
                        <p v-else class="text-sm italic text-muted-foreground">
                            {{ $t('apercu.section_not_written') }}
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
                        <p v-else class="text-sm italic text-muted-foreground">
                            {{ $t('apercu.no_paragraphs') }}
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
                        <p v-else class="text-sm italic text-muted-foreground">
                            {{ $t('apercu.no_conclusions') }}
                        </p>
                    </template>
                </section>
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
                        <span class="min-w-[1.5rem] text-right font-bold text-blue-600 dark:text-blue-400">
                            {{ renvoi.numero }}.
                        </span>
                        <span class="text-muted-foreground">{{ renvoi.contenu || '—' }}</span>
                    </li>
                </ol>
            </section>
        </div>
    </AppLayout>
</template>
