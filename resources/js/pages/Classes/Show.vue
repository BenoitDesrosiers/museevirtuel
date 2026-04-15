<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type Membre = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
};

type Temoin = {
    id: number;
    prenom: string;
    nom: string;
} | null;

type Groupe = {
    id: number;
    numero: number;
    classe_id: number;
    created_by: number;
    membres: Membre[];
    thematiques: Thematique[];
    temoin: Temoin;
};

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
};

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Classe = {
    id: number;
    code: string;
    nom: string | null;
    cours_id: number;
    groupes: Groupe[];
    etudiants: Etudiant[];
};

type Props = {
    cours: Cours;
    classe: Classe;
    estEnseignant: boolean;
};

const props = defineProps<Props>();
const { t } = useI18n();

// ─── Supprimer un groupe ───────────────────────────────────────────────────────
const groupeASupprimer = ref<Groupe | null>(null);
const deleteGroupeForm = useForm({});

function confirmDeleteGroupe(groupe: Groupe) {
    groupeASupprimer.value = groupe;
}

function executeDeleteGroupe() {
    if (!groupeASupprimer.value) return;

    deleteGroupeForm.delete(
        `/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${groupeASupprimer.value.id}`,
        {
            onSuccess: () => {
                groupeASupprimer.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout>
        <Head :title="`${classe.code} — ${cours.nom_cours}`" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/cours/${cours.id}`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('classes.show.back_to_cours') }}
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="classe.nom ?? classe.code"
                :description="`${cours.code} — Groupe ${cours.groupe} · ${cours.nom_cours}`"
            />

            <!-- Groupes dans la section -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            {{ $t('classes.show.groups_title') }}
                            <span class="text-sm font-normal text-muted-foreground">
                                ({{ classe.groupes.length }})
                            </span>
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="classe.groupes.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('classes.show.no_groups') }}
                    </div>

                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="groupe in classe.groupes"
                            :key="groupe.id"
                            class="flex flex-col gap-3 rounded-lg border p-4"
                        >
                            <!-- En-tête du groupe -->
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ $t('classes.groupes.group_number', { n: groupe.numero }) }}
                                    </p>
                                    <p
                                        v-if="groupe.temoin"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Témoin : {{ groupe.temoin.prenom }} {{ groupe.temoin.nom }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <Button size="sm" variant="outline" as-child>
                                        <Link :href="`/cours/${cours.id}/classes/${classe.id}/groupes/${groupe.id}`">
                                            <ExternalLink class="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="estEnseignant"
                                        size="sm"
                                        variant="destructive"
                                        @click="confirmDeleteGroupe(groupe)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <!-- Membres -->
                            <div>
                                <p class="mb-1 text-xs font-medium text-muted-foreground">
                                    {{ $t('groupes.show.members') }}
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="membre in groupe.membres"
                                        :key="membre.id"
                                        class="rounded-full bg-muted px-2 py-0.5 text-xs"
                                    >
                                        {{ membre.prenom }} {{ membre.nom }}
                                    </span>
                                    <span
                                        v-if="groupe.membres.length === 0"
                                        class="text-xs text-muted-foreground"
                                    >
                                        —
                                    </span>
                                </div>
                            </div>

                            <!-- Thématiques -->
                            <div v-if="groupe.thematiques.length > 0">
                                <p class="mb-1 text-xs font-medium text-muted-foreground">
                                    {{ $t('groupes.show.thematic') }}
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="th in groupe.thematiques"
                                        :key="th.id"
                                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                    >
                                        {{ th.nom }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Étudiants inscrits -->
            <Card v-if="estEnseignant">
                <CardHeader>
                    <CardTitle>
                        {{ $t('classes.show.students') }}
                        <span class="ml-2 text-sm font-normal text-muted-foreground">
                            ({{ classe.etudiants.length }})
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="classe.etudiants.length === 0"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('classes.show.no_students') }}
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="pb-3 pr-4 font-medium">{{ $t('classes.show.table_header_first_name') }}</th>
                                <th class="pb-3 pr-4 font-medium">{{ $t('classes.show.table_header_name') }}</th>
                                <th class="pb-3 font-medium">{{ $t('classes.show.table_header_email') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="etudiant in classe.etudiants"
                                :key="etudiant.id"
                                class="border-b last:border-0"
                            >
                                <td class="py-2 pr-4">{{ etudiant.prenom }}</td>
                                <td class="py-2 pr-4 font-medium">{{ etudiant.nom }}</td>
                                <td class="py-2 text-muted-foreground">{{ etudiant.email }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Confirmer suppression groupe -->
        <ConfirmationModal
            :open="groupeASupprimer !== null"
            :title="$t('classes.groupes.confirm_delete_group', { numero: groupeASupprimer?.numero ?? '' })"
            :is-loading="deleteGroupeForm.processing"
            @update:open="(v) => { if (!v) groupeASupprimer = null; }"
            @confirm="executeDeleteGroupe"
        />
    </AppLayout>
</template>
