<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import InputError from '@/components/shared/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type Thematique = {
    id: number;
    nom: string;
    description: string | null;
    periode_historique: string | null;
};
const props = defineProps<{
    thematique?: Thematique;
    mode: 'create' | 'edit';
}>();
const form = useForm({
    nom: props.thematique?.nom ?? '',
    description: props.thematique?.description ?? '',
    periode_historique: props.thematique?.periode_historique ?? '',
});
function submit(): void {
    if (!form.nom.trim()) {
        form.setError('nom', 'Le nom est obligatoire.');

        return;
    }

    if (props.mode === 'create') {
        form.post('/thematiques');
    } else {
        form.put(`/thematiques/${props.thematique?.id}`);
    }
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child
                    ><a href="/enseignant" aria-label="Retour"
                        ><ArrowLeft class="h-4 w-4" /></a
                ></Button>
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{
                            mode === 'create'
                                ? $t('enseignant.index.modal_create_thematic')
                                : $t('enseignant.index.modal_edit_thematic')
                        }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ $t('enseignant.index.heading_description') }}
                    </p>
                </div>
            </div>
            <form
                class="rounded-xl border bg-card p-6"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="nom-theme">{{
                        $t('enseignant.index.modal_thematic_name')
                    }}</Label
                    ><Input
                        id="nom-theme"
                        v-model="form.nom"
                        :placeholder="
                            $t(
                                'enseignant.index.modal_thematic_name_placeholder',
                            )
                        "
                    /><InputError :message="form.errors.nom" />
                </div>
                <div class="grid gap-2">
                    <Label for="periode">{{
                        $t('enseignant.index.modal_historical_period')
                    }}</Label
                    ><Input
                        id="periode"
                        v-model="form.periode_historique"
                        :placeholder="
                            $t(
                                'enseignant.index.modal_historical_period_placeholder',
                            )
                        "
                    /><InputError :message="form.errors.periode_historique" />
                </div>
                <div class="grid gap-2">
                    <Label for="desc-theme">{{
                        $t('enseignant.index.modal_thematic_description')
                    }}</Label
                    ><Input
                        id="desc-theme"
                        v-model="form.description"
                        :placeholder="
                            $t(
                                'enseignant.index.modal_thematic_description_placeholder',
                            )
                        "
                    /><InputError :message="form.errors.description" />
                </div>
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" as-child
                        ><a href="/enseignant">Annuler</a></Button
                    ><Button type="submit" :disabled="form.processing">{{
                        mode === 'create' ? $t('common.add') : 'Enregistrer'
                    }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
