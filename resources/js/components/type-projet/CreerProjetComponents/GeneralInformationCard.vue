<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InputError from '@/components/shared/InputError.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Form = {
    nom: string;
    description: string;
};

defineProps<{
    form: Form;
    errors: Record<string, string | undefined>;
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardContent class="grid gap-1">
            <div class="grid gap-2">
                <Label for="nom">
                    {{ t('types_projet.edit.label_name') }}
                    <span class="text-destructive">*</span>
                </Label>
                <Input id="nom" v-model="form.nom" required />
                <InputError :message="errors.nom" />
            </div>

            <div class="grid gap-2">
                <Label for="description">
                    {{ t('types_projet.edit.label_description') }}
                </Label>
                <Textarea
                    id="description"
                    v-model="form.description"
                    :rows="2"
                />
                <InputError :message="errors.description" />
            </div>
        </CardContent>
    </Card>
</template>
