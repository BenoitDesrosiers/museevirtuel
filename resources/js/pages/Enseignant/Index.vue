<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    BookOpen,
    Calendar,
    ChevronDown,
    ExternalLink,
    Lock,
    Pencil,
    Plus,
    Search,
    Send,
    Trash2,
    Unlock,
    Users,
    Video,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/shared/Heading.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

const afficherApprouves = ref(false);
const rechercheTexte = ref('');
const filtreThematiqueId = ref<number | null>(null);

const props = defineProps<Props>();

function sessionLabel(session: string): string {
    const labels: Record<string, string> = {
        hiver: 'Hiver',
        ete: 'Été',
        automne: 'Automne',
    };

    return labels[session] ?? session;
}

const deleteCoursForm = useForm({});

function deleteCours(unCours: Cours): void {
    if (
        !confirm(
            t('enseignant.index.confirm_delete_course', {
                nom: unCours.nom_cours,
            }),
        )
    ) {
        return;
    }

    deleteCoursForm.delete(`/cours/${unCours.id}`);
}

const verrouillageForm = useForm({});

function toggleVerrouillage(unCours: Cours): void {
    verrouillageForm.patch(`/cours/${unCours.id}/verrouillage`);
}

const deleteThematiqueForm = useForm({});

function deleteThematique(thematique: Thematique): void {
    if (
        !confirm(
            t('enseignant.index.confirm_delete_thematic', {
                nom: thematique.nom,
            }),
        )
    ) {
        return;
    }

    deleteThematiqueForm.delete(`/thematiques/${thematique.id}`);
}

function filtrerTemoins(liste: TemoinEnAttente[]): TemoinEnAttente[] {
    const texte = rechercheTexte.value.trim().toLowerCase();

    return liste.filter((t) => {
        const matchTexte =
            texte === '' ||
            `${t.prenom} ${t.nom} ${t.email}`.toLowerCase().includes(texte);
        const matchThematique =
            filtreThematiqueId.value === null ||
            t.thematiques_choisies.some(
                (th) => th.id === filtreThematiqueId.value,
            );

        return matchTexte && matchThematique;
    });
}

