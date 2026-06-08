<script setup lang="ts">
import { computed } from 'vue';
type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type CritereConfig = {
    label: string;
    poids: number;
};

const props = defineProps<{
    /** Clé unique de section — utilisée pour construire les clés de notesSaving. */
    section: string;
    /** Liste ordonnée des clés de critères à afficher. */
    critereKeys: string[];
    /** Config complète des critères (label + poids). */
    critereConfig: Record<string, CritereConfig>;
    membres: Etudiant[];
    /** notes[userId][critere] = valeur | undefined */
    notes: Record<number, Record<string, number | undefined>>;
    /** notesSaving[`${section}_${critere}_${membreId|'tous'}`] = boolean */
    notesSaving: Record<string, boolean>;
    estEnseignant: boolean;
    userId: number;
    /** Onglet actuellement actif, calculé par le parent via getOngletActif(). */
    ongletActif: number | 'tous';
    /**
     * Verrouille la grille sur un étudiant spécifique.
     * Masque l'onglet "Tous" et les onglets des autres membres.
     * Utilisé dans les sections conclusion individuelles.
     */
    membreVerrouille?: number;
}>();

const emit = defineEmits<{
    'set-onglet': [value: number | 'tous'];
    'save-note': [critere: string, membreId: number, valeur: number];
    'save-note-pour-tous': [critere: string, valeur: number];
}>();

/** Membres visibles selon le verrouillage éventuel. */
const membresVisibles = computed(() =>
    props.membreVerrouille !== undefined
        ? props.membres.filter((m) => m.id === props.membreVerrouille)
        : props.membres,
);

const labelNote: Record<number, string> = {
    0: 'Mauvais',
    2: 'Passable',
    3: 'Bon',
    4: 'Excellent',
};

const couleurNoteActif: Record<number, string> = {
    0: 'bg-red-100 text-red-700 border-red-400',
    2: 'bg-yellow-100 text-yellow-700 border-yellow-400',
    3: 'bg-blue-100 text-blue-700 border-blue-400',
    4: 'bg-green-100 text-green-700 border-green-400',
};

/** Retourne true si tous les membres ont exactement cette valeur pour le critère. */
function tousOntNote(critere: string, valeur: number): boolean {
    return (
        props.membres.length > 0 &&
        props.membres.every((m) => props.notes[m.id]?.[critere] === valeur)
    );
}
</script>

<template>
    <div class="space-y-2">
        <!-- Onglets étudiants -->
        <div class="flex flex-wrap gap-1">
            <button
                v-if="estEnseignant && membreVerrouille === undefined"
                type="button"
                class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                :class="
                    ongletActif === 'tous'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                "
                @click="emit('set-onglet', 'tous')"
            >
                Tous
            </button>
            <template v-for="membre in membresVisibles" :key="membre.id">
                <button
                    v-if="estEnseignant || membre.id === userId"
                    type="button"
                    class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                    :class="
                        ongletActif === membre.id
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                    "
                    @click="emit('set-onglet', membre.id)"
                >
                    {{ membre.prenom }}
                </button>
            </template>
        </div>

        <!-- Onglet Tous (masqué si grille verrouillée) -->
        <div
            v-if="estEnseignant && membreVerrouille === undefined"
            v-show="ongletActif === 'tous'"
            class="space-y-3"
        >
            <div
                v-for="critere in critereKeys"
                :key="critere"
                class="space-y-1.5"
            >
                <span class="text-xs text-muted-foreground"
                    >{{ critereConfig[critere].label }} ({{
                        critereConfig[critere].poids
                    }}%)</span
                >
                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="valeur in [0, 2, 3, 4]"
                        :key="valeur"
                        type="button"
                        :disabled="notesSaving[`${section}_${critere}_tous`]"
                        class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                        :class="
                            tousOntNote(critere, valeur)
                                ? couleurNoteActif[valeur]
                                : 'border-border text-muted-foreground hover:border-primary hover:text-primary'
                        "
                        @click="emit('save-note-pour-tous', critere, valeur)"
                    >
                        {{ valeur }} — {{ labelNote[valeur] }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Onglets individuels -->
        <div v-for="membre in membresVisibles" :key="membre.id">
            <div v-show="ongletActif === membre.id" class="space-y-3">
                <div
                    v-for="critere in critereKeys"
                    :key="critere"
                    class="space-y-1.5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="text-xs text-muted-foreground"
                            >{{ critereConfig[critere].label }} ({{
                                critereConfig[critere].poids
                            }}%)</span
                        >
                        <span
                            v-if="notes[membre.id]?.[critere] !== undefined"
                            class="text-xs font-medium text-muted-foreground"
                        >
                            {{
                                (
                                    (notes[membre.id][critere]! / 4) *
                                    critereConfig[critere].poids
                                ).toFixed(2)
                            }}
                            / {{ critereConfig[critere].poids }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="valeur in [0, 2, 3, 4]"
                            :key="valeur"
                            type="button"
                            :disabled="
                                !estEnseignant ||
                                notesSaving[
                                    `${section}_${critere}_${membre.id}`
                                ]
                            "
                            class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                            :class="
                                notes[membre.id]?.[critere] === valeur
                                    ? couleurNoteActif[valeur]
                                    : 'border-border text-muted-foreground hover:border-primary hover:text-primary'
                            "
                            @click="
                                emit('save-note', critere, membre.id, valeur)
                            "
                        >
                            {{ valeur }} — {{ labelNote[valeur] }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
