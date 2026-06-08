<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Users } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type Classe = {
    id: number;
    nom_cours: string;
    description: string | null;
    code: string;
    groupe: string;
    enseignant: {
        id: number;
        prenom: string;
        nom: string;
    };
    pivot: {
        no_da: string;
        statut_cours: string | null;
    };
};

type Props = {
    classes: Classe[];
};

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <Head :title="$t('classes.index.page_title')" />

        <div class="flex flex-col gap-6 p-6">
            <Heading
                :title="$t('classes.index.heading_title')"
                :description="$t('classes.index.heading_description')"
            />

            <div
                v-if="classes.length === 0"
                class="py-12 text-center text-muted-foreground"
            >
                {{ $t('classes.index.no_classes') }}
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="classe in classes"
                    :key="classe.id"
                    class="flex flex-col"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ classe.code }} — Groupe
                                    {{ classe.groupe }}
                                </span>
                                <CardTitle class="mt-1 text-base">{{
                                    classe.nom_cours
                                }}</CardTitle>
                            </div>
                            <BookOpen
                                class="mt-1 h-5 w-5 shrink-0 text-muted-foreground"
                            />
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-3">
                        <p
                            v-if="classe.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ classe.description }}
                        </p>

                        <div
                            class="flex flex-col gap-1 text-xs text-muted-foreground"
                        >
                            <div class="flex items-center gap-1">
                                <Users class="h-3 w-3" />
                                {{ classe.enseignant.prenom }}
                                {{ classe.enseignant.nom }}
                            </div>
                        </div>

                        <div class="mt-auto flex gap-4 border-t pt-3 text-xs">
                            <div>
                                <span class="text-muted-foreground">No DA</span>
                                <p class="font-mono font-medium">
                                    {{ classe.pivot.no_da }}
                                </p>
                            </div>
                            <div v-if="classe.pivot.statut_cours">
                                <span class="text-muted-foreground"
                                    >Statut</span
                                >
                                <p class="font-medium">
                                    {{ classe.pivot.statut_cours }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="border-t pt-3">
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            as-child
                        >
                            <Link :href="`/classes/${classe.id}/groupes`">
                                <Users class="mr-2 h-4 w-4" />
                                {{ $t('classes.index.groups') }}
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
