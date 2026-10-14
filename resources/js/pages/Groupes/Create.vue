<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/shared/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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

const form = useForm({
    membres: [] as number[],
    thematiques: [] as number[],
});

const membreSelectionne = ref<number | null>(null);
const thematiqueSelectionnee = ref<number | null>(null);

const membresSelectionnes = computed(() =>
    props.autresEtudiants.filter((etudiant) =>
        form.membres.includes(etudiant.id),
    ),
);

const thematiquesSelectionnees = computed(() =>
    props.thematiques.filter((thematique) =>
        form.thematiques.includes(thematique.id),
    ),
);

function ajouterMembre(): void {
    if (
        membreSelectionne.value !== null &&
        !form.membres.includes(membreSelectionne.value)
    ) {
        form.membres.push(membreSelectionne.value);
    }

    membreSelectionne.value = null;
}

function retirerMembre(id: number): void {
    form.membres = form.membres.filter((membreId) => membreId !== id);
}

function ajouterThematique(): void {
    if (
        thematiqueSelectionnee.value !== null &&
        form.thematiques.length < 3 &&
        !form.thematiques.includes(thematiqueSelectionnee.value)
    ) {
        form.thematiques.push(thematiqueSelectionnee.value);
    }

    thematiqueSelectionnee.value = null;
}

function retirerThematique(id: number): void {
    form.thematiques = form.thematiques.filter(
        (thematiqueId) => thematiqueId !== id,
    );
}

function submit(): void {
    form.post(`/cours/${props.cours.id}/classes/${props.classe.id}/groupes`);
}
</script>

<template>
    <AppLayout>
        <Head
            :title="`${$t('classes.groupes.heading')} — ${cours.nom_cours}`"
        />

        <div class="flex flex-col gap-6 p-6">
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/cours/${cours.id}/classes/${classe.id}`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('classes.groupes.back') }}
                    </Link>
                </Button>
            </div>

            <Heading
                :title="`${$t('classes.groupes.create_group')} — ${cours.nom_cours}`"
                :description="`${cours.code} — Groupe ${cours.groupe} · Section ${classe.code}`"
            />

            <form
                class="mx-auto flex w-full max-w-2xl flex-col gap-6"
                @submit.prevent="submit"
            >
                <div class="grid gap-3">
                    <Label for="membre-selection">
                        {{ $t('classes.groupes.modal_invite_members') }}
                    </Label>
                    <div class="flex items-center gap-2">
                        <Select v-model="membreSelectionne">
                            <SelectTrigger id="membre-selection" class="flex-1">
                                <SelectValue
                                    :placeholder="
                                        $t(
                                            'classes.groupes.modal_invite_members',
                                        )
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="etudiant in autresEtudiants.filter(
                                        (etudiant) =>
                                            !form.membres.includes(etudiant.id),
                                    )"
                                    :key="etudiant.id"
                                    :value="etudiant.id"
                                >
                                    {{ etudiant.prenom }} {{ etudiant.nom }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="membreSelectionne === null"
                            @click="ajouterMembre"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('common.add') }}
                        </Button>
                    </div>
                    <div
                        v-if="membresSelectionnes.length > 0"
                        class="flex flex-wrap gap-2"
                    >
                        <span
                            v-for="etudiant in membresSelectionnes"
                            :key="etudiant.id"
                            class="flex items-center gap-1 rounded-full bg-muted px-3 py-1 text-sm"
                        >
                            {{ etudiant.prenom }} {{ etudiant.nom }}
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                :aria-label="`Retirer ${etudiant.prenom} ${etudiant.nom}`"
                                @click="retirerMembre(etudiant.id)"
                            >
                                <X class="size-3" />
                            </button>
                        </span>
                    </div>
                    <p
                        v-else-if="autresEtudiants.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('classes.groupes.modal_no_other_students') }}
                    </p>
                    <p
                        v-if="form.errors.membres"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.membres }}
                    </p>
                </div>

                <div class="grid gap-3">
                    <Label for="thematique-selection">
                        {{ $t('classes.groupes.modal_thematic') }}
                        <span class="text-xs font-normal text-muted-foreground">
                            ({{ form.thematiques.length }}/3)
                        </span>
                    </Label>
                    <div class="flex items-center gap-2">
                        <Select v-model="thematiqueSelectionnee">
                            <SelectTrigger
                                id="thematique-selection"
                                class="flex-1"
                            >
                                <SelectValue
                                    :placeholder="
                                        $t('classes.groupes.modal_thematic')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="thematique in thematiques.filter(
                                        (thematique) =>
                                            !form.thematiques.includes(
                                                thematique.id,
                                            ),
                                    )"
                                    :key="thematique.id"
                                    :value="thematique.id"
                                >
                                    {{ thematique.nom }}
                                    <span
                                        v-if="thematique.periode_historique"
                                        class="text-xs text-muted-foreground"
                                    >
                                        — {{ thematique.periode_historique }}
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="
                                thematiqueSelectionnee === null ||
                                form.thematiques.length >= 3
                            "
                            @click="ajouterThematique"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('common.add') }}
                        </Button>
                    </div>
                    <div
                        v-if="thematiquesSelectionnees.length > 0"
                        class="flex flex-wrap gap-2"
                    >
                        <span
                            v-for="thematique in thematiquesSelectionnees"
                            :key="thematique.id"
                            class="flex items-center gap-1 rounded-full bg-muted px-3 py-1 text-sm"
                        >
                            {{ thematique.nom }}
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                :aria-label="`Retirer ${thematique.nom}`"
                                @click="retirerThematique(thematique.id)"
                            >
                                <X class="size-3" />
                            </button>
                        </span>
                    </div>
                    <p
                        v-if="form.errors.thematiques"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.thematiques }}
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" as-child>
                        <Link :href="`/cours/${cours.id}/classes/${classe.id}`">
                            {{ $t('common.cancel') }}
                        </Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ $t('classes.groupes.modal_create') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
