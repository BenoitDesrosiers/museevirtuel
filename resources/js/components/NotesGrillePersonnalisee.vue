<script setup lang="ts">
type Etudiant = { id: number; prenom: string; nom: string };
type Critere = { id: number; label: string; ponderation: number };
type Malus = {
    id: number;
    label: string;
    deduction: number;
    description: string | null;
};

const props = defineProps<{
    /** Critères de la grille personnalisée (déjà triés par ordre). */
    criteres: Critere[];
    /** Malus de la grille personnalisée (déjà triés par ordre). */
    malus: Malus[];
    membres: Etudiant[];
    /** notes[userId][critereId] = valeur | undefined */
    notes: Record<number, Record<number, number | undefined>>;
    /** malus[userId][malusId] = applique */
    malusAppliques: Record<number, Record<number, boolean>>;
    /** notesSaving[`grille_${critereId}_${membreId|'tous'}`] = boolean */
    notesSaving: Record<string, boolean>;
    /** malusSaving[`malus_${malusId}_${membreId}`] = boolean */
    malusSaving: Record<string, boolean>;
    estEnseignant: boolean;
    userId: number;
    ongletActif: number | 'tous';
    /**
     * Déductions de points issues des annotations de correction inline, par étudiant.
     * Clé = userId, valeur = liste de { contenu, points_malus }.
     */
    annotationsMalus?: Record<
        number,
        Array<{ contenu: string; points_malus: number }>
    >;
}>();

