<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Users } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
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
};

const sessionLabel: Record<string, string> = { hiver: 'Hiver', ete: 'Été', automne: 'Automne' };

type Props = {
    cours: Cours[];
};

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <Head :title="$t('cours.index.page_title')" />

        <div class="flex flex-col gap-6 p-6">
            <Heading
                :title="$t('cours.index.heading_title')"
                :description="$t('cours.index.heading_description')"
            />

            <div v-if="cours.length === 0" class="text-muted-foreground py-12 text-center">
                {{ $t('cours.index.no_courses') }}
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="unCours in cours"
                    :key="unCours.id"
                    class="flex flex-col"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-muted-foreground font-mono text-xs">
                                    {{ unCours.code }} — Groupe {{ unCours.groupe }}
                                </span>
                                <span class="text-muted-foreground text-xs">
                                    {{ sessionLabel[unCours.session] }} {{ unCours.annee }}
                                </span>
                                <CardTitle class="mt-1 text-base">{{ unCours.nom_cours }}</CardTitle>
                            </div>
                            <BookOpen class="text-muted-foreground mt-1 h-5 w-5 shrink-0" />
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-3">
                        <p
                            v-if="unCours.description"
                            class="text-muted-foreground text-sm"
                        >
                            {{ unCours.description }}
                        </p>

                        <div class="text-muted-foreground flex flex-col gap-1 text-xs">
                            <div class="flex items-center gap-1">
                                <Users class="h-3 w-3" />
                                {{ unCours.enseignant.prenom }} {{ unCours.enseignant.nom }}
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="border-t pt-3">
                        <Button variant="outline" size="sm" class="w-full" as-child>
                            <Link :href="`/cours/${unCours.id}/classes`">
                                <Users class="mr-2 h-4 w-4" />
                                {{ $t('cours.index.my_sections') }}
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
