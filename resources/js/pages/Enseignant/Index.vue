<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BookOpen, Calendar, ChevronDown, ExternalLink, Lock, Pencil, Plus, Search, Send, Trash2, Unlock, Users, Video } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { show as showTemoin } from '@/routes/enseignant/temoins';

type Cours = {
    id: number;
    nom_cours: string;
    description: string | null;
    code: string;
    groupe: string;
    annee: number;
    session: 'hiver' | 'ete' | 'automne';
    is_verrouille: boolean;
    etudiants_count: number;
    type_cours: 'dep' | 'cours_complementaire' | 'cours_complet' | null;
    taille_equipe_min: number | null;
    taille_equipe_max: number | null;
};

type Thematique = {
    id: number;
    nom: string;
    description: string | null;
    periode_historique: string | null;
};

type TravailRemis = {
    id: number;
    titre_projet: string | null;
    remis_le: string;
    classe: {
        id: number;
        numero: number;
        cours_id: number;
    };
    membres: { id: number; prenom: string; nom: string }[];
};

type TemoinEnAttente = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    description: string;
    created_at: string;
    thematiques_choisies: { id: number; nom: string }[];
};

type TemoinApprouve = TemoinEnAttente;

type ProchaineVisio = {
    id: number;
    titre: string;
    scheduled_at: string;
    started_at: string | null;
    jitsi_room: string;
    cours: { id: number; nom_cours: string; code: string; groupe: string };
    groupe_numero: number | null;
    animateur: { prenom: string; nom: string };
};

type Props = {
    cours: Cours[];
    thematiques: Thematique[];
    travauxRemis: TravailRemis[];
    temoinsEnAttente: TemoinEnAttente[];
    temoinsApprouves: TemoinApprouve[];
    prochainesVisios: ProchaineVisio[];
};

const { t } = useI18n();

// ─── Sections repliables ──────────────────────────────────────────────────────
const ouvert = ref({
    visios: true,
    cours: true,
    thematiques: true,
    temoins: true,
    travaux: true,
});

// ─── Cours ────────────────────────────────────────────────────────────────────
const showCreateCoursDialog = ref(false);
const showEditCoursDialog = ref(false);
const editingCoursId = ref<number | null>(null);

const coursForm = useForm({
    nom_cours: '',
    description: '',
    code: '',
    groupe: '',
    annee: new Date().getFullYear() as number,
    session: 'hiver' as 'hiver' | 'ete' | 'automne',
    type_cours: '' as '' | 'dep' | 'cours_complementaire' | 'cours_complet',
    taille_equipe_min: null as number | null,
    taille_equipe_max: null as number | null,
    utiliser_gabarit: false,
});

function onTypeCoursCree(val: string) {
    coursForm.type_cours = val as typeof coursForm.type_cours;
    if (val !== 'cours_complet') {
        coursForm.utiliser_gabarit = false;
    }
}

function sessionLabel(session: string): string {
    const labels: Record<string, string> = { hiver: 'Hiver', ete: 'Été', automne: 'Automne' };
    return labels[session] ?? session;
}

function openCreateCours() {
    coursForm.reset();
    showCreateCoursDialog.value = true;
}

function submitCreateCours() {
    coursForm.post('/cours', {
        onSuccess: () => {
            showCreateCoursDialog.value = false;
            coursForm.reset();
        },
    });
}

function openEditCours(unCours: Cours) {
    editingCoursId.value = unCours.id;
    coursForm.nom_cours = unCours.nom_cours;
    coursForm.description = unCours.description ?? '';
    coursForm.code = unCours.code;
    coursForm.groupe = unCours.groupe;
    coursForm.annee = unCours.annee;
    coursForm.session = unCours.session;
    coursForm.type_cours = unCours.type_cours ?? '';
    coursForm.taille_equipe_min = unCours.taille_equipe_min;
    coursForm.taille_equipe_max = unCours.taille_equipe_max;
    showEditCoursDialog.value = true;
}

