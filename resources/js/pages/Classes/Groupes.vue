<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Classe = {
    id: number;
    code: string;
    cours_id: number;
};

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
    periode_historique: string | null;
};

type Props = {
    cours: Cours;
    classe: Classe;
    autresEtudiants: Etudiant[];
    thematiques: Thematique[];
};

const props = defineProps<Props>();

// ─── Créer un groupe ──────────────────────────────────────────────────────────
const showCreateDialog = ref(false);
const form = useForm({
    membres: [] as number[],
    thematiques: [] as number[],
});

// Refs découplés de useForm pour tracker les cases à cocher de façon fiable
const membresSelectionnes = ref<number[]>([]);
const thematiquesSelectionnees = ref<number[]>([]);

function openCreate() {
    form.reset();
    membresSelectionnes.value = [];
    thematiquesSelectionnees.value = [];
    showCreateDialog.value = true;
}

function toggleMembre(id: number) {
    const idx = membresSelectionnes.value.indexOf(id);

    if (idx > -1) {
        membresSelectionnes.value.splice(idx, 1);
    } else {
        membresSelectionnes.value.push(id);
    }
}

const thematiquesMax = computed(
    () => thematiquesSelectionnees.value.length >= 3,
);

function toggleThematique(id: number) {
    const idx = thematiquesSelectionnees.value.indexOf(id);

    if (idx > -1) {
        thematiquesSelectionnees.value.splice(idx, 1);
    } else if (thematiquesSelectionnees.value.length < 3) {
        thematiquesSelectionnees.value.push(id);
    }
}

function submitCreate() {
    form.transform((data) => ({
        ...data,
        membres: membresSelectionnes.value,
        thematiques: thematiquesSelectionnees.value,
    })).post(`/cours/${props.cours.id}/classes/${props.classe.id}/groupes`, {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
            membresSelectionnes.value = [];
            thematiquesSelectionnees.value = [];
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head
            :title="`${$t('classes.groupes.heading')} — ${cours.nom_cours}`"
        />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/cours/${cours.id}/classes`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('classes.groupes.back') }}
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="`${$t('classes.groupes.heading')} — ${cours.nom_cours}`"
                :description="`${cours.code} — Groupe ${cours.groupe} · Section ${classe.code}`"
            />

            <!-- Pas encore de groupe -->
            <div class="flex flex-col items-center gap-4 py-12">
                <p class="text-center text-muted-foreground">
                    {{ $t('classes.groupes.no_group') }}
                </p>
                <Button @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ $t('classes.groupes.create_group') }}
                </Button>
            </div>
        </div>

        <!-- Dialog création de groupe -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{
                        $t('classes.groupes.modal_create_group')
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submitCreate">
                    <!-- Membres -->
                    <div v-if="autresEtudiants.length > 0" class="grid gap-2">
                        <Label>{{
                            $t('classes.groupes.modal_invite_members')
                        }}</Label>
                        <div class="space-y-2">
                            <div
                                v-for="etudiant in autresEtudiants"
                                :key="etudiant.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`membre-${etudiant.id}`"
                                    :checked="
                                        membresSelectionnes.includes(
                                            etudiant.id,
                                        )
                                    "
                                    @click.prevent="
                                        () => toggleMembre(etudiant.id)
                                    "
                                />
                                <Label
                                    :for="`membre-${etudiant.id}`"
                                    class="cursor-pointer font-normal"
                                >
                                    {{ etudiant.prenom }} {{ etudiant.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('classes.groupes.modal_no_other_students') }}
                    </p>

                    <!-- Thématiques -->
                    <div v-if="thematiques.length > 0" class="grid gap-2">
                        <Label>
                            {{ $t('classes.groupes.modal_thematic') }}
                            <span
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ $t('classes.groupes.modal_thematic_max') }}
                                {{ thematiquesSelectionnees.length }}/3
                                sélectionnée{{
                                    thematiquesSelectionnees.length > 1
                                        ? 's'
                                        : ''
                                }})
                            </span>
                        </Label>
                        <div class="space-y-2">
                            <div
                                v-for="thematique in thematiques"
                                :key="thematique.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`thematique-${thematique.id}`"
                                    :checked="
                                        thematiquesSelectionnees.includes(
                                            thematique.id,
                                        )
                                    "
                                    :disabled="
                                        thematiquesMax &&
                                        !thematiquesSelectionnees.includes(
                                            thematique.id,
                                        )
                                    "
                                    @click.prevent="
                                        () => toggleThematique(thematique.id)
                                    "
                                />
                                <Label
                                    :for="`thematique-${thematique.id}`"
                                    class="cursor-pointer font-normal"
                                    :class="{
                                        'text-muted-foreground':
                                            thematiquesMax &&
                                            !thematiquesSelectionnees.includes(
                                                thematique.id,
                                            ),
                                    }"
                                >
                                    {{ thematique.nom }}
                                    <span
                                        v-if="thematique.periode_historique"
                                        class="text-xs text-muted-foreground"
                                    >
                                        — {{ thematique.periode_historique }}
                                    </span>
                                </Label>
                            </div>
                        </div>
                        <p
                            v-if="form.errors.thematiques"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.thematiques }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateDialog = false"
                        >
                            {{ $t('common.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ $t('classes.groupes.modal_create') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
