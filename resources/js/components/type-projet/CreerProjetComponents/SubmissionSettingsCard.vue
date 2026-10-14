<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InputError from '@/components/shared/InputError.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CheckboxSetting from '@/components/type-projet/CreerProjetComponents/CheckboxSetting.vue';

type Form = {
    type: 'standard' | 'musee';
    date_remise: string;
    remises_multiples: boolean;
    retard_permis: boolean;
    generer_page_titre: boolean;
    generer_table_matieres: boolean;
    aide_reference: boolean;
    has_introduction: boolean;
    has_conclusion_individuelle: boolean;
};

defineProps<{
    form: Form;
    errors: Record<string, string | undefined>;
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardContent class="grid gap-4 pt-6">
            <h2 class="text-sm font-semibold">
                {{ t('types_projet.edit.submission_section') }}
            </h2>

            <div class="grid gap-2">
                <Label for="date_remise">
                    {{ t('types_projet.edit.label_deadline') }}
                </Label>
                <Input
                    id="date_remise"
                    v-model="form.date_remise"
                    type="datetime-local"
                    required
                />
                <InputError :message="errors.date_remise" />
            </div>

            <CheckboxSetting
                id="remises_multiples"
                v-model:checked="form.remises_multiples"
                :label="t('types_projet.edit.label_multiple_submissions')"
                :hint="t('types_projet.edit.multiple_submissions_hint')"
                align="center"
            />

            <CheckboxSetting
                id="retard_permis"
                v-model:checked="form.retard_permis"
                :label="t('types_projet.edit.label_late_submission')"
                :hint="t('types_projet.edit.late_submission_hint')"
                align="center"
            />

            <template v-if="form.type === 'standard'">
                <h2 class="mt-2 text-sm font-semibold">
                    {{ t('types_projet.edit.export_options_title') }}
                </h2>

                <CheckboxSetting
                    id="generer_page_titre"
                    v-model:checked="form.generer_page_titre"
                    :label="t('types_projet.edit.label_generer_page_titre')"
                    :hint="t('types_projet.edit.hint_auto')"
                    hint-when-checked
                />

                <CheckboxSetting
                    id="generer_table_matieres"
                    v-model:checked="form.generer_table_matieres"
                    :label="t('types_projet.edit.label_generer_table_matieres')"
                    :hint="t('types_projet.edit.hint_auto')"
                    hint-when-checked
                />

                <CheckboxSetting
                    id="aide_reference"
                    v-model:checked="form.aide_reference"
                    :label="t('types_projet.edit.label_aide_reference')"
                    :hint="t('types_projet.edit.hint_aide_reference')"
                    hint-when-checked
                />

                <h2 class="mt-2 text-sm font-semibold">
                    {{ t('types_projet.edit.structure_title') }}
                </h2>

                <CheckboxSetting
                    id="has_introduction"
                    v-model:checked="form.has_introduction"
                    :label="t('types_projet.edit.label_has_introduction')"
                    :hint="t('types_projet.edit.hint_has_introduction')"
                />

                <CheckboxSetting
                    id="has_conclusion_individuelle"
                    v-model:checked="form.has_conclusion_individuelle"
                    :label="
                        t('types_projet.edit.label_has_conclusion_individuelle')
                    "
                    :hint="
                        t('types_projet.edit.hint_has_conclusion_individuelle')
                    "
                />
            </template>
        </CardContent>
    </Card>
</template>
