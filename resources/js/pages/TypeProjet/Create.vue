<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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

const form = useForm({
    nom: '',
    description: '',
    date_remise: '',
    remises_multiples: false,
    retard_permis: false,
    generer_page_titre: true,
    generer_table_matieres: true,
    aide_reference: false,
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
 * Soumet le formulaire de création via POST.
 */
function creer() {
    form.post(typesProjets.store.url(props.cours.id));
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('types_projet.create.page_title')" />

        <div class="mx-auto flex max-w-2xl flex-col gap-6 p-6">
            <!-- En-tête -->
            <div>
                <Link
                    :href="typesProjets.index.url(cours.id)"
                    class="mb-3 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    {{ $t('types_projet.create.back') }}
                </Link>
                <Heading :title="$t('types_projet.create.heading_title')" />
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
                            v-model="form.remises_multiples"
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
                            v-model="form.retard_permis"
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
                            v-model="form.generer_page_titre"
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
                            v-model="form.generer_table_matieres"
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
                            v-model="form.aide_reference"
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

            <!-- Sections -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold">
                            {{ $t('types_projet.edit.sections_title') }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('types_projet.edit.sections_hint') }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="ajouterSection"
                    >
                        <Plus class="mr-2 h-3.5 w-3.5" />
                        {{ $t('types_projet.edit.add_section') }}
                    </Button>
                </div>

                <!-- Message vide -->
                <Card v-if="form.sections.length === 0">
                    <CardContent
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('types_projet.edit.no_sections') }}
                    </CardContent>
                </Card>

                <!-- Liste des sections -->
                <Card
                    v-for="(section, idx) in form.sections"
                    :key="`new-${idx}`"
                    class="border"
                >
                    <CardContent class="grid gap-4 pt-5">
                        <!-- Numéro + label + supprimer -->
                        <div class="flex items-start gap-2">
                            <GripVertical
                                class="mt-2.5 h-4 w-4 shrink-0 text-muted-foreground/40"
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
                            <p
                                class="mb-2 text-xs font-medium text-muted-foreground"
                            >
                                {{ $t('types_projet.edit.input_mode_label') }}
                            </p>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="sType in sectionTypes"
                                    :key="sType.value"
                                    type="button"
                                    :class="[
                                        'flex flex-col rounded-md border px-3 py-2.5 text-left text-xs transition-colors',
                                        form.sections[idx].type === sType.value
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : 'border-border bg-background text-muted-foreground hover:border-muted-foreground/40',
                                    ]"
                                    @click="
                                        form.sections[idx].type = sType.value
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
                    </CardContent>
                </Card>
            </div>

            <!-- Bouton créer -->
            <div class="flex justify-end">
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
