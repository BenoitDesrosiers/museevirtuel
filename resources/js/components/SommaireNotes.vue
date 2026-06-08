<script setup lang="ts">
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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
    open: boolean;
    etudiant: Etudiant | null;
    /** notes[critere] = valeur | undefined — notes de l'étudiant sélectionné seulement. */
    notes: Record<string, number | undefined>;
    /** Config complète de tous les critères (label + poids). */
    criteres: Record<string, CritereConfig>;
    /** Critères groupés par section : { section: [critere, ...] } */
    criteresSections: Record<string, string[]>;
    estEnseignant: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    /** Émis quand l'enseignant clique sur un bouton de note. */
    'save-note': [critere: string, valeur: number];
}>();

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

const couleurNoteInactif =
    'border-border text-muted-foreground hover:border-primary hover:text-primary';

/**
 * Sections à afficher.
 * Enseignant : toutes les sections (pour pouvoir saisir la première note).
 * Étudiant   : uniquement les sections ayant au moins une note saisie.
 */
const sectionsVisibles = computed(() =>
    props.estEnseignant
        ? Object.entries(props.criteresSections)
        : Object.entries(props.criteresSections).filter(([, criteres]) =>
              criteres.some((c) => props.notes[c] !== undefined),
          ),
);

/**
 * Note finale pondérée calculée côté frontend (miroir de ProjetNote::noteFinale).
 * Les critères non remplis valent 4 (excellent) par défaut — toujours entre 0 et 100.
 */
const noteFinale = computed<number>(() => {
    return (
        Math.round(
            Object.entries(props.criteres).reduce((total, [cle, config]) => {
                // Critère non rempli → note maximale (4 = excellent) par défaut
                const note = props.notes[cle] ?? 4;

                return total + (note / 4) * config.poids;
            }, 0) * 100,
        ) / 100
    );
});

/** Score partiel d'un critère. */
function scorePartiel(critere: string): string {
    const valeur = props.notes[critere];

    if (valeur === undefined) {
        return '—';
    }

    const poids = props.criteres[critere].poids;

    return `${((valeur / 4) * poids).toFixed(2)} / ${poids}`;
}

const labelSection: Record<string, string> = {
    page_titre: 'Page titre',
    introduction_amener: 'Introduction — sujet amené',
    introduction_poser: 'Introduction — sujet posé',
    introduction_diviser: 'Introduction — sujet divisé',
    developpement: 'Développement',
    conclusion: 'Conclusion',
    references_et_ecriture: 'Références et écriture',
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    Sommaire — {{ etudiant?.prenom }} {{ etudiant?.nom }}
                </DialogTitle>
            </DialogHeader>

            <div
                v-if="sectionsVisibles.length === 0"
                class="py-4 text-center text-sm text-muted-foreground"
            >
                Aucune note saisie pour cet étudiant.
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="[section, criteres] in sectionsVisibles"
                    :key="section"
                >
                    <p
                        class="mb-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ labelSection[section] ?? section }}
                    </p>
                    <div class="space-y-2">
                        <div
                            v-for="critere in criteres"
                            :key="critere"
                            class="rounded-md border bg-muted/30 px-3 py-2"
                        >
                            <!-- Label + score -->
                            <div
                                class="mb-1.5 flex items-center justify-between gap-2"
                            >
                                <span class="text-xs text-muted-foreground">
                                    {{ props.criteres[critere]?.label }}
                                    ({{ props.criteres[critere]?.poids }}%)
                                </span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ scorePartiel(critere) }}
                                </span>
                            </div>

                            <!-- Boutons d'édition (enseignant) -->
                            <div
                                v-if="estEnseignant"
                                class="flex flex-wrap gap-1"
                            >
                                <button
                                    v-for="valeur in [0, 2, 3, 4]"
                                    :key="valeur"
                                    type="button"
                                    class="rounded border px-2 py-0.5 text-xs font-medium transition-colors"
                                    :class="
                                        notes[critere] === valeur
                                            ? couleurNoteActif[valeur]
                                            : couleurNoteInactif
                                    "
                                    @click="emit('save-note', critere, valeur)"
                                >
                                    {{ valeur }} — {{ labelNote[valeur] }}
                                </button>
                            </div>

                            <!-- Badge lecture seule (étudiant) -->
                            <div v-else>
                                <span
                                    v-if="notes[critere] !== undefined"
                                    class="rounded px-1.5 py-0.5 text-xs font-medium"
                                    :class="couleurNoteActif[notes[critere]!]"
                                >
                                    {{ labelNote[notes[critere]!] }}
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Note finale -->
                <div
                    class="flex items-center justify-between rounded-lg border-2 px-3 py-2"
                    :class="
                        noteFinale >= 60
                            ? 'border-green-400 bg-green-50'
                            : 'border-red-400 bg-red-50'
                    "
                >
                    <span class="text-sm font-semibold">Note finale</span>
                    <span class="text-sm font-bold">
                        {{ noteFinale }} / 100
                    </span>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