const temoinsEnAttenteFiltres = computed(() =>
    filtrerTemoins(props.temoinsEnAttente),
);
const temoinsApprouvesFiltres = computed(() =>
    filtrerTemoins(props.temoinsApprouves),
);
const nombreTemoinsAffiches = computed(() =>
    afficherApprouves.value
        ? temoinsApprouvesFiltres.value.length
        : temoinsEnAttenteFiltres.value.length,
);

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
    window.open(
        `https://meet.jit.si/${jitsiRoom}`,
        '_blank',
        'noopener,noreferrer',
    );
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
                        <CardTitle>{{
                            $t('enseignant.index.my_courses')
                        }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">
                            ({{ cours.length }})
                        </span>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.cours }"
                        />
                    </button>
                    <Button size="sm" as-child>
                        <Link href="/enseignant/cours/create">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('enseignant.index.new_course') }}
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.cours">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_code',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_group',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        Session
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_course_name',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="pr-4 pb-3 text-center font-medium"
                                    >
                                        {{
                                            $t(
                                                'enseignant.index.table_header_students',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_actions',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="unCours in cours"
                                    :key="unCours.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-mono text-xs">
                                        {{ unCours.code }}
                                    </td>
                                    <td class="py-3 pr-4 font-mono text-xs">
                                        {{ unCours.groupe }}
                                    </td>
                                    <td
                                        class="py-3 pr-4 text-xs text-muted-foreground"
                                    >
                                        {{ sessionLabel(unCours.session) }}
                                        {{ unCours.annee }}
                                    </td>
                                    <td class="py-3 pr-4">
                                        {{ unCours.nom_cours }}
                                    </td>
                                    <td class="py-3 pr-4 text-center">
                                        {{ unCours.etudiants_count }}
                                    </td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <BoutonTooltip
                                                texte="Accéder au détail de ce cours"
                                                size="sm"
                                                variant="outline"
                                                as-child
                                            >
                                                <Link
                                                    :href="`/cours/${unCours.id}`"
                                                >
                                                    <ExternalLink
                                                        class="h-4 w-4"
                                                    />
                                                </Link>
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                texte="Modifier les informations de ce cours"
                                                size="sm"
                                                variant="outline"
                                                as-child
                                            >
                                                <Link
                                                    :href="`/enseignant/cours/${unCours.id}/edit`"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                </Link>
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                :texte="
                                                    unCours.is_verrouille
                                                        ? 'Déverrouiller — rendre accessible aux étudiants'
                                                        : 'Verrouiller — masquer aux étudiants'
                                                "
                                                size="sm"
                                                :variant="
                                                    unCours.is_verrouille
                                                        ? 'default'
                                                        : 'outline'
                                                "
                                                @click="
                                                    toggleVerrouillage(unCours)
                                                "
                                            >
                                                <Lock
                                                    v-if="unCours.is_verrouille"
                                                    class="h-4 w-4"
                                                />
                                                <Unlock
                                                    v-else
                                                    class="h-4 w-4"
                                                />
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
                                    <td
                                        colspan="6"
                                        class="py-6 text-center text-muted-foreground"
                                    >
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
                        <CardTitle>{{
                            $t('enseignant.index.my_thematic')
                        }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">
                            ({{ thematiques.length }})
                        </span>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.thematiques }"
                        />
                    </button>
                    <Button size="sm" as-child>
                        <Link href="/enseignant/thematiques/create">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('enseignant.index.new_thematic') }}
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent v-show="ouvert.thematiques">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_thematic_name',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_historical_period',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_description',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_actions',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="thematique in thematiques"
                                    :key="thematique.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">
                                        {{ thematique.nom }}
                                    </td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{
                                            thematique.periode_historique ?? '—'
                                        }}
                                    </td>
                                    <td
                                        class="max-w-xs truncate py-3 pr-4 text-muted-foreground"
                                    >
                                        {{ thematique.description ?? '—' }}
                                    </td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <BoutonTooltip
                                                texte="Modifier cette thématique"
                                                size="sm"
                                                variant="outline"
                                                as-child
                                            >
                                                <Link
                                                    :href="`/enseignant/thematiques/${thematique.id}/edit`"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                </Link>
                                            </BoutonTooltip>
                                            <BoutonTooltip
                                                texte="Supprimer cette thématique"
                                                size="sm"
                                                variant="destructive"
                                                @click="
                                                    deleteThematique(thematique)
                                                "
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </BoutonTooltip>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="thematiques.length === 0">
                                    <td
                                        colspan="4"
                                        class="py-6 text-center text-muted-foreground"
                                    >
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
                        <CardTitle>{{
                            $t('administration.index.temoins_table')
                        }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">
                            ({{ nombreTemoinsAffiches }})
                        </span>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.temoins }"
                        />
                    </button>
                    <!-- Toggle afficher approuvés -->
                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm"
                        @click.stop
                    >
                        <span class="text-muted-foreground">{{
                            $t('enseignant.index.temoins_show_approved')
                        }}</span>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="afficherApprouves"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                            :class="
                                afficherApprouves ? 'bg-green-500' : 'bg-input'
                            "
                            @click.stop="afficherApprouves = !afficherApprouves"
                        >
                            <span
                                class="inline-block h-3 w-3 rounded-full bg-white shadow-sm transition-transform"
                                :class="
                                    afficherApprouves
                                        ? 'translate-x-5'
                                        : 'translate-x-1'
                                "
                            />
                        </button>
                    </label>
                </CardHeader>
                <CardContent v-show="ouvert.temoins">
                    <!-- Barre de filtre -->
                    <div class="mb-4 flex flex-wrap gap-3">
                        <div class="relative min-w-48 flex-1">
                            <Search
                                class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                            />
                            <Input
                                v-model="rechercheTexte"
                                class="pl-8"
                                :placeholder="
                                    $t(
                                        'enseignant.index.temoins_filter_search_placeholder',
                                    )
                                "
                            />
                        </div>
                        <select
                            v-model="filtreThematiqueId"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                        >
                            <option :value="null">
                                {{
                                    $t(
                                        'enseignant.index.temoins_filter_all_thematiques',
                                    )
                                }}
                            </option>
                            <option
                                v-for="th in props.thematiques"
                                :key="th.id"
                                :value="th.id"
                            >
                                {{ th.nom }}
                            </option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <!-- Tableau : témoins en attente -->
                        <table v-if="!afficherApprouves" class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'administration.index.temoins_header_first_name',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'administration.index.temoins_header_last_name',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'administration.index.temoins_header_email',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-3 font-medium">
                                        {{
                                            $t(
                                                'administration.index.temoins_header_theme',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="temoin in temoinsEnAttenteFiltres"
                                    :key="temoin.id"
                                    class="cursor-pointer border-b last:border-0 hover:bg-muted/50"
                                    @click="voirTemoin(temoin)"
                                >
                                    <td class="py-3 pr-4">
                                        {{ temoin.prenom }}
                                    </td>
                                    <td class="py-3 pr-4">{{ temoin.nom }}</td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{ temoin.email }}
                                    </td>
                                    <td class="py-3">
                                        <span
                                            v-if="
                                                temoin.thematiques_choisies
                                                    .length
                                            "
                                        >
                                            {{
                                                temoin.thematiques_choisies
                                                    .map((th) => th.nom)
                                                    .join(', ')
                                            }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="temoinsEnAttenteFiltres.length === 0">
                                    <td
                                        colspan="4"
                                        class="py-6 text-center text-muted-foreground"
                                    >
                                        {{
                                            $t(
                                                'administration.index.temoins_no_pending',
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Tableau : témoins approuvés -->
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'administration.index.temoins_header_first_name',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'administration.index.temoins_header_last_name',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'administration.index.temoins_header_email',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-3 font-medium">
                                        {{
                                            $t(
                                                'administration.index.temoins_header_theme',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="temoin in temoinsApprouvesFiltres"
                                    :key="temoin.id"
                                    class="cursor-pointer border-b last:border-0 hover:bg-muted/50"
                                    @click="voirTemoin(temoin)"
                                >
                                    <td class="py-3 pr-4">
                                        {{ temoin.prenom }}
                                    </td>
                                    <td class="py-3 pr-4">{{ temoin.nom }}</td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{ temoin.email }}
                                    </td>
                                    <td class="py-3">
                                        <span
                                            v-if="
                                                temoin.thematiques_choisies
                                                    .length
                                            "
                                        >
                                            {{
                                                temoin.thematiques_choisies
                                                    .map((th) => th.nom)
                                                    .join(', ')
                                            }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="temoinsApprouvesFiltres.length === 0">
                                    <td
                                        colspan="4"
                                        class="py-6 text-center text-muted-foreground"
                                    >
                                        {{
                                            $t(
                                                'enseignant.index.temoins_no_approved',
                                            )
                                        }}
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
                        <CardTitle>{{
                            $t('enseignant.index.recent_submissions')
                        }}</CardTitle>
                        <span class="text-sm font-normal text-muted-foreground">
                            ({{ travauxRemis.length }})
                        </span>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.travaux }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.travaux">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_group_name',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_project_title',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_members',
                                            )
                                        }}
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_submitted_at',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-3 font-medium">
                                        {{
                                            $t(
                                                'enseignant.index.table_header_actions',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="travail in travauxRemis"
                                    :key="travail.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">
                                        {{
                                            $t('classes.groupes.group_number', {
                                                n: travail.classe.numero,
                                            })
                                        }}
                                    </td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{ travail.titre_projet ?? '—' }}
                                    </td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{
                                            travail.membres
                                                .map(
                                                    (m) =>
                                                        `${m.prenom} ${m.nom}`,
                                                )
                                                .join(', ')
                                        }}
                                    </td>
                                    <td class="py-3 pr-4 tabular-nums">
                                        +
                                        {{
                                            new Date(
                                                travail.remis_le,
                                            ).toLocaleDateString()
                                        }}
                                    </td>
                                    <td class="py-3">
                                        <BoutonTooltip
                                            texte="Accéder aux projets de ce groupe"
                                            size="sm"
                                            variant="outline"
                                            as-child
                                        >
                                            <Link
                                                :href="`/cours/${travail.classe.cours_id}/classes/${travail.classe.id}/projets`"
                                            >
                                                <ExternalLink class="h-4 w-4" />
                                                {{
                                                    $t(
                                                        'enseignant.index.view_project',
                                                    )
                                                }}
                                            </Link>
                                        </BoutonTooltip>
                                    </td>
                                </tr>
                                <tr v-if="travauxRemis.length === 0">
                                    <td
                                        colspan="5"
                                        class="py-6 text-center text-muted-foreground"
                                    >
                                        {{
                                            $t(
                                                'enseignant.index.no_submissions',
                                            )
                                        }}
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
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.visios }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.visios">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pr-4 pb-3 font-medium">Titre</th>
                                    <th class="pr-4 pb-3 font-medium">Cours</th>
                                    <th class="pr-4 pb-3 font-medium">
                                        Groupe
                                    </th>
                                    <th class="pr-4 pb-3 font-medium">Date</th>
                                    <th class="pb-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="visio in prochainesVisios"
                                    :key="visio.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">
                                        {{ visio.titre }}
                                    </td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        <span class="font-mono text-xs"
                                            >{{ visio.cours.code }}-{{
                                                visio.cours.groupe
                                            }}</span
                                        >
                                        <span class="ml-1">{{
                                            visio.cours.nom_cours
                                        }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{
                                            visio.groupe_numero
                                                ? `Groupe ${visio.groupe_numero}`
                                                : 'Tous les groupes'
                                        }}
                                    </td>
                                    <td class="py-3 pr-4 tabular-nums">
                                        <span class="flex items-center gap-1">
                                            <Calendar
                                                class="h-3.5 w-3.5 text-muted-foreground"
                                            />
                                            {{
                                                formatDateVisio(
                                                    visio.scheduled_at,
                                                )
                                            }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <Button
                                            v-if="visio.started_at"
                                            size="sm"
                                            @click="
                                                rejoindreVisio(visio.jitsi_room)
                                            "
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
    </AppLayout>
</template>
