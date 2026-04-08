<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle, Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { approuver } from '@/routes/administration/temoins';

type Enseignant = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    classes_count: number;
    thematiques_count: number;
};

type Stats = {
    total_enseignants: number;
    total_classes: number;
    total_etudiants: number;
};

type TemoinEnAttente = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    description: string;
    thematique_id: number | null;
    theme_libre: string | null;
    created_at: string;
    thematique: { id: number; nom: string } | null;
};

type Props = {
    enseignants: Enseignant[];
    stats: Stats;
    temoinsEnAttente: TemoinEnAttente[];
};

defineProps<Props>();
const { t } = useI18n();

// ─── Approuver un témoin ──────────────────────────────────────────────────────
const approuverForm = useForm({});

function approuverTemoin(temoin: TemoinEnAttente) {
    approuverForm.put(approuver.url(temoin.id));
}

// ─── Créer un enseignant ──────────────────────────────────────────────────────
const showCreateDialog = ref(false);
const createForm = useForm({
    prenom: '',
    nom: '',
    email: '',
});

function openCreate() {
    createForm.reset();
    showCreateDialog.value = true;
}

function submitCreate() {
    createForm.post('/administration/enseignants', {
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
        },
    });
}

// ─── Modifier un enseignant ───────────────────────────────────────────────────
const showEditDialog = ref(false);
const editingId = ref<number | null>(null);
const editForm = useForm({
    prenom: '',
    nom: '',
    email: '',
});

function openEdit(enseignant: Enseignant) {
    editingId.value = enseignant.id;
    editForm.prenom = enseignant.prenom;
    editForm.nom = enseignant.nom;
    editForm.email = enseignant.email;
    showEditDialog.value = true;
}

function submitEdit() {
    if (!editingId.value) {
return;
}

    editForm.put(`/administration/enseignants/${editingId.value}`, {
        onSuccess: () => {
            showEditDialog.value = false;
        },
    });
}

// ─── Supprimer un enseignant ──────────────────────────────────────────────────
const deleteForm = useForm({});

