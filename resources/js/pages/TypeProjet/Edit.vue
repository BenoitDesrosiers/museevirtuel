<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    GripVertical,
    Info,
    List,
    Plus,
    Table2,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import critereRoutes from '@/actions/App/Http/Controllers/TypeProjetCritereController';
import CritereForm from '@/components/CritereForm.vue';
import type { Critere } from '@/components/CritereForm.vue';
import CritereTable from '@/components/CritereTable.vue';
import Heading from '@/components/Heading.vue';
import InfoTooltip from '@/components/InfoTooltip.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import typesProjets from '@/routes/types-projets';

const { t } = useI18n();

type SectionType = 'texte' | 'paragraphes' | 'individuel' | 'entrevue';

type SectionFormItem = {
    /** Clé locale stable pour Vue — indépendante du proxy réactif Inertia. */
    _uid: number;
    id?: number;
    label: string;
    description: string;
    type: SectionType;
};

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    type: SectionType;
    criteres: Critere[];
};

type TypeProjet = {
    id: number;
    nom: string;
    description: string | null;
    date_remise: string | null;
    remises_multiples: boolean;
    retard_permis: boolean;
    generer_page_titre: boolean;
    generer_table_matieres: boolean;
    aide_reference: boolean;
    ponderation: number | null;
    is_sommatif: boolean;
    sections: Section[];
};

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
};

type Props = {
    cours: Cours;
    typeProjet: TypeProjet;
    criteresGlobaux: Critere[];
};

const sectionTypes = computed<
    { value: SectionType; label: string; description: string }[]
>(() => [
    {
        value: 'texte',
        label: t('types_projet.edit.section_type_texte_label'),
        description: t('types_projet.edit.section_type_texte_desc'),
    },
    {
        value: 'paragraphes',
        label: t('types_projet.edit.section_type_paragraphes_label'),
        description: t('types_projet.edit.section_type_paragraphes_desc'),
    },
    {
        value: 'individuel',
        label: t('types_projet.edit.section_type_individuel_label'),
        description: t('types_projet.edit.section_type_individuel_desc'),
    },
    {
        value: 'entrevue',
        label: t('types_projet.edit.section_type_entrevue_label'),
        description: t('types_projet.edit.section_type_entrevue_desc'),
    },
]);

const props = defineProps<Props>();

/** Compteur local pour générer des clés stables sans dépendre du proxy Inertia. */
let _uidCounter = 0;

/** Contrôle l'ouverture du Dialog d'aide sur les modes de saisie. */
const modesInfoOuvert = ref(false);

/** Bascule l'affichage des critères entre liste et tableau. */
const vueModeCriteres = ref<'liste' | 'tableau'>('liste');

/**
 * Convertit une date ISO en format attendu par datetime-local (YYYY-MM-DDTHH:mm).
 */
function toDatetimeLocal(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    return iso.slice(0, 16);
}

const form = useForm({
    nom: props.typeProjet.nom,
    description: props.typeProjet.description ?? '',
    date_remise: toDatetimeLocal(props.typeProjet.date_remise),
    remises_multiples: props.typeProjet.remises_multiples,
    retard_permis: props.typeProjet.retard_permis,
    generer_page_titre: props.typeProjet.generer_page_titre,
    generer_table_matieres: props.typeProjet.generer_table_matieres,
    aide_reference: props.typeProjet.aide_reference,
    ponderation: props.typeProjet.ponderation,
    is_sommatif: props.typeProjet.is_sommatif,
    sections: props.typeProjet.sections.map<SectionFormItem>((s) => ({
        _uid: ++_uidCounter,
        id: s.id,
        label: s.label,
        description: s.description ?? '',
        type: s.type ?? 'texte',
    })),
});

/**
 * Ajoute une nouvelle section vide à la fin de la liste.
 */
function ajouterSection() {
    form.sections.push({
        _uid: ++_uidCounter,
        label: '',
        description: '',
        type: 'texte',
    });
}

/**
 * Supprime la section à l'index donné.
 */
function supprimerSection(idx: number) {
    form.sections.splice(idx, 1);
}

/**
 * Soumet le formulaire complet via PUT.
 */
function sauvegarder() {
    form.put(
        typesProjets.update.url({
            cours: props.cours.id,
            typeProjet: props.typeProjet.id,
        }),
        {
            onSuccess: () => {
                // Reste sur la page — le flash success s'affiche via Inertia
            },
        },
    );
}

