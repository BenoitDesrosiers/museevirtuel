<script setup lang="ts">
import InputError from '@/components/shared/InputError.vue';
import { Card, CardContent } from '@/components/ui/card';
import ProjectTypeOption from '@/components/type-projet/CreerProjetComponents/ProjectTypeOption.vue';

type ProjectType = 'standard' | 'musee';

type Form = {
    type: ProjectType;
};

defineProps<{
    form: Form;
    errors: Record<string, string | undefined>;
}>();
</script>

<template>
    <Card>
        <CardContent class="grid gap-4 pt-6">
            <h2 class="text-sm font-semibold">Type de projet</h2>

            <div class="grid grid-cols-2 gap-3">
                <ProjectTypeOption
                    value="standard"
                    label="Projet de recherche"
                    description="Rapport structuré par sections texte, paragraphes ou entrevues."
                    :selected="form.type === 'standard'"
                    @select="form.type = $event"
                />

                <ProjectTypeOption
                    value="musee"
                    label="Musée virtuel"
                    description="Site web pédagogique avec blocs riches, images et vidéos."
                    :selected="form.type === 'musee'"
                    @select="form.type = $event"
                />
            </div>

            <InputError :message="errors.type" />
        </CardContent>
    </Card>
</template>