function submitEditCours() {
    if (!editingCoursId.value) {
return;
}

    coursForm.put(`/cours/${editingCoursId.value}`, {
        onSuccess: () => {
            showEditCoursDialog.value = false;
        },
    });
}

const deleteCoursForm = useForm({});

function deleteCours(unCours: Cours) {
    if (!confirm(t('enseignant.index.confirm_delete_course', { nom: unCours.nom_cours }))) {
return;
}

    deleteCoursForm.delete(`/cours/${unCours.id}`);
}

const verrouillageForm = useForm({});

function toggleVerrouillage(unCours: Cours) {
    verrouillageForm.patch(`/cours/${unCours.id}/verrouillage`);
}

// ─── Thématiques ──────────────────────────────────────────────────────────────
const showCreateThematiqueDialog = ref(false);
const showEditThematiqueDialog = ref(false);
const editingThematiqueId = ref<number | null>(null);

const thematiqueForm = useForm({
    nom: '',
    description: '',
    periode_historique: '',
});

function openCreateThematique() {
    thematiqueForm.reset();
    showCreateThematiqueDialog.value = true;
}

function submitCreateThematique() {
    thematiqueForm.post('/thematiques', {
        onSuccess: () => {
            showCreateThematiqueDialog.value = false;
            thematiqueForm.reset();
        },
    });
}

function openEditThematique(thematique: Thematique) {
    editingThematiqueId.value = thematique.id;
    thematiqueForm.nom = thematique.nom;
    thematiqueForm.description = thematique.description ?? '';
    thematiqueForm.periode_historique = thematique.periode_historique ?? '';
    showEditThematiqueDialog.value = true;
}

function submitEditThematique() {
    if (!editingThematiqueId.value) {
return;
}

    thematiqueForm.put(`/thematiques/${editingThematiqueId.value}`, {
        onSuccess: () => {
            showEditThematiqueDialog.value = false;
        },
    });
}

const deleteThematiqueForm = useForm({});

function deleteThematique(thematique: Thematique) {
    if (!confirm(t('enseignant.index.confirm_delete_thematic', { nom: thematique.nom }))) {
return;
}

    deleteThematiqueForm.delete(`/thematiques/${thematique.id}`);
}

// ─── Témoins ──────────────────────────────────────────────────────────────────
const afficherApprouves = ref(false);
const rechercheTexte = ref('');
const filtreThematiqueId = ref<number | null>(null);

const props = defineProps<Props>();

function filtrerTemoins(liste: TemoinEnAttente[]): TemoinEnAttente[] {
    const texte = rechercheTexte.value.trim().toLowerCase();
    return liste.filter((t) => {
        const matchTexte =
            texte === '' ||
            `${t.prenom} ${t.nom} ${t.email}`.toLowerCase().includes(texte);
        const matchThematique =
            filtreThematiqueId.value === null ||
            t.thematiques_choisies.some((th) => th.id === filtreThematiqueId.value);
        return matchTexte && matchThematique;
    });
}

const temoinsEnAttenteFiltrés = computed(() => filtrerTemoins(props.temoinsEnAttente));
const temoinsApprouvésFiltres = computed(() => filtrerTemoins(props.temoinsApprouves));

function voirTemoin(temoin: TemoinEnAttente) {
    router.visit(showTemoin.url(temoin.id));
}

