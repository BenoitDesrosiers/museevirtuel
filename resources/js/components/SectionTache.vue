<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Circle, User } from 'lucide-vue-next';
import groupesTachesRoutes from '@/routes/groupes/taches';

type RouteParams = {
    cours: number;
    classe: number;
    groupe: number;
    typeProjet: number;
};

type Membre = {
    id: number;
    prenom: string;
    nom: string;
};

type Tache = {
    id: number;
    titre: string;
    description: string | null;
    ordre: number;
    assigne_a: Membre | null;
    completed_at: string | null;
};

const props = defineProps<{
    params: RouteParams;
    taches: Tache[];
    membres: Membre[];
    readonly?: boolean;
}>();

/**
 * Bascule l'état complété/non-complété d'une tâche.
 */
function toggleCompleted(tache: Tache): void {
    if (props.readonly) return;

    router.patch(
        groupesTachesRoutes.toggle.url({ ...props.params, tache: tache.id }),
        {},
        { preserveScroll: true },
    );
}

/**
 * Assigne un membre à une tâche.
 */
function assigner(tache: Tache, membreId: number | null): void {
    if (props.readonly) return;

    router.patch(
        groupesTachesRoutes.assigner.url({ ...props.params, tache: tache.id }),
        { assigne_a: membreId },
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-2">
        <!-- Liste vide -->
        <p
            v-if="taches.length === 0"
            class="text-sm text-muted-foreground italic"
        >
            Aucune tâche définie pour cette section.
        </p>

        <!-- Liste des tâches -->
        <div
            v-for="tache in taches"
            :key="tache.id"
            :class="[
                'flex items-start gap-3 rounded-md border px-4 py-3 transition-colors',
                tache.completed_at
                    ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/30'
                    : 'bg-card hover:bg-muted/50',
            ]"
        >
            <!-- Bouton toggle complété -->
            <button
                type="button"
                class="mt-0.5 shrink-0 text-muted-foreground transition-colors hover:text-green-600 disabled:cursor-default disabled:opacity-50"
                :disabled="readonly"
                :title="tache.completed_at ? 'Marquer non-complétée' : 'Marquer complétée'"
                @click="toggleCompleted(tache)"
            >
                <CheckCircle2
                    v-if="tache.completed_at"
                    class="h-5 w-5 text-green-600"
                />
                <Circle v-else class="h-5 w-5" />
            </button>

            <!-- Contenu de la tâche -->
            <div class="min-w-0 flex-1">
                <p
                    :class="[
                        'text-sm font-medium leading-snug',
                        tache.completed_at ? 'text-muted-foreground line-through' : '',
                    ]"
                >
                    {{ tache.titre }}
                </p>
                <p
                    v-if="tache.description"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    {{ tache.description }}
                </p>

                <!-- Assignation -->
                <div class="mt-2 flex items-center gap-1.5">
                    <User class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                    <template v-if="readonly">
                        <span class="text-xs text-muted-foreground">
                            {{
                                tache.assigne_a
                                    ? `${tache.assigne_a.prenom} ${tache.assigne_a.nom}`
                                    : 'Non assignée'
                            }}
                        </span>
                    </template>
                    <template v-else>
                        <select
                            class="h-6 rounded border border-input bg-background px-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                            :value="tache.assigne_a?.id ?? ''"
                            @change="assigner(tache, ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
                        >
                            <option value="">— Non assignée —</option>
                            <option
                                v-for="membre in membres"
                                :key="membre.id"
                                :value="membre.id"
                            >
                                {{ membre.prenom }} {{ membre.nom }}
                            </option>
                        </select>
                    </template>
                </div>
            </div>

            <!-- Date de complétion -->
            <span
                v-if="tache.completed_at"
                class="shrink-0 text-xs text-green-600"
            >
                ✓
            </span>
        </div>
    </div>
</template>
