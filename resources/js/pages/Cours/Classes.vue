<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Users } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
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
    nom: string | null;
    cours_id: number;
    groupes_count: number;
};

type Props = {
    cours: Cours;
    classes: Classe[];
};

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <Head :title="`${$t('cours.classes.page_title')} — ${cours.nom_cours}`" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link href="/cours">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('cours.classes.back') }}
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="`${cours.code} — Groupe ${cours.groupe}`"
                :description="cours.nom_cours"
            />

            <!-- Liste des sections -->
            <div v-if="classes.length === 0" class="py-12 text-center text-muted-foreground">
                {{ $t('cours.classes.no_classes') }}
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="classe in classes"
                    :key="classe.id"
                    class="flex flex-col"
                >
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{ classe.nom ?? classe.code }}
                        </CardTitle>
                        <p class="font-mono text-xs text-muted-foreground">{{ classe.code }}</p>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-2 text-sm text-muted-foreground">
                        <div class="flex items-center gap-1">
                            <Users class="h-3 w-3" />
                            {{ $t('cours.classes.groups_count', { n: classe.groupes_count }) }}
                        </div>
                    </CardContent>
                    <CardFooter class="border-t pt-3">
                        <Button variant="outline" size="sm" class="w-full" as-child>
                            <Link :href="`/cours/${cours.id}/classes/${classe.id}/groupes`">
                                <Users class="mr-2 h-4 w-4" />
                                {{ $t('cours.classes.my_group') }}
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
