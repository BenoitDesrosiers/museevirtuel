<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, CheckCircle, Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { approuver } from '@/routes/administration/temoins';

type Enseignant = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    cours_count: number;
    thematiques_count: number;
    etablissement: { id: number; nom: string } | null;
};

type Etablissement = {
    id: number;
    nom: string;
    ville: string;
    code: string | null;
    enseignants_count: number;
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
    theme_libre: string | null;
    created_at: string;
    thematiques_choisies: { id: number; nom: string }[];
};

type Props = {
    enseignants: Enseignant[];
    etablissements: Etablissement[];
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
    etablissement_id: '' as string | number,
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
    etablissement_id: '' as string | number,
});

function openEdit(enseignant: Enseignant) {
    editingId.value = enseignant.id;
    editForm.prenom = enseignant.prenom;
    editForm.nom = enseignant.nom;
    editForm.email = enseignant.email;
    editForm.etablissement_id = enseignant.etablissement?.id ?? '';
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
const deleteEnseignantForm = useForm({});

function deleteEnseignant(enseignant: Enseignant) {
    if (!confirm(t('administration.index.confirm_delete_teacher', { prenom: enseignant.prenom, nom: enseignant.nom }))) {
        return;
    }

    deleteEnseignantForm.delete(`/administration/enseignants/${enseignant.id}`);
}

// ─── Créer un établissement ───────────────────────────────────────────────────
const showCreateEtablissementDialog = ref(false);
const createEtablissementForm = useForm({
    nom: '',
    ville: '',
    code: '',
});

function openCreateEtablissement() {
    createEtablissementForm.reset();
    showCreateEtablissementDialog.value = true;
}

function submitCreateEtablissement() {
    createEtablissementForm.post('/administration/etablissements', {
        onSuccess: () => {
            showCreateEtablissementDialog.value = false;
            createEtablissementForm.reset();
        },
    });
}

// ─── Modifier un établissement ────────────────────────────────────────────────
const showEditEtablissementDialog = ref(false);
const editingEtablissementId = ref<number | null>(null);
const editEtablissementForm = useForm({
    nom: '',
    ville: '',
    code: '',
});

function openEditEtablissement(etablissement: Etablissement) {
    editingEtablissementId.value = etablissement.id;
    editEtablissementForm.nom = etablissement.nom;
    editEtablissementForm.ville = etablissement.ville;
    editEtablissementForm.code = etablissement.code ?? '';
    showEditEtablissementDialog.value = true;
}

function submitEditEtablissement() {
    if (!editingEtablissementId.value) {
        return;
    }

    editEtablissementForm.put(`/administration/etablissements/${editingEtablissementId.value}`, {
        onSuccess: () => {
            showEditEtablissementDialog.value = false;
        },
    });
}

// ─── Supprimer un établissement ───────────────────────────────────────────────
const deleteEtablissementForm = useForm({});

function deleteEtablissement(etablissement: Etablissement) {
    if (!confirm(t('administration.index.confirm_delete_etablissement', { nom: etablissement.nom }))) {
        return;
    }

    deleteEtablissementForm.delete(`/administration/etablissements/${etablissement.id}`);
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

            <!-- Cégeps / Établissements -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        {{ $t('administration.index.etablissements_table') }}
                    </CardTitle>
                    <Button size="sm" @click="openCreateEtablissement">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('administration.index.add_etablissement') }}
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.etablissement_header_nom') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.etablissement_header_ville') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.etablissement_header_code') }}</th>
                                    <th class="pb-3 pr-4 text-center font-medium">{{ $t('administration.index.table_header_thematics') }}</th>
                                    <th class="pb-3 pr-4 text-center font-medium">{{ $t('administration.index.table_header_classes') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('administration.index.table_header_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="etablissement in etablissements"
                                    :key="etablissement.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4">
                                        <Link
                                            :href="`/administration/etablissements/${etablissement.id}`"
                                            class="font-medium underline-offset-4 hover:underline"
                                        >
                                            {{ etablissement.nom }}
                                        </Link>
                                    </td>
                                    <td class="text-muted-foreground py-3 pr-4">{{ etablissement.ville }}</td>
                                    <td class="text-muted-foreground py-3 pr-4">{{ etablissement.code ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-center">{{ etablissement.thematiques_count }}</td>
                                    <td class="py-3 pr-4 text-center">{{ etablissement.enseignants_count }}</td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="openEditEtablissement(etablissement)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteEtablissement(etablissement)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="etablissements.length === 0">
                                    <td colspan="6" class="text-muted-foreground py-6 text-center">
                                        {{ $t('administration.index.no_etablissements') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

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
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.etablissement_header_nom') }}</th>
                                    <th class="pb-3 pr-4 text-center font-medium">{{ $t('administration.index.table_header_classes') }}</th>
                                    <th class="pb-3 pr-4 text-center font-medium">{{ $t('administration.index.table_header_thematics') }}</th>
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
                                    <td class="text-muted-foreground py-3 pr-4">{{ enseignant.email }}</td>
                                    <td class="text-muted-foreground py-3 pr-4">{{ enseignant.etablissement?.nom ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-center">{{ enseignant.cours_count }}</td>
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
                                    <td colspan="7" class="text-muted-foreground py-6 text-center">
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
                    <CardTitle>{{ $t('administration.index.temoins_table') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_first_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_last_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_email') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_theme') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_description') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('administration.index.temoins_header_action') }}</th>
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
                                    <td class="text-muted-foreground py-3 pr-4">{{ temoin.email }}</td>
                                    <td class="py-3 pr-4">
                                        <span v-if="temoin.thematiques_choisies.length">{{ temoin.thematiques_choisies.map(t => t.nom).join(', ') }}</span>
                                        <span v-else-if="temoin.theme_libre" class="italic">{{ temoin.theme_libre }}</span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="max-w-xs py-3 pr-4">
                                        <p class="text-muted-foreground line-clamp-2">{{ temoin.description }}</p>
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
                                            {{ $t('administration.index.temoins_approve') }}
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="temoinsEnAttente.length === 0">
                                    <td colspan="6" class="text-muted-foreground py-6 text-center">
                                        {{ $t('administration.index.temoins_no_pending') }}
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
            <div class="grid gap-2">
                <Label>{{ $t('administration.index.modal_etablissement') }}</Label>
                <Select v-model="createForm.etablissement_id">
                    <SelectTrigger>
                        <SelectValue :placeholder="$t('administration.index.modal_etablissement_placeholder')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="etab in etablissements"
                            :key="etab.id"
                            :value="etab.id"
                        >
                            {{ etab.nom }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="createForm.errors.etablissement_id" />
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
            <div class="grid gap-2">
                <Label>{{ $t('administration.index.modal_etablissement') }}</Label>
                <Select v-model="editForm.etablissement_id">
                    <SelectTrigger>
                        <SelectValue :placeholder="$t('administration.index.modal_etablissement_placeholder')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="etab in etablissements"
                            :key="etab.id"
                            :value="etab.id"
                        >
                            {{ etab.nom }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="editForm.errors.etablissement_id" />
            </div>
        </FormDialog>

        <!-- Modal : Créer établissement -->
        <FormDialog
            v-model:open="showCreateEtablissementDialog"
            :title="$t('administration.index.modal_add_etablissement')"
            :is-loading="createEtablissementForm.processing"
            :submit-label="$t('common.add')"
            @submit="submitCreateEtablissement"
        >
            <div class="grid gap-2">
                <Label for="etab-nom">{{ $t('administration.index.etablissement_header_nom') }}</Label>
                <Input id="etab-nom" v-model="createEtablissementForm.nom" :placeholder="$t('administration.index.modal_etablissement_nom_placeholder')" />
                <InputError :message="createEtablissementForm.errors.nom" />
            </div>
            <div class="grid gap-2">
                <Label for="etab-ville">{{ $t('administration.index.etablissement_header_ville') }}</Label>
                <Input id="etab-ville" v-model="createEtablissementForm.ville" :placeholder="$t('administration.index.modal_etablissement_ville_placeholder')" />
                <InputError :message="createEtablissementForm.errors.ville" />
            </div>
            <div class="grid gap-2">
                <Label for="etab-code">{{ $t('administration.index.etablissement_header_code') }}</Label>
                <Input id="etab-code" v-model="createEtablissementForm.code" :placeholder="$t('administration.index.modal_etablissement_code_placeholder')" />
                <InputError :message="createEtablissementForm.errors.code" />
            </div>
        </FormDialog>

        <!-- Modal : Modifier établissement -->
        <FormDialog
            v-model:open="showEditEtablissementDialog"
            :title="$t('administration.index.modal_edit_etablissement')"
            :is-loading="editEtablissementForm.processing"
            @submit="submitEditEtablissement"
        >
            <div class="grid gap-2">
                <Label for="edit-etab-nom">{{ $t('administration.index.etablissement_header_nom') }}</Label>
                <Input id="edit-etab-nom" v-model="editEtablissementForm.nom" :placeholder="$t('administration.index.modal_etablissement_nom_placeholder')" />
                <InputError :message="editEtablissementForm.errors.nom" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-etab-ville">{{ $t('administration.index.etablissement_header_ville') }}</Label>
                <Input id="edit-etab-ville" v-model="editEtablissementForm.ville" :placeholder="$t('administration.index.modal_etablissement_ville_placeholder')" />
                <InputError :message="editEtablissementForm.errors.ville" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-etab-code">{{ $t('administration.index.etablissement_header_code') }}</Label>
                <Input id="edit-etab-code" v-model="editEtablissementForm.code" :placeholder="$t('administration.index.modal_etablissement_code_placeholder')" />
                <InputError :message="editEtablissementForm.errors.code" />
            </div>
        </FormDialog>
    </AppLayout>
</template>