function deleteEnseignant(enseignant: Enseignant) {
    if (!confirm(t('administration.index.confirm_delete_teacher', { prenom: enseignant.prenom, nom: enseignant.nom }))) {
return;
}

    deleteForm.delete(`/administration/enseignants/${enseignant.id}`);
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('administration.index.page_title')" />

        <div class="flex flex-col gap-6 p-6">
            <Heading
                :title="$t('administration.index.heading_title')"
                :description="$t('administration.index.heading_description')"
            />

            <!-- Statistiques -->
            <div class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('administration.index.stats_teachers') }}</CardTitle>
                        <Users class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_enseignants }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('administration.index.stats_classes') }}</CardTitle>
                        <Users class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_classes }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('administration.index.stats_students') }}</CardTitle>
                        <Users class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_etudiants }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Liste des enseignants -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ $t('administration.index.teachers_table') }}</CardTitle>
                    <Button size="sm" @click="openCreate">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('administration.index.add_teacher') }}
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.table_header_first_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.table_header_last_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.table_header_email') }}</th>
                                    <th class="pb-3 pr-4 font-medium text-center">{{ $t('administration.index.table_header_classes') }}</th>
                                    <th class="pb-3 pr-4 font-medium text-center">{{ $t('administration.index.table_header_thematics') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('administration.index.table_header_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="enseignant in enseignants"
                                    :key="enseignant.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4">{{ enseignant.prenom }}</td>
                                    <td class="py-3 pr-4">{{ enseignant.nom }}</td>
                                    <td class="py-3 pr-4 text-muted-foreground">{{ enseignant.email }}</td>
                                    <td class="py-3 pr-4 text-center">{{ enseignant.classes_count }}</td>
                                    <td class="py-3 pr-4 text-center">{{ enseignant.thematiques_count }}</td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="openEdit(enseignant)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteEnseignant(enseignant)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="enseignants.length === 0">
                                    <td colspan="6" class="text-muted-foreground py-6 text-center">
                                        {{ $t('administration.index.no_teachers') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Demandes de témoins en attente -->
            <Card>
                <CardHeader>
                    <CardTitle>Demandes d'inscription — Témoins</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">Prénom</th>
                                    <th class="pb-3 pr-4 font-medium">Nom</th>
                                    <th class="pb-3 pr-4 font-medium">Courriel</th>
                                    <th class="pb-3 pr-4 font-medium">Thème</th>
                                    <th class="pb-3 pr-4 font-medium">Description</th>
                                    <th class="pb-3 font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="temoin in temoinsEnAttente"
                                    :key="temoin.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4">{{ temoin.prenom }}</td>
                                    <td class="py-3 pr-4">{{ temoin.nom }}</td>
                                    <td class="py-3 pr-4 text-muted-foreground">{{ temoin.email }}</td>
                                    <td class="py-3 pr-4">
                                        <span v-if="temoin.thematique">{{ temoin.thematique.nom }}</span>
                                        <span v-else-if="temoin.theme_libre" class="italic">{{ temoin.theme_libre }}</span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="max-w-xs py-3 pr-4">
                                        <p class="line-clamp-2 text-muted-foreground">{{ temoin.description }}</p>
                                    </td>
                                    <td class="py-3">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="text-green-600 hover:text-green-700"
                                            :disabled="approuverForm.processing"
                                            @click="approuverTemoin(temoin)"
                                        >
                                            <CheckCircle class="mr-1 h-4 w-4" />
                                            Approuver
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="temoinsEnAttente.length === 0">
                                    <td colspan="6" class="text-muted-foreground py-6 text-center">
                                        Aucune demande en attente.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Créer enseignant -->
        <FormDialog
            v-model:open="showCreateDialog"
            :title="$t('administration.index.modal_add_teacher')"
            :is-loading="createForm.processing"
            :submit-label="$t('common.add')"
            @submit="submitCreate"
        >
            <div class="grid gap-2">
                <Label for="create-prenom">{{ $t('administration.index.modal_first_name') }}</Label>
                <Input id="create-prenom" v-model="createForm.prenom" :placeholder="$t('administration.index.modal_first_name_placeholder')" />
                <InputError :message="createForm.errors.prenom" />
            </div>
            <div class="grid gap-2">
                <Label for="create-nom">{{ $t('administration.index.modal_last_name') }}</Label>
                <Input id="create-nom" v-model="createForm.nom" :placeholder="$t('administration.index.modal_last_name_placeholder')" />
                <InputError :message="createForm.errors.nom" />
            </div>
            <div class="grid gap-2">
                <Label for="create-email">{{ $t('administration.index.modal_email') }}</Label>
                <Input id="create-email" v-model="createForm.email" type="email" :placeholder="$t('administration.index.modal_email_placeholder')" />
                <InputError :message="createForm.errors.email" />
            </div>
        </FormDialog>

        <!-- Modal : Modifier enseignant -->
        <FormDialog
            v-model:open="showEditDialog"
            :title="$t('administration.index.modal_edit_teacher')"
            :is-loading="editForm.processing"
            @submit="submitEdit"
        >
            <div class="grid gap-2">
                <Label for="edit-prenom">{{ $t('administration.index.modal_first_name') }}</Label>
                <Input id="edit-prenom" v-model="editForm.prenom" :placeholder="$t('administration.index.modal_first_name_placeholder')" />
                <InputError :message="editForm.errors.prenom" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-nom">{{ $t('administration.index.modal_last_name') }}</Label>
                <Input id="edit-nom" v-model="editForm.nom" :placeholder="$t('administration.index.modal_last_name_placeholder')" />
                <InputError :message="editForm.errors.nom" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-email">{{ $t('administration.index.modal_email') }}</Label>
                <Input id="edit-email" v-model="editForm.email" type="email" :placeholder="$t('administration.index.modal_email_placeholder')" />
                <InputError :message="editForm.errors.email" />
            </div>
        </FormDialog>
    </AppLayout>
</template>
