<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { MessageSquare, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type User = {
    id: number;
    prenom: string;
    nom: string;
};

type Classe = {
    id: number;
    nom_cours: string;
    code: string;
};

type GroupeAssigne = {
    id: number;
    numero: number;
    classe: Classe;
    membres: User[];
    nb_echanges: number;
};

type Props = {
    groupes: GroupeAssigne[];
};

const props = defineProps<Props>();

const page = usePage();
const user = computed(() => page.props.auth?.user as { prenom: string; nom: string } | undefined);
</script>

<template>
    <AppLayout>
        <Head title="Espace Témoin" />

        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold">
                    Bonjour {{ user?.prenom }} !
                </h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Bienvenue dans votre espace Témoin.
                </p>
            </div>

            <!-- Aucun groupe -->
            <div
                v-if="groupes.length === 0"
                class="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm"
            >
                Vous n'êtes encore assigné(e) à aucun groupe.<br>
                Un enseignant vous contactera bientôt.
            </div>

            <!-- Liste des groupes assignés -->
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="groupe in groupes"
                    :key="groupe.id"
                    class="flex flex-col"
                >
                    <CardHeader>
                        <CardTitle class="text-base">
                            Groupe {{ groupe.numero }}
                        </CardTitle>
                        <p class="text-muted-foreground text-sm">
                            {{ groupe.classe.code }} — {{ groupe.classe.nom_cours }}
                        </p>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-4">
                        <!-- Membres -->
                        <div>
                            <p class="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide">
                                <Users class="h-3.5 w-3.5" />
                                Membres
                            </p>
                            <ul class="space-y-1">
                                <li
                                    v-for="membre in groupe.membres"
                                    :key="membre.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <span class="bg-primary/10 text-primary flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium">
                                        {{ membre.prenom[0] }}{{ membre.nom[0] }}
                                    </span>
                                    {{ membre.prenom }} {{ membre.nom }}
                                </li>
                            </ul>
                        </div>

                        <!-- Bouton échanges -->
                        <div class="mt-auto">
                            <Button class="w-full" as-child>
                                <Link :href="`/classes/${groupe.classe.id}/groupes/${groupe.id}/echanges`">
                                    <MessageSquare class="mr-2 h-4 w-4" />
                                    Voir les échanges
                                    <span
                                        v-if="groupe.nb_echanges > 0"
                                        class="bg-primary-foreground/20 ml-2 rounded-full px-1.5 py-0.5 text-xs"
                                    >
                                        {{ groupe.nb_echanges }}
                                    </span>
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