// ─── Gestion des critères ─────────────────────────────────────────────────────

/**
 * Clé de la section dont le formulaire de nouveau critère est ouvert.
 * null = fermé, 'global' = critères globaux, number = id de section.
 */
const formOuvertePour = ref<'global' | number | null>(null);

/**
 * ID du critère en cours d'édition (toutes sections confondues).
 * null = aucun.
 */
const critereEnEdition = ref<number | null>(null);

/**
 * Supprime un critère via Inertia DELETE.
 */
function supprimerCritere(critereId: number) {
    router.delete(
        critereRoutes.destroy.url({
            cours: props.cours.id,
            typeProjet: props.typeProjet.id,
            critere: critereId,
        }),
        { preserveScroll: true },
    );
}

/**
 * Rend tous les critères d'un type (positif|négatif) visibles d'un seul coup.
 */
function rendreVisibles(type: 'positif' | 'negatif') {
    router.patch(
        critereRoutes.toggleVisibleGroupe.url({
            cours: props.cours.id,
            typeProjet: props.typeProjet.id,
        }),
        { type, visible: true },
        { preserveScroll: true },
    );
}

/**
 * Ouvre le formulaire de création pour une section ou les critères globaux.
 */
function ouvrirFormCreation(cle: 'global' | number) {
    critereEnEdition.value = null;
    formOuvertePour.value = formOuvertePour.value === cle ? null : cle;
}

/**
 * Ouvre le formulaire d'édition pour un critère spécifique.
 */
function ouvrirFormEdition(critereId: number) {
    formOuvertePour.value = null;
    critereEnEdition.value =
        critereEnEdition.value === critereId ? null : critereId;
}

/**
 * Ferme tous les formulaires ouverts (après sauvegarde ou annulation).
 */
function fermerForms() {
    formOuvertePour.value = null;
    critereEnEdition.value = null;
}

/**
 * Total global de tous les critères positifs (sections + globaux).
 * Affiché en permanence dans la barre sticky pour aider à atteindre 100 pts.
 */
const totalPointsGlobal = computed(() => {
    const ptsSections = props.typeProjet.sections.reduce((acc, s) => {
        return (
            acc +
            s.criteres
                .filter((c) => c.type === 'positif')
                .reduce((sum, c) => sum + (Number(c.pointage) || 0), 0)
        );
    }, 0);

    const ptsGlobaux = props.criteresGlobaux
        .filter((c) => c.type === 'positif')
        .reduce((acc, c) => acc + (Number(c.pointage) || 0), 0);

    return Math.round((ptsSections + ptsGlobaux) * 100) / 100 || 0;
});
</script>

