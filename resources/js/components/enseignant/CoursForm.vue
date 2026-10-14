<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/shared/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberInput } from '@/components/ui/number-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

type Cours = {
    id: number;
    nom_cours: string;
    description: string | null;
    code: string;
    groupe: string;
    annee: number;
    session: 'hiver' | 'ete' | 'automne';
    type_cours: 'dep' | 'cours_complementaire' | 'cours_complet' | null;
    taille_equipe_min: number | null;
    taille_equipe_max: number | null;
};

const props = defineProps<{ cours?: Cours; mode: 'create' | 'edit' }>();
const { t } = useI18n();

const form = useForm({
    nom_cours: props.cours?.nom_cours ?? '',
    description: props.cours?.description ?? '',
    code: props.cours?.code ?? '',
    groupe: props.cours?.groupe ?? '',
    annee: props.cours?.annee ?? new Date().getFullYear(),
    session: props.cours?.session ?? ('hiver' as 'hiver' | 'ete' | 'automne'),
    type_cours:
        props.cours?.type_cours ??
        ('' as '' | 'dep' | 'cours_complementaire' | 'cours_complet'),
    taille_equipe_min: props.cours?.taille_equipe_min ?? 1,
    taille_equipe_max: props.cours?.taille_equipe_max ?? null,
    utiliser_gabarit: false,
});

function validate(): boolean {
    form.clearErrors();
    let valid = true;

    if (!form.code.trim()) {
        form.setError('code', t('enseignant.index.error_course_code_required'));
        valid = false;
    }

    if (!form.groupe.trim()) {
        form.setError(
            'groupe',
            t('enseignant.index.error_course_group_required'),
        );
        valid = false;
    }

    if (!form.nom_cours.trim()) {
        form.setError(
            'nom_cours',
            t('enseignant.index.error_course_name_required'),
        );
        valid = false;
    }

    if (!form.type_cours && props.mode === 'create') {
        form.setError(
            'type_cours',
            t('enseignant.index.error_course_level_required'),
        );
        valid = false;
    }

    if (form.annee < 2000 || form.annee > 2100) {
        form.setError('annee', t('enseignant.index.error_course_year_invalid'));
        valid = false;
    }

    if (
        form.taille_equipe_max !== null &&
        form.taille_equipe_max < form.taille_equipe_min
    ) {
        form.setError(
            'taille_equipe_max',
            t('enseignant.index.error_course_team_size_invalid'),
        );
        valid = false;
    }

    return valid;
}

function submit(): void {
    if (!validate()) {
        return;
    }

    if (props.mode === 'create') {
        form.post('/cours');
    } else {
        form.put(`/cours/${props.cours?.id}`);
    }
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <a href="/enseignant" aria-label="Retour"
                        ><ArrowLeft class="h-4 w-4"
                    /></a>
                </Button>
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{
                            mode === 'create'
                                ? $t('enseignant.index.modal_create_cours')
                                : $t('enseignant.index.modal_edit_cours')
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
                <div class="grid grid-cols-2 items-start gap-4">
                    <div class="grid gap-2">
                        <Label>{{
                            $t('enseignant.index.modal_course_code')
                        }}</Label
                        ><Input
                            v-model="form.code"
                            :placeholder="
                                $t(
                                    'enseignant.index.modal_course_code_placeholder',
                                )
                            "
                        /><InputError :message="form.errors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('enseignant.index.modal_group') }}</Label
                        ><Input
                            v-model="form.groupe"
                            :placeholder="
                                $t('enseignant.index.modal_group_placeholder')
                            "
                        /><InputError :message="form.errors.groupe" />
                    </div>
                </div>
                <div class="grid grid-cols-2 items-start gap-4">
                    <div class="grid gap-2">
                        <Label>Session</Label
                        ><Select v-model="form.session"
                            ><SelectTrigger
                                ><SelectValue
                                    placeholder="Session…" /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="hiver">Hiver</SelectItem
                                ><SelectItem value="ete">Été</SelectItem
                                ><SelectItem value="automne"
                                    >Automne</SelectItem
                                ></SelectContent
                            ></Select
                        ><InputError :message="form.errors.session" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Année</Label
                        ><NumberInput
                            v-model="form.annee"
                            :min="2000"
                            :max="2100"
                            placeholder="2026"
                        /><InputError :message="form.errors.annee" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ $t('enseignant.index.modal_course_name') }}</Label
                    ><Input
                        v-model="form.nom_cours"
                        :placeholder="
                            $t('enseignant.index.modal_course_name_placeholder')
                        "
                    /><InputError :message="form.errors.nom_cours" />
                </div>
                <div class="grid gap-2">
                    <Label>{{ $t('enseignant.index.modal_description') }}</Label
                    ><Input
                        v-model="form.description"
                        :placeholder="
                            $t('enseignant.index.modal_description_placeholder')
                        "
                    /><InputError :message="form.errors.description" />
                </div>
                <div class="grid gap-2">
                    <Label>Niveau du cours</Label
                    ><Select v-model="form.type_cours"
                        ><SelectTrigger
                            ><SelectValue
                                placeholder="Choisir un niveau…" /></SelectTrigger
                        ><SelectContent
                            ><SelectItem value="dep">DEP</SelectItem
                            ><SelectItem value="cours_complementaire"
                                >Cours complémentaire</SelectItem
                            ><SelectItem value="cours_complet"
                                >Cours complet</SelectItem
                            ></SelectContent
                        ></Select
                    ><InputError :message="form.errors.type_cours" />
                </div>
                <div
                    v-if="
                        mode === 'create' && form.type_cours === 'cours_complet'
                    "
                    class="flex items-start gap-3 rounded-md border p-3"
                >
                    <input
                        v-model="form.utiliser_gabarit"
                        type="checkbox"
                        class="mt-1"
                    />
                    <div>
                        <Label>Créer avec le gabarit</Label>
                        <p class="text-xs text-muted-foreground">
                            Pré-remplit le cours avec les types de projets,
                            l'échéancier et les objectifs pédagogiques.
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-2 items-start gap-4">
                    <div class="grid gap-2">
                        <Label>Taille équipe min.</Label
                        ><NumberInput
                            v-model="form.taille_equipe_min"
                            :min="1"
                            :max="20"
                            placeholder="—"
                        /><InputError
                            :message="form.errors.taille_equipe_min"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Taille équipe max.</Label
                        ><NumberInput
                            v-model="form.taille_equipe_max"
                            :min="1"
                            :max="20"
                            placeholder="—"
                        /><InputError
                            :message="form.errors.taille_equipe_max"
                        />
                    </div>
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