const emit = defineEmits<{
    'set-onglet': [value: number | 'tous'];
    'save-note': [critereId: number, membreId: number, valeur: number];
    'save-note-pour-tous': [critereId: number, valeur: number];
    'toggle-malus': [malusId: number, membreId: number, applique: boolean];
    'toggle-malus-pour-tous': [malusId: number, applique: boolean];
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

/** Contribution d'une note pour un critère donné. */
function contribution(note: number, ponderation: number): string {
    return ((note / 4) * ponderation).toFixed(2);
}

/** Retourne true si tous les membres ont exactement cette valeur pour le critère. */
function tousOntNote(critereId: number, valeur: number): boolean {
    return (
        props.membres.length > 0 &&
        props.membres.every((m) => props.notes[m.id]?.[critereId] === valeur)
    );
}

/** Retourne true si tous les membres ont ce malus appliqué. */
function tousOntMalus(malusId: number): boolean {
    return (
        props.membres.length > 0 &&
        props.membres.every(
            (m) => props.malusAppliques[m.id]?.[malusId] === true,
        )
    );
}

/** Total des déductions appliquées pour un étudiant (malus grille + corrections inline), arrondi au centième. */
function totalMalus(membreId: number): number {
    const malusGrille = props.malus.reduce((total, m) => {
        return props.malusAppliques[membreId]?.[m.id]
            ? total + m.deduction
            : total;
    }, 0);
    const malusAnnotations = (props.annotationsMalus?.[membreId] ?? []).reduce(
        (total, a) => total + a.points_malus,
        0,
    );

    // Arrondi au centième pour éviter les erreurs de précision flottante JS (ex. 0.1 + 0.2)
    return Math.round((malusGrille + malusAnnotations) * 100) / 100;
}
</script>

<template>
    <div class="space-y-2">
        <!-- Onglets étudiants -->
        <div class="flex flex-wrap gap-1">
            <button
                v-if="estEnseignant"
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
            <template v-for="membre in membres" :key="membre.id">
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
                    {{ membre.prenom }} {{ membre.nom }}
                </button>
            </template>
        </div>

        <!-- Onglet Tous -->
        <div
            v-if="estEnseignant"
            v-show="ongletActif === 'tous'"
            class="space-y-3"
        >
            <div
                v-for="critere in criteres"
                :key="critere.id"
                class="space-y-1.5"
            >
                <span class="text-xs text-muted-foreground">
                    {{ critere.label }} ({{ critere.ponderation }} pts)
                </span>
                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="valeur in [0, 2, 3, 4]"
                        :key="valeur"
                        type="button"
                        :disabled="notesSaving[`grille_${critere.id}_tous`]"
                        class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                        :class="
                            tousOntNote(critere.id, valeur)
                                ? couleurNoteActif[valeur]
                                : 'border-border text-muted-foreground hover:border-primary hover:text-primary'
                        "
                        @click="emit('save-note-pour-tous', critere.id, valeur)"
                    >
                        {{ valeur }} — {{ labelNote[valeur] }}
                    </button>
                </div>
            </div>

            <!-- Section malus — onglet Tous -->
            <div v-if="malus.length > 0" class="space-y-2 border-t pt-3">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Malus
                </p>
                <div
                    v-for="m in malus"
                    :key="m.id"
                    class="flex items-center gap-2"
                >
                    <button
                        type="button"
                        :disabled="malusSaving[`malus_${m.id}_tous`]"
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors disabled:opacity-60"
                        :class="
                            tousOntMalus(m.id)
                                ? 'border-destructive bg-destructive text-destructive-foreground'
                                : 'border-border hover:border-destructive'
                        "
                        :title="
                            tousOntMalus(m.id)
                                ? 'Retirer ce malus à tous'
                                : 'Appliquer ce malus à tous'
                        "
                        @click="
                            emit(
                                'toggle-malus-pour-tous',
                                m.id,
                                !tousOntMalus(m.id),
                            )
                        "
                    >
                        <span
                            v-if="tousOntMalus(m.id)"
                            class="text-[10px] font-bold"
                            >✓</span
                        >
                    </button>
                    <span
                        class="text-xs"
                        :class="
                            tousOntMalus(m.id)
                                ? 'font-medium text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ m.label }}
                        <span class="tabular-nums"
                            >-{{ m.deduction }} pt{{
                                m.deduction !== 1 ? 's' : ''
                            }}</span
                        >
                    </span>
                    <span
                        v-if="m.description"
                        class="text-xs text-muted-foreground"
                        >({{ m.description }})</span
                    >
                </div>
            </div>
        </div>

        <!-- Onglets individuels -->
        <div v-for="membre in membres" :key="membre.id">
            <div v-show="ongletActif === membre.id" class="space-y-4">
                <!-- Critères -->
                <div
                    v-for="critere in criteres"
                    :key="critere.id"
                    class="space-y-1.5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="text-xs text-muted-foreground">
                            {{ critere.label }} ({{ critere.ponderation }} pts)
                        </span>
                        <span
                            v-if="notes[membre.id]?.[critere.id] !== undefined"
                            class="text-xs font-medium text-muted-foreground"
                        >
                            {{
                                contribution(
                                    notes[membre.id][critere.id]!,
                                    critere.ponderation,
                                )
                            }}
                            / {{ critere.ponderation }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="valeur in [0, 2, 3, 4]"
                            :key="valeur"
                            type="button"
                            :disabled="
                                !estEnseignant ||
                                notesSaving[`grille_${critere.id}_${membre.id}`]
                            "
                            class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                            :class="
                                notes[membre.id]?.[critere.id] === valeur
                                    ? couleurNoteActif[valeur]
                                    : 'border-border text-muted-foreground hover:border-primary hover:text-primary'
                            "
                            @click="
                                emit('save-note', critere.id, membre.id, valeur)
                            "
                        >
                            {{ valeur }} — {{ labelNote[valeur] }}
                        </button>
                    </div>
                </div>

                <!-- Section malus grille -->
                <div v-if="malus.length > 0" class="space-y-2 border-t pt-3">
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Malus
                    </p>
                    <div
                        v-for="m in malus"
                        :key="m.id"
                        class="flex items-center gap-2"
                    >
                        <button
                            type="button"
                            :disabled="
                                !estEnseignant ||
                                malusSaving[`malus_${m.id}_${membre.id}`]
                            "
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors disabled:opacity-60"
                            :class="
                                malusAppliques[membre.id]?.[m.id]
                                    ? 'border-destructive bg-destructive text-destructive-foreground'
                                    : 'border-border hover:border-destructive'
                            "
                            :title="
                                malusAppliques[membre.id]?.[m.id]
                                    ? 'Retirer ce malus'
                                    : 'Appliquer ce malus'
                            "
                            @click="
                                emit(
                                    'toggle-malus',
                                    m.id,
                                    membre.id,
                                    !malusAppliques[membre.id]?.[m.id],
                                )
                            "
                        >
                            <span
                                v-if="malusAppliques[membre.id]?.[m.id]"
                                class="text-[10px] font-bold"
                                >✓</span
                            >
                        </button>
                        <span
                            class="text-xs"
                            :class="
                                malusAppliques[membre.id]?.[m.id]
                                    ? 'font-medium text-destructive'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ m.label }}
                            <span class="tabular-nums"
                                >-{{ m.deduction }} pt{{
                                    m.deduction !== 1 ? 's' : ''
                                }}</span
                            >
                        </span>
                        <span
                            v-if="m.description"
                            class="text-xs text-muted-foreground"
                            >({{ m.description }})</span
                        >
                    </div>
                    <p
                        v-if="totalMalus(membre.id) > 0"
                        class="text-xs font-medium text-destructive"
                    >
                        Total malus : -{{ totalMalus(membre.id) }} pts
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
