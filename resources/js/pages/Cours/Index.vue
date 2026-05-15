<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, BookMarked, Calendar, ChevronDown, ChevronRight, ExternalLink, FolderOpen, Users, Video } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import EtudiantReferences from '@/components/EtudiantReferences.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Cours = {
    id: number;
    nom_cours: string;
    description: string | null;
    code: string;
    groupe: string;
    annee: number;
    session: 'hiver' | 'ete' | 'automne';
    enseignant: {
        id: number;
        prenom: string;
        nom: string;
    };
    classe_id: number | null;
};

type Projet = {
    id: number;
    titre: string | null;
    type_projet: { id: number; nom: string };
    groupe_id: number;
    classe_id: number;
    cours_id: number;
};

type ProchaineVisio = {
    id: number;
    titre: string;
    scheduled_at: string;
    started_at: string | null;
    jitsi_room: string;
    cours: { id: number; nom_cours: string };
    animateur: { prenom: string; nom: string };
};

type MesReference = {
    id: number;
    titre: string;
    auteurs: { prenom: string; nom: string }[] | null;
    annee: number | null;
    type_source: string | null;
    url: string | null;
    doi: string | null;
    publication: string | null;
    ordre: number;
    est_depuis_zotero: boolean;
};

type ZoteroConfig = {
    configure: boolean;
    synchronise_le: string | null;
};

const sessionLabel: Record<string, string> = { hiver: 'Hiver', ete: 'Été', automne: 'Automne' };

type Props = {
    cours: Cours[];
    projets: Projet[];
    prochainesVisios: ProchaineVisio[];
    mesReferences: MesReference[];
    zoteroConfig: ZoteroConfig;
};

defineProps<Props>();

const ouvert = ref({ projets: true, references: true, visios: true });

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('fr-CA', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function rejoindre(jitsiRoom: string) {
    window.open(`https://meet.jit.si/${jitsiRoom}`, '_blank', 'noopener,noreferrer');
}

function projetUrl(projet: Projet): string {
    return `/cours/${projet.cours_id}/classes/${projet.classe_id}/groupes/${projet.groupe_id}/projets/${projet.type_projet.id}/edit`;
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('cours.index.page_title')" />

        <div class="flex flex-col gap-4 p-6">
            <!-- Section : Mes cours (toujours ouverte) -->
            <Card>
                <CardHeader class="flex flex-row items-center gap-2">
                    <BookOpen class="h-5 w-5" />
                    <CardTitle>{{ $t('cours.index.heading_title') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="cours.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        {{ $t('cours.index.no_courses') }}
                    </div>
                    <div v-else class="flex flex-col divide-y">
                        <div
                            v-for="unCours in cours"
                            :key="unCours.id"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium">{{ unCours.nom_cours }}</span>
                                    <span class="font-mono text-xs text-muted-foreground">
                                        {{ unCours.code }} — Groupe {{ unCours.groupe }}
                                    </span>
                                    <Badge variant="outline" class="text-xs">
                                        {{ sessionLabel[unCours.session] }} {{ unCours.annee }}
                                    </Badge>
                                </div>
                                <div class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                    <Users class="h-3 w-3" />
                                    {{ unCours.enseignant.prenom }} {{ unCours.enseignant.nom }}
                                </div>
                            </div>
                            <BoutonTooltip
                                :texte="$t('cours.index.my_sections')"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <Link :href="`/cours/${unCours.id}/classes/${unCours.classe_id}`">
                                    <ExternalLink class="h-4 w-4" />
                                </Link>
                            </BoutonTooltip>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Section : Mes projets -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer select-none items-center gap-2 text-left"
                        @click="ouvert.projets = !ouvert.projets"
                    >
                        <FolderOpen class="h-5 w-5" />
                        <CardTitle>Mes projets</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.projets }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.projets">
                    <div v-if="projets.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        Aucun projet disponible pour l'instant.
                    </div>
                    <div v-else class="flex flex-col divide-y">
                        <Link
                            v-for="projet in projets"
                            :key="projet.id"
                            :href="projetUrl(projet)"
                            class="-mx-6 flex items-center justify-between gap-4 px-6 py-3 transition-colors hover:bg-muted/50"
                        >
                            <div class="min-w-0 flex-1">
                                <span class="text-sm font-medium">{{ projet.type_projet.nom }}</span>
                                <p v-if="projet.titre" class="text-xs text-muted-foreground">
                                    {{ projet.titre }}
                                </p>
                                <p v-else class="text-xs italic text-muted-foreground/60">Sans titre</p>
                            </div>
                            <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground" />
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Section : Mes références bibliographiques -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer select-none items-center gap-2 text-left"
                        @click="ouvert.references = !ouvert.references"
                    >
                        <BookMarked class="h-5 w-5" />
                        <CardTitle>Mes références</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ '-rotate-180': ouvert.references }"
                        />
                    </button>
                </CardHeader>
                <CardContent v-show="ouvert.references">
                    <EtudiantReferences :references="mesReferences" :zotero-config="zoteroConfig" />
                </CardContent>
            </Card>

            <!-- Section : Prochaines visioconférences -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer select-none items-center gap-2 text-left"
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
                    <div v-if="prochainesVisios.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        Aucune visioconférence planifiée.
                    </div>
                    <div v-else class="flex flex-col divide-y">
                        <div
                            v-for="visio in prochainesVisios"
                            :key="visio.id"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium">{{ visio.titre }}</span>
                                    <Badge v-if="visio.started_at" variant="default" class="text-xs">En cours</Badge>
                                    <Badge v-else variant="outline" class="text-xs">Planifiée</Badge>
                                </div>
                                <div class="mt-0.5 flex items-center gap-3 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <Calendar class="h-3 w-3" />
                                        {{ formatDate(visio.scheduled_at) }}
                                    </span>
                                    <span>{{ visio.cours.nom_cours }}</span>
                                </div>
                            </div>
                            <Button
                                v-if="visio.started_at"
                                size="sm"
                                @click="rejoindre(visio.jitsi_room)"
                            >
                                <Video class="mr-1.5 h-4 w-4" />
                                Rejoindre
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