function formatDateVisio(iso: string): string {
    return new Date(iso).toLocaleString('fr-CA', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function rejoindreVisio(jitsiRoom: string) {
    window.open(`https://meet.jit.si/${jitsiRoom}`, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('enseignant.index.page_title')" />

        <div class="flex flex-col gap-6 p-6">
            <Heading
                :title="$t('enseignant.index.heading_title')"
                :description="$t('enseignant.index.heading_description')"
            />

            <!-- ─── Mes cours ──────────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex flex-1 cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.cours = !ouvert.cours"
                    >
                        <CardTitle>{{ $t('enseignant.index.my_courses') }}</CardTitle>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.cours }"
                        />
                    </button>
                    <Button size="sm" @click="openCreateCours">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('enseignant.index.new_course') }}
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.cours">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_code') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_group') }}</th>
                                    <th class="pb-3 pr-4 font-medium">Session</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_course_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium text-center">{{ $t('enseignant.index.table_header_students') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('enseignant.index.table_header_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="unCours in cours"
                                    :key="unCours.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-mono text-xs">{{ unCours.code }}</td>
                                    <td class="py-3 pr-4 font-mono text-xs">{{ unCours.groupe }}</td>
                                    <td class="text-muted-foreground py-3 pr-4 text-xs">{{ sessionLabel(unCours.session) }} {{ unCours.annee }}</td>
                                    <td class="py-3 pr-4">{{ unCours.nom_cours }}</td>
                                    <td class="py-3 pr-4 text-center">{{ unCours.etudiants_count }}</td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <BoutonTooltip
                                                texte="Accéder au détail de ce cours"
                                                size="sm"
                                                variant="outline"
                                                as-child
                                            >
                                                <Link :href="`/cours/${unCours.id}`">
                                                    <ExternalLink class="h-4 w-4" />
                                                </Link>
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                texte="Modifier les informations de ce cours"
                                                size="sm"
                                                variant="outline"
                                                @click="openEditCours(unCours)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                :texte="unCours.is_verrouille ? 'Déverrouiller — rendre accessible aux étudiants' : 'Verrouiller — masquer aux étudiants'"
                                                size="sm"
                                                :variant="unCours.is_verrouille ? 'default' : 'outline'"
                                                @click="toggleVerrouillage(unCours)"
                                            >
                                                <Lock v-if="unCours.is_verrouille" class="h-4 w-4" />
                                                <Unlock v-else class="h-4 w-4" />
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                texte="Supprimer ce cours définitivement"
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteCours(unCours)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </BoutonTooltip>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="cours.length === 0">
                                    <td colspan="6" class="text-muted-foreground py-6 text-center">
                                        {{ $t('enseignant.index.no_courses') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Mes thématiques ────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex flex-1 cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.thematiques = !ouvert.thematiques"
                    >
                        <BookOpen class="h-5 w-5" />
                        <CardTitle>{{ $t('enseignant.index.my_thematic') }}</CardTitle>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.thematiques }"
                        />
                    </button>
                    <Button size="sm" @click="openCreateThematique">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('enseignant.index.new_thematic') }}
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.thematiques">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_thematic_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_historical_period') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_description') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('enseignant.index.table_header_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="thematique in thematiques"
                                    :key="thematique.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">{{ thematique.nom }}</td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{ thematique.periode_historique ?? '—' }}
                                    </td>
                                    <td class="text-muted-foreground max-w-xs truncate py-3 pr-4">
                                        {{ thematique.description ?? '—' }}
                                    </td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <BoutonTooltip
                                                texte="Modifier cette thématique"
                                                size="sm"
                                                variant="outline"
                                                @click="openEditThematique(thematique)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                texte="Supprimer cette thématique"
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteThematique(thematique)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </BoutonTooltip>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="thematiques.length === 0">
                                    <td colspan="4" class="text-muted-foreground py-6 text-center">
                                        {{ $t('enseignant.index.no_thematic') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <!-- ─── Témoins ───────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex flex-1 cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.temoins = !ouvert.temoins"
                    >
                        <Users class="h-5 w-5" />
                        <CardTitle>{{ $t('administration.index.temoins_table') }}</CardTitle>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.temoins }"
                        />
                    </button>
                    <!-- Toggle afficher approuvés -->
                    <label class="flex cursor-pointer items-center gap-2 text-sm" @click.stop>
                        <span class="text-muted-foreground">{{ $t('enseignant.index.temoins_show_approved') }}</span>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="afficherApprouves"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            :class="afficherApprouves ? 'bg-primary' : 'bg-input'"
                            @click.stop="afficherApprouves = !afficherApprouves"
                        >
                            <span
                                class="inline-block h-3 w-3 rounded-full bg-white shadow-sm transition-transform"
                                :class="afficherApprouves ? 'translate-x-5' : 'translate-x-1'"
                            />
                        </button>
                    </label>
                </CardHeader>
                <CardContent v-show="ouvert.temoins">
                    <!-- Barre de filtre -->
                    <div class="mb-4 flex flex-wrap gap-3">
                        <div class="relative flex-1 min-w-48">
                            <Search class="text-muted-foreground absolute left-2.5 top-2.5 h-4 w-4" />
                            <Input
                                v-model="rechercheTexte"
                                class="pl-8"
                                :placeholder="$t('enseignant.index.temoins_filter_search_placeholder')"
                            />
                        </div>
                        <select
                            v-model="filtreThematiqueId"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring h-10 rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                        >
                            <option :value="null">{{ $t('enseignant.index.temoins_filter_all_thematiques') }}</option>
                            <option v-for="th in props.thematiques" :key="th.id" :value="th.id">
                                {{ th.nom }}
                            </option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <!-- Tableau : témoins en attente -->
                        <table v-if="!afficherApprouves" class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_first_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_last_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_email') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('administration.index.temoins_header_theme') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="temoin in temoinsEnAttenteFiltrés"
                                    :key="temoin.id"
                                    class="hover:bg-muted/50 cursor-pointer border-b last:border-0"
                                    @click="voirTemoin(temoin)"
                                >
                                    <td class="py-3 pr-4">{{ temoin.prenom }}</td>
                                    <td class="py-3 pr-4">{{ temoin.nom }}</td>
                                    <td class="text-muted-foreground py-3 pr-4">{{ temoin.email }}</td>
                                    <td class="py-3">
                                        <span v-if="temoin.thematiques_choisies.length">
                                            {{ temoin.thematiques_choisies.map(th => th.nom).join(', ') }}
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                </tr>
                                <tr v-if="temoinsEnAttenteFiltrés.length === 0">
                                    <td colspan="4" class="text-muted-foreground py-6 text-center">
                                        {{ $t('administration.index.temoins_no_pending') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Tableau : témoins approuvés -->
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_first_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_last_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.temoins_header_email') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('administration.index.temoins_header_theme') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="temoin in temoinsApprouvésFiltres"
                                    :key="temoin.id"
                                    class="hover:bg-muted/50 cursor-pointer border-b last:border-0"
                                    @click="voirTemoin(temoin)"
                                >
                                    <td class="py-3 pr-4">{{ temoin.prenom }}</td>
                                    <td class="py-3 pr-4">{{ temoin.nom }}</td>
                                    <td class="text-muted-foreground py-3 pr-4">{{ temoin.email }}</td>
                                    <td class="py-3">
                                        <span v-if="temoin.thematiques_choisies.length">
                                            {{ temoin.thematiques_choisies.map(th => th.nom).join(', ') }}
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                </tr>
                                <tr v-if="temoinsApprouvésFiltres.length === 0">
                                    <td colspan="4" class="text-muted-foreground py-6 text-center">
                                        {{ $t('enseignant.index.temoins_no_approved') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Travaux remis récemment ────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.travaux = !ouvert.travaux"
                    >
                        <Send class="h-5 w-5" />
                        <CardTitle>{{ $t('enseignant.index.recent_submissions') }}</CardTitle>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.travaux }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.travaux">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_group_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_project_title') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_members') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_submitted_at') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('enseignant.index.table_header_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="travail in travauxRemis"
                                    :key="travail.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">{{ $t('classes.groupes.group_number', { n: travail.classe.numero }) }}</td>
                                    <td class="text-muted-foreground py-3 pr-4">
                                        {{ travail.titre_projet ?? '—' }}
                                    </td>
                                    <td class="text-muted-foreground py-3 pr-4">
                                        {{ travail.membres.map(m => `${m.prenom} ${m.nom}`).join(', ') }}
                                    </td>
                                    <td class="py-3 pr-4 tabular-nums">
                                        {{ new Date(travail.remis_le).toLocaleDateString() }}
                                    </td>
                                    <td class="py-3">
                                        <BoutonTooltip
                                            texte="Accéder aux projets de ce groupe"
                                            size="sm"
                                            variant="outline"
                                            as-child
                                        >
                                            <Link :href="`/cours/${travail.classe.cours_id}/classes/${travail.classe.id}/projets`">
                                                <ExternalLink class="h-4 w-4" />
                                                {{ $t('enseignant.index.view_project') }}
                                            </Link>
                                        </BoutonTooltip>
                                    </td>
                                </tr>
                                <tr v-if="travauxRemis.length === 0">
                                    <td colspan="5" class="text-muted-foreground py-6 text-center">
                                        {{ $t('enseignant.index.no_submissions') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Prochaines visioconférences ───────────────────────────── -->
            <Card v-if="prochainesVisios.length > 0">
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-left select-none"
                        @click="ouvert.visios = !ouvert.visios"
                    >
                        <Video class="h-5 w-5" />
                        <CardTitle>Prochaines visioconférences</CardTitle>
                        <ChevronDown
                            class="text-muted-foreground h-4 w-4 transition-transform"
                            :class="{ '-rotate-180': ouvert.visios }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.visios">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">Titre</th>
                                    <th class="pb-3 pr-4 font-medium">Cours</th>
                                    <th class="pb-3 pr-4 font-medium">Groupe</th>
                                    <th class="pb-3 pr-4 font-medium">Date</th>
                                    <th class="pb-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="visio in prochainesVisios"
                                    :key="visio.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">{{ visio.titre }}</td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        <span class="font-mono text-xs">{{ visio.cours.code }}-{{ visio.cours.groupe }}</span>
                                        <span class="ml-1">{{ visio.cours.nom_cours }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{ visio.groupe_numero ? `Groupe ${visio.groupe_numero}` : 'Tous les groupes' }}
                                    </td>
                                    <td class="py-3 pr-4 tabular-nums">
                                        <span class="flex items-center gap-1">
                                            <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                                            {{ formatDateVisio(visio.scheduled_at) }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <Button
                                            v-if="visio.started_at"
                                            size="sm"
                                            @click="rejoindreVisio(visio.jitsi_room)"
                                        >
                                            <Video class="mr-1.5 h-4 w-4" />
                                            Rejoindre
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Créer cours -->
        <FormDialog
            v-model:open="showCreateCoursDialog"
            :title="$t('enseignant.index.modal_create_cours')"
            :is-loading="coursForm.processing"
            :submit-label="$t('common.add')"
            @submit="submitCreateCours"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="code">{{ $t('enseignant.index.modal_course_code') }}</Label>
                    <Input id="code" v-model="coursForm.code" :placeholder="$t('enseignant.index.modal_course_code_placeholder')" />
                    <InputError :message="coursForm.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="groupe">{{ $t('enseignant.index.modal_group') }}</Label>
                    <Input id="groupe" v-model="coursForm.groupe" :placeholder="$t('enseignant.index.modal_group_placeholder')" />
                    <InputError :message="coursForm.errors.groupe" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label>Session</Label>
                    <Select v-model="coursForm.session">
                        <SelectTrigger>
                            <SelectValue placeholder="Session…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="hiver">Hiver</SelectItem>
                            <SelectItem value="ete">Été</SelectItem>
                            <SelectItem value="automne">Automne</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="coursForm.errors.session" />
                </div>
                <div class="grid gap-2">
                    <Label>Année</Label>
                    <Input v-model.number="coursForm.annee" type="number" min="2000" max="2100" placeholder="2026" />
                    <InputError :message="coursForm.errors.annee" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="nom_cours">{{ $t('enseignant.index.modal_course_name') }}</Label>
                <Input id="nom_cours" v-model="coursForm.nom_cours" :placeholder="$t('enseignant.index.modal_course_name_placeholder')" />
                <InputError :message="coursForm.errors.nom_cours" />
            </div>
            <div class="grid gap-2">
                <Label for="description">{{ $t('enseignant.index.modal_description') }}</Label>
                <Input id="description" v-model="coursForm.description" :placeholder="$t('enseignant.index.modal_description_placeholder')" />
                <InputError :message="coursForm.errors.description" />
            </div>
            <div class="grid gap-2">
                <Label>Niveau du cours</Label>
                <Select :model-value="coursForm.type_cours" @update:model-value="onTypeCoursCree">
                    <SelectTrigger>
                        <SelectValue placeholder="Choisir un niveau…" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="dep">DEP</SelectItem>
                        <SelectItem value="cours_complementaire">Cours complémentaire</SelectItem>
                        <SelectItem value="cours_complet">Cours complet</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="coursForm.errors.type_cours" />
            </div>

            <!-- Gabarit — affiché uniquement pour cours complet -->
            <div
                v-if="coursForm.type_cours === 'cours_complet'"
                class="cursor-pointer rounded-md border p-3"
                :class="coursForm.utiliser_gabarit ? 'border-primary bg-primary/5' : 'border-border'"
                @click="coursForm.utiliser_gabarit = !coursForm.utiliser_gabarit"
            >
                <div class="flex items-start gap-3">
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="coursForm.utiliser_gabarit"
                        class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        :class="coursForm.utiliser_gabarit ? 'bg-primary' : 'bg-input'"
                        @click.stop="coursForm.utiliser_gabarit = !coursForm.utiliser_gabarit"
                    >
                        <span
                            class="inline-block h-3 w-3 rounded-full bg-white shadow-sm transition-transform"
                            :class="coursForm.utiliser_gabarit ? 'translate-x-5' : 'translate-x-1'"
                        />
                    </button>
                    <div class="grid gap-0.5">
                        <span class="text-sm font-medium leading-none">Créer avec le gabarit</span>
                        <span class="text-muted-foreground text-xs">
                            Pré-remplit le cours avec les types de projets (plan de travail, schéma d'entrevue, projet de recherche), l'échéancier sur 13 semaines et les objectifs pédagogiques.
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label>Taille équipe min.</Label>
                    <Input v-model.number="coursForm.taille_equipe_min" type="number" min="1" max="20" placeholder="—" />
                    <InputError :message="coursForm.errors.taille_equipe_min" />
                </div>
                <div class="grid gap-2">
                    <Label>Taille équipe max.</Label>
                    <Input v-model.number="coursForm.taille_equipe_max" type="number" min="1" max="20" placeholder="—" />
                    <InputError :message="coursForm.errors.taille_equipe_max" />
                </div>
            </div>
        </FormDialog>

        <!-- Modal : Modifier cours -->
        <FormDialog
            v-model:open="showEditCoursDialog"
            :title="$t('enseignant.index.modal_edit_cours')"
            :is-loading="coursForm.processing"
            @submit="submitEditCours"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label>{{ $t('enseignant.index.modal_course_code') }}</Label>
                    <Input v-model="coursForm.code" :placeholder="$t('enseignant.index.modal_course_code_placeholder')" />
                    <InputError :message="coursForm.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label>{{ $t('enseignant.index.modal_group') }}</Label>
                    <Input v-model="coursForm.groupe" :placeholder="$t('enseignant.index.modal_group_placeholder')" />
                    <InputError :message="coursForm.errors.groupe" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label>Session</Label>
                    <Select v-model="coursForm.session">
                        <SelectTrigger>
                            <SelectValue placeholder="Session…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="hiver">Hiver</SelectItem>
                            <SelectItem value="ete">Été</SelectItem>
                            <SelectItem value="automne">Automne</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="coursForm.errors.session" />
                </div>
                <div class="grid gap-2">
                    <Label>Année</Label>
                    <Input v-model.number="coursForm.annee" type="number" min="2000" max="2100" placeholder="2026" />
                    <InputError :message="coursForm.errors.annee" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_course_name') }}</Label>
                <Input v-model="coursForm.nom_cours" :placeholder="$t('enseignant.index.modal_course_name_placeholder')" />
                <InputError :message="coursForm.errors.nom_cours" />
            </div>
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_description') }}</Label>
                <Input v-model="coursForm.description" :placeholder="$t('enseignant.index.modal_description_placeholder')" />
                <InputError :message="coursForm.errors.description" />
            </div>
            <div class="grid gap-2">
                <Label>Niveau du cours</Label>
                <Select v-model="coursForm.type_cours">
                    <SelectTrigger>
                        <SelectValue placeholder="Choisir un niveau…" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="dep">DEP</SelectItem>
                        <SelectItem value="cours_complementaire">Cours complémentaire</SelectItem>
                        <SelectItem value="cours_complet">Cours complet</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="coursForm.errors.type_cours" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label>Taille équipe min.</Label>
                    <Input v-model.number="coursForm.taille_equipe_min" type="number" min="1" max="20" placeholder="—" />
                    <InputError :message="coursForm.errors.taille_equipe_min" />
                </div>
                <div class="grid gap-2">
                    <Label>Taille équipe max.</Label>
                    <Input v-model.number="coursForm.taille_equipe_max" type="number" min="1" max="20" placeholder="—" />
                    <InputError :message="coursForm.errors.taille_equipe_max" />
                </div>
            </div>
        </FormDialog>

        <!-- Modal : Créer thématique -->
        <FormDialog
            v-model:open="showCreateThematiqueDialog"
            :title="$t('enseignant.index.modal_create_thematic')"
            :is-loading="thematiqueForm.processing"
            :submit-label="$t('common.add')"
            @submit="submitCreateThematique"
        >
            <div class="grid gap-2">
                <Label for="nom-theme">{{ $t('enseignant.index.modal_thematic_name') }}</Label>
                <Input id="nom-theme" v-model="thematiqueForm.nom" :placeholder="$t('enseignant.index.modal_thematic_name_placeholder')" />
                <InputError :message="thematiqueForm.errors.nom" />
            </div>
            <div class="grid gap-2">
                <Label for="periode">{{ $t('enseignant.index.modal_historical_period') }}</Label>
                <Input id="periode" v-model="thematiqueForm.periode_historique" :placeholder="$t('enseignant.index.modal_historical_period_placeholder')" />
                <InputError :message="thematiqueForm.errors.periode_historique" />
            </div>
            <div class="grid gap-2">
                <Label for="desc-theme">{{ $t('enseignant.index.modal_thematic_description') }}</Label>
                <Input id="desc-theme" v-model="thematiqueForm.description" :placeholder="$t('enseignant.index.modal_thematic_description_placeholder')" />
                <InputError :message="thematiqueForm.errors.description" />
            </div>
        </FormDialog>

        <!-- Modal : Modifier thématique -->
        <FormDialog
            v-model:open="showEditThematiqueDialog"
            :title="$t('enseignant.index.modal_edit_thematic')"
            :is-loading="thematiqueForm.processing"
            @submit="submitEditThematique"
        >
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_thematic_name') }}</Label>
                <Input v-model="thematiqueForm.nom" :placeholder="$t('enseignant.index.modal_thematic_name_placeholder')" />
                <InputError :message="thematiqueForm.errors.nom" />
            </div>
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_historical_period') }}</Label>
                <Input v-model="thematiqueForm.periode_historique" :placeholder="$t('enseignant.index.modal_historical_period_placeholder')" />
                <InputError :message="thematiqueForm.errors.periode_historique" />
            </div>
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_thematic_description') }}</Label>
                <Input v-model="thematiqueForm.description" :placeholder="$t('enseignant.index.modal_thematic_description_placeholder')" />
                <InputError :message="thematiqueForm.errors.description" />
            </div>
        </FormDialog>
    </AppLayout>
</template>
