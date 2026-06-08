<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    Grid2x2,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import typesProjetsRoutes from '@/routes/types-projets';

const { t } = useI18n();

type GrilleResume = { id: number; nom: string } | null;

type SectionType = 'texte' | 'paragraphes' | 'individuel';

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    type: SectionType;
};

const sectionTypeLabels = computed<Record<SectionType, string>>(() => ({
    texte: t('types_projet.edit.section_type_texte_label'),
    paragraphes: t('types_projet.edit.section_type_paragraphes_label'),
    individuel: t('types_projet.edit.section_type_individuel_label'),
}));

type TypeProjet = {
    id: number;
    nom: string;
    description: string | null;
    accessible: boolean;
    grille: GrilleResume;
    sections: Section[];
};

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
};

type Props = {
    cours: Cours;
    typesProjets: TypeProjet[];
};

const props = defineProps<Props>();

// ─── Toggle accessible ────────────────────────────────────────────────────────
const toggleForm = useForm({});

function toggleAccessible(tp: TypeProjet) {
    toggleForm.patch(
        typesProjetsRoutes.toggleAccessible.url({
            cours: props.cours.id,
            typeProjet: tp.id,
        }),
    );
}

// ─── Suppression TypeProjet ───────────────────────────────────────────────────
const deleteForm = useForm({});

function supprimer(tp: TypeProjet) {
    if (!confirm(t('types_projet.index.confirm_delete', { nom: tp.nom }))) {
        return;
    }
    deleteForm.delete(
        typesProjetsRoutes.destroy.url({
            cours: props.cours.id,
            typeProjet: tp.id,
        }),
    );
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('types_projet.index.page_title')" />

        <div class="mx-auto flex max-w-3xl flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <Heading
                    :title="$t('types_projet.index.heading_title')"
                    :description="$t('types_projet.index.heading_description')"
                />
                <Button size="sm" as-child>
                    <Link :href="typesProjetsRoutes.create.url(cours.id)">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('types_projet.index.new_type') }}
                    </Link>
                </Button>
            </div>

            <!-- Liste vide -->
            <Card v-if="props.typesProjets.length === 0">
                <CardContent class="py-10 text-center text-muted-foreground">
                    {{ $t('types_projet.index.no_types') }}
                </CardContent>
            </Card>

            <!-- Liste des types -->
            <Card v-for="tp in props.typesProjets" :key="tp.id">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                {{ tp.nom }}
                                <Badge
                                    :class="
                                        tp.accessible
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        tp.accessible
                                            ? $t(
                                                  'types_projet.index.badge_accessible',
                                              )
                                            : $t(
                                                  'types_projet.index.badge_not_accessible',
                                              )
                                    }}
                                </Badge>
                            </CardTitle>
                            <p
                                v-if="tp.description"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{ tp.description }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <BoutonTooltip
                                size="sm"
                                :variant="
                                    tp.accessible ? 'outline' : 'secondary'
                                "
                                :texte="
                                    tp.accessible
                                        ? 'Masquer ce type de projet aux étudiants'
                                        : 'Rendre ce type de projet accessible aux étudiants'
                                "
                                class="text-xs"
                                :disabled="toggleForm.processing"
                                @click="toggleAccessible(tp)"
                            >
                                <ChevronRight
                                    v-if="!tp.accessible"
                                    class="mr-1 h-3 w-3"
                                />
                                <ChevronDown v-else class="mr-1 h-3 w-3" />
                                {{
                                    tp.accessible
                                        ? $t('types_projet.index.btn_hide')
                                        : $t(
                                              'types_projet.index.btn_make_accessible',
                                          )
                                }}
                            </BoutonTooltip>

                            <BoutonTooltip
                                texte="Modifier ce type de projet"
                                size="icon"
                                variant="ghost"
                                class="h-8 w-8"
                                as-child
                            >
                                <Link
                                    :href="
                                        typesProjetsRoutes.edit.url({
                                            cours: cours.id,
                                            typeProjet: tp.id,
                                        })
                                    "
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                            </BoutonTooltip>

                            <BoutonTooltip
                                texte="Supprimer ce type de projet"
                                size="icon"
                                variant="ghost"
                                class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                :disabled="deleteForm.processing"
                                @click="supprimer(tp)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </BoutonTooltip>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="flex flex-col gap-4">
                    <!-- Grille associée -->
                    <div class="flex items-center gap-3">
                        <Grid2x2 class="h-4 w-4 text-muted-foreground" />
                        <span class="text-sm text-muted-foreground">{{
                            $t('types_projet.index.grille_label')
                        }}</span>
                        <a
                            :href="
                                typesProjetsRoutes.grille.edit.url({
                                    cours: cours.id,
                                    typeProjet: tp.id,
                                })
                            "
                            class="text-sm font-medium text-primary hover:underline"
                        >
                            {{
                                tp.grille
                                    ? tp.grille.nom
                                    : $t('types_projet.index.configure_grille')
                            }}
                        </a>
                    </div>

                    <!-- Sections (résumé) -->
                    <div class="border-t pt-3">
                        <p
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            {{ tp.sections.length }} section{{
                                tp.sections.length !== 1 ? 's' : ''
                            }}
                            <span
                                v-if="tp.sections.length === 0"
                                class="font-normal italic"
                            >
                                {{ $t('types_projet.index.default_intro') }}
                            </span>
                        </p>
                        <div
                            v-if="tp.sections.length > 0"
                            class="flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="s in [...tp.sections].sort(
                                    (a, b) => a.ordre - b.ordre,
                                )"
                                :key="s.id"
                                class="rounded-md bg-muted px-2 py-0.5 text-xs"
                            >
                                {{ s.ordre }}. {{ s.label }}
                                <span class="ml-1 text-muted-foreground/60">
                                    ({{ sectionTypeLabels[s.type ?? 'texte'] }})
                                </span>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
