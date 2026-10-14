<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/shared/Heading.vue';
import { Button } from '@/components/ui/button';
import GeneralInformationCard from '@/components/type-projet/CreerProjetComponents/GeneralInformationCard.vue';
import ProjectTypeCard from '@/components/type-projet/CreerProjetComponents/ProjectTypeCard.vue';
import SubmissionSettingsCard from '@/components/type-projet/CreerProjetComponents/SubmissionSettingsCard.vue';
import SectionsList from '@/components/type-projet/CreerProjetComponents/SectionsList.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import typesProjets from '@/routes/types-projets';

const { t } = useI18n();

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
};

type Props = {
    cours: Cours;
};

const props = defineProps<Props>();

type SectionType = 'texte' | 'paragraphes' | 'individuel' | 'entrevue';

type SectionFormItem = {
    label: string;
    description: string;
    type: SectionType;
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

type TypeProjetType = 'standard' | 'musee';

/**
 * Retourne une échéance locale par défaut à demain pour le champ datetime-local.
 */
function defaultDateRemise(): string {
    const date = new Date();
    date.setDate(date.getDate() + 1);

    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const form = useForm({
    nom: '',
    type: 'standard' as TypeProjetType,
    description: '',
    date_remise: defaultDateRemise(),
    remises_multiples: false,
    retard_permis: false,
    generer_page_titre: true,
    generer_table_matieres: true,
    aide_reference: false,
    has_introduction: false,
    has_conclusion_individuelle: false,
    sections: [] as SectionFormItem[],
});

/**
 * Ajoute une nouvelle section vide à la fin de la liste.
 */
function ajouterSection() {
    form.sections.push({ label: '', description: '', type: 'texte' });
}

/**
 * Supprime la section à l'index donné.
 */
function supprimerSection(idx: number) {
    form.sections.splice(idx, 1);
}

/**
 * Réinitialise les champs non-applicables lors du changement de type.
 * Les options d'export et les sections n'ont aucun sens pour un musée virtuel.
 */
watch(
    () => form.type,
    (newType) => {
        if (newType === 'musee') {
            form.generer_page_titre = false;
            form.generer_table_matieres = false;
            form.aide_reference = false;
            form.sections = [];
        } else {
            form.generer_page_titre = true;
            form.generer_table_matieres = true;
        }
    },
);

/**
 * Soumet le formulaire de création via POST.
 */
function creer() {
    form.post(typesProjets.store.url(props.cours.id));
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('types_projet.create.page_title')" />

        <div class="mx-auto mt-2 flex max-w-2xl flex-col gap-6">
            <!-- En-tête -->
            <div>
                <Link
                    :href="typesProjets.index.url(cours.id)"
                    class="mb-2 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    {{ $t('types_projet.create.back') }}
                </Link>
                <Heading :title="$t('types_projet.create.heading_title')" />
            </div>

            <!-- Informations générales -->
            <GeneralInformationCard :form="form" :errors="form.errors" />

            <!-- Type de projet -->
            <ProjectTypeCard :form="form" :errors="form.errors" />

            <!-- Paramètres de remise -->
            <SubmissionSettingsCard :form="form" :errors="form.errors" />

            <!-- Liste des sections -->
            <SectionsList
                v-if="form.type === 'standard'"
                :form="form"
                :errors="form.errors"
                :section-types="sectionTypes"
                @add="ajouterSection"
                @remove="supprimerSection"
            />

            <!-- Bouton créer -->
            <div class="mb-2 flex justify-end">
                <Button :disabled="form.processing" @click="creer">
                    {{
                        form.processing
                            ? $t('types_projet.create.save_btn_saving')
                            : $t('types_projet.create.save_btn')
                    }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
