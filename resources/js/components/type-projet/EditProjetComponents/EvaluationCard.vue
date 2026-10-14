<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InfoTooltip from '@/components/shared/InfoTooltip.vue';
import InputError from '@/components/shared/InputError.vue';
import CheckboxSetting from '@/components/type-projet/CreerProjetComponents/CheckboxSetting.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Form = {
    ponderation: number | null;
    is_sommatif: boolean;
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
            <h2 class="text-sm font-semibold">Évaluation</h2>

            <div class="grid gap-2">
                <div class="flex items-center gap-1">
                    <Label for="ponderation">
                        {{ t('types_projet.edit.label_ponderation') }}
                    </Label>
                    <InfoTooltip
                        :texte="t('types_projet.edit.tooltip_ponderation')"
                        content-class="max-w-72"
                    />
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
                    {{ t('types_projet.edit.hint_ponderation') }}
                </p>
                <InputError :message="errors.ponderation" />
            </div>

            <CheckboxSetting
                id="is_sommatif"
                v-model:checked="form.is_sommatif"
                label="Évaluation sommative"
                hint="Coché : ce projet contribue à la note finale. Décoché : formatif seulement."
                align="center"
            />
        </CardContent>
    </Card>
</template>
