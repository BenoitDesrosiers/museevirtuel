<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, GraduationCap } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type Enseignant = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    cours_count: number;
    thematiques_count: number;
};

type Thematique = {
    id: number;
    nom: string;
    description: string | null;
    periode_historique: string | null;
    enseignant: { id: number; prenom: string; nom: string } | null;
};

type Etablissement = {
    id: number;
    nom: string;
    ville: string;
    code: string | null;
    enseignants: Enseignant[];
    thematiques: Thematique[];
};

defineProps<{ etablissement: Etablissement }>();
</script>

<template>
    <AppLayout>
        <Head :title="etablissement.nom" />

        <div class="flex flex-col gap-6 p-6">
            <!-- En-tête -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" as-child>
                    <Link href="/administration">
                        <ArrowLeft class="mr-1 h-4 w-4" />
                        {{ $t('common.back') }}
                    </Link>
                </Button>
            </div>

            <Heading
                :title="etablissement.nom"
                :description="etablissement.ville + (etablissement.code ? ' · ' + etablissement.code : '')"
            />

            <!-- Enseignants -->
            <Card>
                <CardHeader class="flex flex-row items-center gap-2">
                    <GraduationCap class="text-muted-foreground h-5 w-5" />
                    <CardTitle>{{ $t('administration.etablissement.teachers_title') }}</CardTitle>
                    <Badge variant="secondary" class="ml-auto">{{ etablissement.enseignants.length }}</Badge>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.table_header_first_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.table_header_last_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.index.table_header_email') }}</th>
                                    <th class="pb-3 pr-4 text-center font-medium">{{ $t('administration.index.table_header_classes') }}</th>
                                    <th class="pb-3 text-center font-medium">{{ $t('administration.index.table_header_thematics') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="enseignant in etablissement.enseignants"
                                    :key="enseignant.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4">{{ enseignant.prenom }}</td>
                                    <td class="py-3 pr-4">{{ enseignant.nom }}</td>
                                    <td class="text-muted-foreground py-3 pr-4">{{ enseignant.email }}</td>
                                    <td class="py-3 pr-4 text-center">{{ enseignant.cours_count }}</td>
                                    <td class="py-3 text-center">{{ enseignant.thematiques_count }}</td>
                                </tr>
                                <tr v-if="etablissement.enseignants.length === 0">
                                    <td colspan="5" class="text-muted-foreground py-6 text-center">
                                        {{ $t('administration.etablissement.no_teachers') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Thématiques -->
            <Card>
                <CardHeader class="flex flex-row items-center gap-2">
                    <BookOpen class="text-muted-foreground h-5 w-5" />
                    <CardTitle>{{ $t('administration.etablissement.thematics_title') }}</CardTitle>
                    <Badge variant="secondary" class="ml-auto">{{ etablissement.thematiques.length }}</Badge>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.etablissement.thematic_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('administration.etablissement.thematic_period') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('administration.etablissement.thematic_teacher') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="thematique in etablissement.thematiques"
                                    :key="thematique.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">{{ thematique.nom }}</td>
                                    <td class="text-muted-foreground py-3 pr-4">{{ thematique.periode_historique ?? '—' }}</td>
                                    <td class="text-muted-foreground py-3">
                                        {{ thematique.enseignant ? thematique.enseignant.prenom + ' ' + thematique.enseignant.nom : '—' }}
                                    </td>
                                </tr>
                                <tr v-if="etablissement.thematiques.length === 0">
                                    <td colspan="3" class="text-muted-foreground py-6 text-center">
                                        {{ $t('administration.etablissement.no_thematics') }}
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
