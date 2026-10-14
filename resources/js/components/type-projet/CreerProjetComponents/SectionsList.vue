<script setup lang="ts">
import { GripVertical, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import InputError from '@/components/shared/InputError.vue';

type SectionType = 'texte' | 'paragraphes' | 'individuel' | 'entrevue';

type SectionFormItem = {
    label: string;
    description: string;
    type: SectionType;
};

type SectionTypeOption = {
    value: SectionType;
    label: string;
    description: string;
};

type Form = {
    sections: SectionFormItem[];
};

const props = defineProps<{
    form: Form;
    errors: Record<string, string | undefined>;
    sectionTypes: SectionTypeOption[];
}>();

const emit = defineEmits<{
    add: [];
    remove: [index: number];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold">
                    {{ t('types_projet.edit.sections_title') }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ t('types_projet.edit.sections_hint') }}
                </p>
            </div>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="emit('add')"
            >
                {{ t('types_projet.edit.add_section') }}
            </Button>
        </div>

        <Card v-if="props.form.sections.length === 0">
            <CardContent class="py-8 text-center text-sm text-muted-foreground">
                {{ t('types_projet.edit.no_sections') }}
            </CardContent>
        </Card>

        <Card
            v-for="(section, idx) in props.form.sections"
            :key="`new-${idx}`"
            class="border"
        >
            <CardContent class="grid gap-4 pt-5">
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
                            v-model="section.label"
                            :placeholder="
                                t('types_projet.edit.section_title_placeholder')
                            "
                            required
                        />
                        <InputError
                            :message="props.errors[`sections.${idx}.label`]"
                        />
                        <Input
                            v-model="section.description"
                            :placeholder="
                                t(
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
                        @click="emit('remove', idx)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <div class="ml-11">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">
                        {{ t('types_projet.edit.input_mode_label') }}
                    </p>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="sectionType in props.sectionTypes"
                            :key="sectionType.value"
                            type="button"
                            :class="[
                                'flex flex-col rounded-md border px-3 py-2.5 text-left text-xs transition-colors',
                                section.type === sectionType.value
                                    ? 'border-primary bg-primary/5 text-primary'
                                    : 'border-border bg-background text-muted-foreground hover:border-muted-foreground/40',
                            ]"
                            @click="section.type = sectionType.value"
                        >
                            <span class="font-medium">
                                {{ sectionType.label }}
                            </span>
                            <span
                                class="mt-0.5 leading-tight text-muted-foreground/70"
                            >
                                {{ sectionType.description }}
                            </span>
                        </button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