<template>
    <AppLayout>
        <Head
            :title="`${$t('types_projet.edit.heading_title')} — ${props.typeProjet.nom}`"
        />

        <div class="mx-auto flex max-w-5xl flex-col gap-6 p-6">
            <!-- En-tête -->
            <div>
                <Link
                    :href="typesProjets.index.url(props.cours.id)"
                    class="mb-3 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    {{ $t('types_projet.edit.back') }}
                </Link>
                <Heading :title="$t('types_projet.edit.heading_title')" />
            </div>

            <!-- Informations générales -->
            <Card>
                <CardContent class="grid gap-4 pt-6">
                    <div class="grid gap-2">
                        <Label for="nom"
                            >{{ $t('types_projet.edit.label_name') }}
                            <span class="text-destructive">*</span></Label
                        >
                        <Input id="nom" v-model="form.nom" required />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">{{
                            $t('types_projet.edit.label_description')
                        }}</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                </CardContent>
            </Card>

            <!-- Paramètres de remise -->
            <Card>
                <CardContent class="grid gap-4 pt-6">
                    <h2 class="text-sm font-semibold">
                        {{ $t('types_projet.edit.submission_section') }}
                    </h2>

                    <div class="grid gap-2">
                        <Label for="date_remise">{{
                            $t('types_projet.edit.label_deadline')
                        }}</Label>
                        <Input
                            id="date_remise"
                            v-model="form.date_remise"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.date_remise" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox
                            id="remises_multiples"
                            v-model:checked="form.remises_multiples"
                        />
                        <div class="grid gap-0.5">
                            <Label
                                for="remises_multiples"
                                class="cursor-pointer"
                                >{{
                                    $t(
                                        'types_projet.edit.label_multiple_submissions',
                                    )
                                }}</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'types_projet.edit.multiple_submissions_hint',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox
                            id="retard_permis"
                            v-model:checked="form.retard_permis"
                        />
                        <div class="grid gap-0.5">
                            <Label for="retard_permis" class="cursor-pointer">{{
                                $t('types_projet.edit.label_late_submission')
                            }}</Label>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t('types_projet.edit.late_submission_hint')
                                }}
                            </p>
                        </div>
                    </div>

                    <h2 class="mt-2 text-sm font-semibold">
                        {{ $t('types_projet.edit.export_options_title') }}
                    </h2>

                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="generer_page_titre"
                            v-model:checked="form.generer_page_titre"
                        />
                        <div class="grid gap-0.5">
                            <Label
                                for="generer_page_titre"
                                class="cursor-pointer"
                                >{{
                                    $t(
                                        'types_projet.edit.label_generer_page_titre',
                                    )
                                }}</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                {{
                                    form.generer_page_titre
                                        ? $t('types_projet.edit.hint_auto')
                                        : ''
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="generer_table_matieres"
                            v-model:checked="form.generer_table_matieres"
                        />
                        <div class="grid gap-0.5">
                            <Label
                                for="generer_table_matieres"
                                class="cursor-pointer"
                                >{{
                                    $t(
                                        'types_projet.edit.label_generer_table_matieres',
                                    )
                                }}</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                {{
                                    form.generer_table_matieres
                                        ? $t('types_projet.edit.hint_auto')
                                        : ''
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="aide_reference"
                            v-model:checked="form.aide_reference"
                        />
                        <div class="grid gap-0.5">
                            <Label
                                for="aide_reference"
                                class="cursor-pointer"
                                >{{
                                    $t('types_projet.edit.label_aide_reference')
                                }}</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                {{
                                    form.aide_reference
                                        ? $t(
                                              'types_projet.edit.hint_aide_reference',
                                          )
                                        : ''
                                }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Évaluation -->
            <Card>
                <CardContent class="grid gap-4 pt-6">
                    <h2 class="text-sm font-semibold">Évaluation</h2>

                    <div class="grid gap-2">
                        <div class="flex items-center gap-1">
                            <Label for="ponderation">{{ $t('types_projet.edit.label_ponderation') }}</Label>
                            <InfoTooltip :texte="$t('types_projet.edit.tooltip_ponderation')" content-class="max-w-72" />
                        </div>
                        <Input
                            id="ponderation"
                            v-model.number="form.ponderation"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="ex: 60"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('types_projet.edit.hint_ponderation') }}
                        </p>
                        <InputError :message="form.errors.ponderation" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox
                            id="is_sommatif"
                            v-model:checked="form.is_sommatif"
                        />
                        <div class="grid gap-0.5">
                            <Label for="is_sommatif" class="cursor-pointer"
                                >Évaluation sommative</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                Coché : ce projet contribue à la note finale.
                                Décoché : formatif seulement.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Critères globaux ────────────────────────────────────────── -->
            <Card>
                <CardContent class="grid gap-3 pt-6">
                    <!-- En-tête + boutons masse -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <h2 class="text-sm font-semibold">
                                {{ $t('criteres.titre_global') }}
                            </h2>
                            <InfoTooltip
                                :texte="$t('criteres.tooltip_global_vs_section')"
                                content-class="max-w-72"
                                icon-class="h-3.5 w-3.5"
                            />
                        </div>
                        <div class="flex gap-1.5">
                            <button
                                type="button"
                                :class="[
                                    'rounded-md border p-1 transition-colors',
                                    vueModeCriteres === 'liste'
                                        ? 'border-primary bg-primary/5 text-primary'
                                        : 'border-border text-muted-foreground hover:border-muted-foreground/40',
                                ]"
                                :title="$t('criteres.vue_liste')"
                                @click="vueModeCriteres = 'liste'"
                            >
                                <List class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'rounded-md border p-1 transition-colors',
                                    vueModeCriteres === 'tableau'
                                        ? 'border-primary bg-primary/5 text-primary'
                                        : 'border-border text-muted-foreground hover:border-muted-foreground/40',
                                ]"
                                :title="$t('criteres.vue_tableau')"
                                @click="vueModeCriteres = 'tableau'"
                            >
                                <Table2 class="h-3.5 w-3.5" />
                            </button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-7 px-2 text-xs text-emerald-600 hover:text-emerald-700"
                                @click="rendreVisibles('positif')"
                            >
                                {{ $t('criteres.btn_visible_positifs') }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-7 px-2 text-xs text-rose-600 hover:text-rose-700"
                                @click="rendreVisibles('negatif')"
                            >
                                {{ $t('criteres.btn_visible_negatifs') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Liste des critères globaux existants (mode liste) -->
                    <div
                        v-if="criteresGlobaux.length > 0 && vueModeCriteres === 'liste'"
                        class="space-y-1.5"
                    >
                        <div
                            v-for="critere in criteresGlobaux"
                            :key="critere.id"
                            class="space-y-1"
                        >
                            <div
                                class="flex items-start gap-2 rounded-md border px-3 py-2 text-sm"
                            >
                                <span
                                    :class="[
                                        'mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-xs font-medium',
                                        critere.type === 'positif'
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
                                    ]"
                                >
                                    {{
                                        critere.type === 'positif'
                                            ? '+' + critere.pointage
                                            : '-' + critere.pointage
                                    }}
                                </span>
                                <span class="min-w-0 flex-1 text-xs">
                                    {{ critere.contenu }}
                                    <span
                                        v-if="critere.contenu_type === 'echelle'"
                                        class="ml-1 text-muted-foreground"
                                        >(échelle)</span
                                    >
                                </span>
                                <span
                                    v-if="!critere.visible"
                                    class="shrink-0 text-xs text-muted-foreground"
                                    >masqué</span
                                >
                                <div class="flex shrink-0 gap-1">
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="ouvrirFormEdition(critere.id)"
                                    >
                                        <span class="text-xs">{{ $t('criteres.btn_modifier') }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="supprimerCritere(critere.id)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <CritereForm
                                v-if="critereEnEdition === critere.id"
                                :cours-id="cours.id"
                                :type-projet-id="typeProjet.id"
                                :section-id="null"
                                :critere="critere"
                                @saved="fermerForms"
                                @cancelled="fermerForms"
                            />
                        </div>
                    </div>

                    <!-- Critères globaux (mode tableau) -->
                    <CritereTable
                        v-else-if="criteresGlobaux.length > 0 && vueModeCriteres === 'tableau'"
                        :criteres="criteresGlobaux"
                        :cours-id="cours.id"
                        :type-projet-id="typeProjet.id"
                        :section-id="null"
                        :critere-en-edition="critereEnEdition"
                        @edit="ouvrirFormEdition"
                        @delete="supprimerCritere"
                        @close="fermerForms"
                    />

                    <!-- Message vide -->
                    <p v-else class="text-xs text-muted-foreground">
                        {{ $t('criteres.aucun_critere') }}
                    </p>

                    <!-- Formulaire de création -->
                    <CritereForm
                        v-if="formOuvertePour === 'global'"
                        :cours-id="cours.id"
                        :type-projet-id="typeProjet.id"
                        :section-id="null"
                        @saved="fermerForms"
                        @cancelled="fermerForms"
                    />

                    <!-- Bouton ajouter -->
                    <Button
                        v-if="formOuvertePour !== 'global'"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="self-start"
                        @click="ouvrirFormCreation('global')"
                    >
                        <Plus class="mr-1.5 h-3.5 w-3.5" />
                        {{ $t('criteres.ajouter') }}
                    </Button>
                </CardContent>
            </Card>

            <!-- ─── Sections ──────────────────────────────────────────────────── -->
            <div class="flex flex-col gap-3">
                <div>
                    <h2 class="text-sm font-semibold">
                        {{ $t('types_projet.edit.sections_title') }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ $t('types_projet.edit.sections_hint') }}
                    </p>
                </div>

                <!-- Message vide -->
                <Card v-if="form.sections.length === 0">
                    <CardContent
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('types_projet.edit.no_sections') }}
                    </CardContent>
                </Card>

                <!-- Liste des sections (drag-and-drop) -->
                <VueDraggable
                    v-model="form.sections"
                    handle=".drag-handle"
                    :animation="150"
                    class="flex flex-col gap-3"
                >
                    <Card
                        v-for="(section, idx) in form.sections"
                        :key="section._uid"
                        class="border"
                    >
                        <CardContent class="grid gap-4 pt-5">
                            <!-- Numéro + label + supprimer -->
                            <div class="flex items-start gap-2">
                                <GripVertical
                                    class="drag-handle mt-2.5 h-4 w-4 shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing"
                                />
                                <span
                                    class="mt-2 w-5 shrink-0 text-center text-xs font-medium text-muted-foreground"
                                >
                                    {{ idx + 1 }}
                                </span>
                                <div class="flex-1 space-y-1.5">
                                    <Input
                                        v-model="form.sections[idx].label"
                                        :placeholder="
                                            $t(
                                                'types_projet.edit.section_title_placeholder',
                                            )
                                        "
                                        required
                                    />
                                    <InputError
                                        :message="
                                            form.errors[`sections.${idx}.label`]
                                        "
                                    />
                                    <Input
                                        v-model="form.sections[idx].description"
                                        :placeholder="
                                            $t(
                                                'types_projet.edit.section_instruction_placeholder',
                                            )
                                        "
                                    />
                                </div>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="mt-0.5 h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
                                    @click="supprimerSection(idx)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <!-- Sélecteur de type -->
                            <div class="ml-11">
                                <div class="mb-2 flex items-center gap-1">
                                    <p class="text-xs font-medium text-muted-foreground">
                                        {{ $t('types_projet.edit.input_mode_label') }}
                                    </p>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="modesInfoOuvert = true"
                                    >
                                        <Info class="h-3 w-3" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="sType in sectionTypes"
                                        :key="sType.value"
                                        type="button"
                                        :class="[
                                            'flex flex-col rounded-md border px-3 py-2.5 text-left text-xs transition-colors',
                                            form.sections[idx].type ===
                                            sType.value
                                                ? 'border-primary bg-primary/5 text-primary'
                                                : 'border-border bg-background text-muted-foreground hover:border-muted-foreground/40',
                                        ]"
                                        @click="
                                            form.sections[idx].type =
                                                sType.value
                                        "
                                    >
                                        <span class="font-medium">{{
                                            sType.label
                                        }}</span>
                                        <span
                                            class="mt-0.5 leading-tight text-muted-foreground/70"
                                        >
                                            {{ sType.description }}
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <!-- ─── Critères de la section (collapsible) ───────── -->
                            <div v-if="section.id" class="ml-11">
                                <Collapsible>
                                    <CollapsibleTrigger
                                        class="group flex w-full items-center justify-between rounded-md border border-dashed border-border px-3 py-2 text-xs font-medium transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary"
                                    >
                                        <span class="flex items-center gap-1.5">
                                            {{ $t('criteres.titre_section') }}
                                            <span
                                                v-if="
                                                    typeProjet.sections.find(
                                                        (s) =>
                                                            s.id === section.id,
                                                    )?.criteres?.length
                                                "
                                                class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] group-hover:bg-primary/10"
                                            >
                                                {{
                                                    typeProjet.sections.find(
                                                        (s) =>
                                                            s.id === section.id,
                                                    )?.criteres?.length
                                                }}
                                            </span>
                                            <span class="text-[10px] font-normal text-muted-foreground group-hover:text-primary/70">
                                                — {{ $t('criteres.trigger_hint') }}
                                            </span>
                                        </span>
                                        <ChevronDown
                                            class="h-3.5 w-3.5 text-muted-foreground transition-transform group-hover:text-primary [[data-state=open]_&]:rotate-180"
                                        />
                                    </CollapsibleTrigger>

                                    <CollapsibleContent class="mt-2 space-y-2">
                                        <!-- Critères existants (mode liste) -->
                                        <div
                                            v-if="
                                                typeProjet.sections.find((s) => s.id === section.id)?.criteres?.length &&
                                                vueModeCriteres === 'liste'
                                            "
                                            class="space-y-1"
                                        >
                                            <template
                                                v-for="critere in typeProjet.sections.find((s) => s.id === section.id)?.criteres"
                                                :key="critere.id"
                                            >
                                                <div class="flex items-start gap-2 rounded-md border px-3 py-2 text-sm">
                                                    <span
                                                        :class="[
                                                            'mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-xs font-medium',
                                                            critere.type === 'positif'
                                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                                : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
                                                        ]"
                                                    >
                                                        {{ critere.type === 'positif' ? '+' + critere.pointage : '-' + critere.pointage }}
                                                    </span>
                                                    <span class="min-w-0 flex-1 text-xs">
                                                        {{ critere.contenu }}
                                                        <span v-if="critere.contenu_type === 'echelle'" class="ml-1 text-muted-foreground">(échelle)</span>
                                                    </span>
                                                    <span v-if="!critere.visible" class="shrink-0 text-xs text-muted-foreground">masqué</span>
                                                    <div class="flex shrink-0 gap-1">
                                                        <button type="button" class="text-muted-foreground hover:text-foreground" @click="ouvrirFormEdition(critere.id)">
                                                            <span class="text-xs">{{ $t('criteres.btn_modifier') }}</span>
                                                        </button>
                                                        <button type="button" class="text-muted-foreground hover:text-destructive" @click="supprimerCritere(critere.id)">
                                                            <Trash2 class="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                </div>
                                                <CritereForm
                                                    v-if="critereEnEdition === critere.id"
                                                    :cours-id="cours.id"
                                                    :type-projet-id="typeProjet.id"
                                                    :section-id="section.id ?? null"
                                                    :critere="critere"
                                                    @saved="fermerForms"
                                                    @cancelled="fermerForms"
                                                />
                                            </template>
                                        </div>

                                        <!-- Critères existants (mode tableau) -->
                                        <CritereTable
                                            v-else-if="
                                                typeProjet.sections.find((s) => s.id === section.id)?.criteres?.length &&
                                                vueModeCriteres === 'tableau'
                                            "
                                            :criteres="typeProjet.sections.find((s) => s.id === section.id)?.criteres ?? []"
                                            :cours-id="cours.id"
                                            :type-projet-id="typeProjet.id"
                                            :section-id="section.id ?? null"
                                            :critere-en-edition="critereEnEdition"
                                            @edit="ouvrirFormEdition"
                                            @delete="supprimerCritere"
                                            @close="fermerForms"
                                        />

                                        <!-- Message vide -->
                                        <p
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ $t('criteres.aucun_critere') }}
                                        </p>

                                        <!-- Formulaire de création -->
                                        <CritereForm
                                            v-if="
                                                formOuvertePour === section.id
                                            "
                                            :cours-id="cours.id"
                                            :type-projet-id="typeProjet.id"
                                            :section-id="section.id ?? null"
                                            @saved="fermerForms"
                                            @cancelled="fermerForms"
                                        />

                                        <!-- Bouton ajouter -->
                                        <Button
                                            v-if="
                                                formOuvertePour !== section.id
                                            "
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="text-xs"
                                            @click="
                                                ouvrirFormCreation(section.id!)
                                            "
                                        >
                                            <Plus class="mr-1 h-3 w-3" />
                                            {{ $t('criteres.ajouter') }}
                                        </Button>
                                    </CollapsibleContent>
                                </Collapsible>
                            </div>
                        </CardContent>
                    </Card>
                </VueDraggable>

                <!-- Bouton ajouter une section -->
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="self-start"
                    @click="ajouterSection"
                >
                    <Plus class="mr-2 h-3.5 w-3.5" />
                    {{ $t('types_projet.edit.add_section') }}
                </Button>
            </div>

        </div>

        <!-- Barre fixe : total des critères + bouton Enregistrer -->
        <div class="sticky bottom-0 z-10 border-t bg-background">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-3">
                <p
                    :class="[
                        'text-sm font-medium tabular-nums',
                        totalPointsGlobal === 100
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : totalPointsGlobal > 100
                              ? 'text-destructive'
                              : 'text-muted-foreground',
                    ]"
                >
                    {{ $t('types_projet.edit.total_criteres') }} :
                    {{ totalPointsGlobal }} / 100 pts
                </p>

                <Button :disabled="form.processing" @click="sauvegarder">
                    {{
                        form.processing
                            ? $t('types_projet.edit.save_btn_saving')
                            : $t('types_projet.edit.save_btn')
                    }}
                </Button>
            </div>
        </div>
        <!-- ─── Dialog : aide sur les modes de saisie ────────────────────── -->
        <Dialog v-model:open="modesInfoOuvert">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('types_projet.edit.modes_info_title') }}</DialogTitle>
                    <DialogDescription>{{ $t('types_projet.edit.modes_info_subtitle') }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 pt-2 text-sm">
                    <div v-for="mode in sectionTypes" :key="mode.value" class="flex gap-3">
                        <span class="mt-0.5 shrink-0 rounded border px-2 py-0.5 text-xs font-medium text-muted-foreground">
                            {{ mode.label }}
                        </span>
                        <div>
                            <p class="font-medium">{{ mode.label }}</p>
                            <p class="text-muted-foreground">{{ mode.description }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground/70 italic">
                                {{ $t(`types_projet.edit.modes_info_exemple_${mode.value}`) }}
                            </p>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
