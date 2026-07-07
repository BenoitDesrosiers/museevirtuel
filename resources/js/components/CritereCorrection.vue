<script setup lang="ts">
import axios from 'axios';
import {
    CheckCheck,
    GitFork,
    Loader2,
    MessageSquare,
    TriangleAlert,
    Trash2,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import {
    clonerCritereCorrection,
    destroyCritereCorrection,
    upsertCritereCorrection,
} from '@/actions/App/Http/Controllers/ProjetRechercheController';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

// ─── Types exportés ───────────────────────────────────────────────────────────

export type EchelleNiveau = {
    label: string;
    points: number;
    description: string | null;
};

export type Critere = {
    id: number;
    type: 'positif' | 'negatif';
    contenu_type: 'texte' | 'echelle';
    pointage: number;
    contenu: string | null;
    echelle: EchelleNiveau[] | null;
    visible: boolean;
    ordre: number;
};

export type CorrectionLocale = {
    id: number;
    user_id: number | null;
    points: number | null;
    commentaire: string | null;
    verifie: boolean;
    source_id: number | null;
};

type Membre = {
    id: number;
    prenom: string;
    nom: string;
};

// ─── Props / Emits ────────────────────────────────────────────────────────────

const props = defineProps<{
    coursId: number;
    classeId: number;
    groupeId: number;
    typeProjetId: number;
    critere: Critere;
    corrections: CorrectionLocale[];
    membres: Membre[];
}>();

const emit = defineEmits<{
    /** Émis après chaque modification persistée ; contient la liste complète des corrections pour ce critère. */
    updated: [corrections: CorrectionLocale[]];
}>();

// ─── État local ───────────────────────────────────────────────────────────────

/** Copie réactive des corrections pour ce critère. */
const correctionsLocales = ref<CorrectionLocale[]>([...props.corrections]);

watch(
    () => props.corrections,
    (v) => {
        correctionsLocales.value = [...v];

        // Synchronise les brouillons si pas en train de saisir
        for (const c of v) {
            const key = c.user_id === null ? 'groupe' : String(c.user_id);

            if (!(key in saving) || !saving[key]) {
                pointsDraft[key] = c.points?.toString() ?? '';
                commentaireDraft[key] = c.commentaire ?? '';
            }
        }
    },
    { deep: true },
);

/** Brouillons des points saisis, non encore persistés. Clé = 'groupe' | userId. */
const pointsDraft = reactive<Record<string, string>>({});

/** Brouillons des commentaires. */
const commentaireDraft = reactive<Record<string, string>>({});

/** Visibilité des zones de commentaire. */
const showCommentaire = reactive<Record<string, boolean>>({});

/** Indicateurs de sauvegarde en cours. */
const saving = reactive<Record<string, boolean>>({});

/** Affiche ou masque la section des valeurs différentes par étudiant. */
const showOverrides = ref(false);

// Initialiser les brouillons depuis les corrections existantes
for (const c of props.corrections) {
    const key = c.user_id === null ? 'groupe' : String(c.user_id);
    pointsDraft[key] = c.points?.toString() ?? '';
    commentaireDraft[key] = c.commentaire ?? '';
}

// ─── Computed ────────────────────────────────────────────────────────────────

const correctionGroupe = computed<CorrectionLocale | null>(
    () => correctionsLocales.value.find((c) => c.user_id === null) ?? null,
);

/**
 * Retrouve la correction individuelle d'un membre (override du groupe).
 */
function correctionPourMembre(userId: number): CorrectionLocale | null {
    return correctionsLocales.value.find((c) => c.user_id === userId) ?? null;
}

/**
 * Indique si au moins un membre a une correction individuelle (override).
 * Utilisé pour avertir l'enseignant que modifier la note groupe réinitialisera ces overrides.
 */
const hasOverrides = computed(() =>
    correctionsLocales.value.some((c) => c.user_id !== null),
);

const routeArgs = computed(() => ({
    cours: props.coursId,
    classe: props.classeId,
    groupe: props.groupeId,
    typeProjet: props.typeProjetId,
}));

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Met à jour la liste locale et émet l'événement `updated`.
 *
 * @param clearedUserIds  IDs des membres dont l'override individuel a été supprimé
 *                        côté serveur (suite à une correction groupe).
 */
function appliquerMaj(updated: CorrectionLocale, clearedUserIds: number[] = []) {
    // Purger les overrides individuels supprimés par le serveur
    if (clearedUserIds.length > 0) {
        correctionsLocales.value = correctionsLocales.value.filter(
            (c) => c.user_id === null || !clearedUserIds.includes(c.user_id),
        );
        for (const uid of clearedUserIds) {
            const key = String(uid);
            delete pointsDraft[key];
            delete commentaireDraft[key];
            delete showCommentaire[key];
        }
    }

    const idx = correctionsLocales.value.findIndex(
        (c) => c.user_id === updated.user_id,
    );

    if (idx >= 0) {
        correctionsLocales.value[idx] = updated;
    } else {
        correctionsLocales.value.push(updated);
    }

    // Synchronise les brouillons avec la valeur persistée
    const key = updated.user_id === null ? 'groupe' : String(updated.user_id);
    pointsDraft[key] = updated.points?.toString() ?? '';
    commentaireDraft[key] = updated.commentaire ?? '';
    emit('updated', [...correctionsLocales.value]);
}

// ─── Sauvegarde : points saisie manuelle ─────────────────────────────────────

/**
 * Sauvegarde la correction pour un utilisateur (groupe ou membre).
 * Appelé sur blur ou Enter du champ points.
 */
async function sauvegarderPoints(userId: number | null) {
    const key = userId === null ? 'groupe' : String(userId);
    const pts = parseFloat(pointsDraft[key] ?? '');
    const existing =
        userId === null ? correctionGroupe.value : correctionPourMembre(userId);

    const payload = {
        user_id: userId,
        points: isNaN(pts) ? null : pts,
        // La saisie manuelle des points annule le flag "vérifié"
        verifie: false,
        commentaire: existing?.commentaire ?? null,
    };

    saving[key] = true;

    try {
        const { data } = await axios.put(
            upsertCritereCorrection.url({
                ...routeArgs.value,
                critere: props.critere.id,
            }),
            payload,
        );
        appliquerMaj(data.correction as CorrectionLocale, data.cleared_user_ids ?? []);
    } finally {
        saving[key] = false;
    }
}

// ─── Sauvegarde : commentaire ─────────────────────────────────────────────────

/**
 * Sauvegarde le commentaire pour un utilisateur.
 */
async function sauvegarderCommentaire(userId: number | null) {
    const key = userId === null ? 'groupe' : String(userId);
    const existing =
        userId === null ? correctionGroupe.value : correctionPourMembre(userId);

    const pts = parseFloat(pointsDraft[key] ?? '');
    const payload = {
        user_id: userId,
        points: isNaN(pts) ? null : pts,
        verifie: existing?.verifie ?? false,
        commentaire: commentaireDraft[key] || null,
    };

    saving[key] = true;

    try {
        const { data } = await axios.put(
            upsertCritereCorrection.url({
                ...routeArgs.value,
                critere: props.critere.id,
            }),
            payload,
        );
        appliquerMaj(data.correction as CorrectionLocale, data.cleared_user_ids ?? []);
    } finally {
        saving[key] = false;
    }
}

// ─── Crochet vert (positif) ───────────────────────────────────────────────────

/**
 * Bascule le flag "vérifié" de la correction groupe :
 *  - vrai  → donne tous les points automatiquement
 *  - faux  → conserve les points actuels
 */
async function toggleVerifie() {
    const current = correctionGroupe.value;
    const newVerifie = !(current?.verifie ?? false);
    const pts = newVerifie ? props.critere.pointage : (current?.points ?? null);

    saving['groupe'] = true;

    try {
        const { data } = await axios.put(
            upsertCritereCorrection.url({
                ...routeArgs.value,
                critere: props.critere.id,
            }),
            {
                user_id: null,
                points: pts,
                verifie: newVerifie,
                commentaire: current?.commentaire ?? null,
            },
        );
        appliquerMaj(data.correction as CorrectionLocale, data.cleared_user_ids ?? []);
        pointsDraft['groupe'] = pts?.toString() ?? '';
    } finally {
        saving['groupe'] = false;
    }
}

// ─── Échelle ──────────────────────────────────────────────────────────────────

/**
 * Sélectionne un niveau de l'échelle et sauvegarde immédiatement.
 */
async function choisirNiveauEchelle(niveauPts: number) {
    const current = correctionGroupe.value;
    saving['groupe'] = true;

    try {
        const { data } = await axios.put(
            upsertCritereCorrection.url({
                ...routeArgs.value,
                critere: props.critere.id,
            }),
            {
                user_id: null,
                points: niveauPts,
                verifie: niveauPts === props.critere.pointage,
                commentaire: current?.commentaire ?? null,
            },
        );
        appliquerMaj(data.correction as CorrectionLocale, data.cleared_user_ids ?? []);
        pointsDraft['groupe'] = niveauPts.toString();
    } finally {
        saving['groupe'] = false;
    }
}

// ─── Fork (override par étudiant) ────────────────────────────────────────────

/**
 * Crée une correction individuelle pour un étudiant,
 * clonée depuis la correction de groupe.
 */
async function forkerPourMembre(userId: number) {
    const groupeCorr = correctionGroupe.value;

    if (!groupeCorr) {
        return;
    }

    saving[String(userId)] = true;

    try {
        const { data } = await axios.post(
            clonerCritereCorrection.url({
                ...routeArgs.value,
                correction: groupeCorr.id,
            }),
            {
                user_id: userId,
                points: groupeCorr.points,
                verifie: groupeCorr.verifie,
                commentaire: groupeCorr.commentaire,
            },
        );
        const clone = data.correction as CorrectionLocale;
        const idx = correctionsLocales.value.findIndex(
            (c) => c.user_id === userId,
        );

        if (idx >= 0) {
            correctionsLocales.value[idx] = clone;
        } else {
            correctionsLocales.value.push(clone);
        }

        const key = String(userId);
        pointsDraft[key] = clone.points?.toString() ?? '';
        commentaireDraft[key] = clone.commentaire ?? '';
        emit('updated', [...correctionsLocales.value]);
    } finally {
        saving[String(userId)] = false;
    }
}

// ─── Suppression ─────────────────────────────────────────────────────────────

/**
 * Supprime une correction (et ses éventuels clones côté backend).
 */
async function supprimerCorrection(
    correctionId: number,
    userId: number | null,
) {
    try {
        await axios.delete(
            destroyCritereCorrection.url({
                ...routeArgs.value,
                correction: correctionId,
            }),
        );
        // Retirer la correction et ses clones de la liste locale
        correctionsLocales.value = correctionsLocales.value.filter(
            (c) => c.id !== correctionId && c.source_id !== correctionId,
        );
        const key = userId === null ? 'groupe' : String(userId);
        delete pointsDraft[key];
        delete commentaireDraft[key];
        delete showCommentaire[key];
        emit('updated', [...correctionsLocales.value]);
    } catch {}
}
</script>

<template>
    <div
        class="space-y-1.5 border-l-2 py-1.5 pl-3"
        :class="
            critere.type === 'positif'
                ? 'border-emerald-400'
                : 'border-rose-400'
        "
    >
        <!-- ─── En-tête du critère ─────────────────────────────────────── -->
        <div class="flex items-start gap-2">
            <span
                class="shrink-0 rounded px-1 py-0.5 text-[10px] font-semibold"
                :class="
                    critere.type === 'positif'
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                        : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'
                "
            >
                {{ critere.type === 'positif' ? '+' : '−'
                }}{{ critere.pointage }}
            </span>
            <span v-if="critere.contenu" class="flex-1 text-xs leading-snug">{{
                critere.contenu
            }}</span>
            <span v-else class="flex-1 text-xs text-muted-foreground italic"
                >Sans description</span
            >
        </div>

        <!-- ─── Niveaux d'échelle (sélectionnables) ───────────────────── -->
        <div
            v-if="critere.contenu_type === 'echelle' && critere.echelle"
            class="ml-5 flex flex-wrap gap-1"
        >
            <button
                v-for="niveau in critere.echelle"
                :key="niveau.label"
                type="button"
                :title="niveau.description ?? undefined"
                :class="[
                    'rounded border px-2 py-0.5 text-[10px] transition-colors',
                    correctionGroupe?.points === niveau.points
                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'border-border text-muted-foreground hover:border-muted-foreground/40',
                ]"
                @click="choisirNiveauEchelle(niveau.points)"
            >
                {{ niveau.label }} ({{ niveau.points }})
            </button>
        </div>

        <!-- ─── Ligne de correction Groupe ───────────────────────────── -->
        <div class="ml-2 flex items-center gap-1.5">
            <span class="shrink-0 text-[10px] text-muted-foreground"
                >Groupe&nbsp;:</span
            >

            <!-- Crochet vert (positif uniquement) -->
            <button
                v-if="critere.type === 'positif'"
                type="button"
                :class="[
                    'shrink-0 rounded border p-0.5 transition-colors',
                    correctionGroupe?.verifie
                        ? 'border-emerald-500 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                        : 'border-border text-muted-foreground hover:border-emerald-400 hover:text-emerald-600',
                ]"
                title="Crochet vert — donne tous les points"
                @click="toggleVerifie"
            >
                <CheckCheck class="h-3 w-3" />
            </button>

            <!-- Champ points -->
            <Input
                v-model="pointsDraft['groupe']"
                type="number"
                min="0"
                :max="critere.pointage"
                step="0.25"
                class="h-6 w-20 text-xs"
                :placeholder="`/${critere.pointage}`"
                @blur="sauvegarderPoints(null)"
                @keydown.enter.prevent="sauvegarderPoints(null)"
            />

            <!-- Toggle commentaire -->
            <button
                type="button"
                :class="[
                    'shrink-0 transition-colors',
                    showCommentaire['groupe'] || correctionGroupe?.commentaire
                        ? 'text-blue-500'
                        : 'text-muted-foreground hover:text-blue-400',
                ]"
                title="Commentaire"
                @click="showCommentaire['groupe'] = !showCommentaire['groupe']"
            >
                <MessageSquare class="h-3.5 w-3.5" />
            </button>

            <!-- Avertissement overrides individuels existants -->
            <span
                v-if="hasOverrides"
                class="shrink-0 text-amber-500"
                title="Des notes individuelles existent — elles seront réinitialisées si vous modifiez la note groupe"
            >
                <TriangleAlert class="h-3 w-3" />
            </span>

            <!-- Toggle valeurs différentes par étudiant -->
            <button
                v-if="correctionGroupe && membres.length > 0"
                type="button"
                :class="[
                    'shrink-0 transition-colors',
                    showOverrides
                        ? 'text-foreground'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
                :title="
                    showOverrides
                        ? 'Masquer les valeurs individuelles'
                        : 'Valeurs différentes par étudiant'
                "
                @click="showOverrides = !showOverrides"
            >
                <GitFork class="h-3 w-3" />
            </button>

            <!-- Supprimer correction groupe -->
            <button
                v-if="correctionGroupe"
                type="button"
                class="shrink-0 text-muted-foreground hover:text-destructive"
                title="Supprimer cette correction"
                @click="supprimerCorrection(correctionGroupe.id, null)"
            >
                <Trash2 class="h-3 w-3" />
            </button>

            <!-- Indicateur de sauvegarde -->
            <Loader2
                v-if="saving['groupe']"
                class="h-3 w-3 shrink-0 animate-spin text-muted-foreground"
            />
        </div>

        <!-- ─── Commentaire groupe (indenté) ──────────────────────────── -->
        <div v-if="showCommentaire['groupe']" class="ml-10">
            <Textarea
                v-model="commentaireDraft['groupe']"
                rows="2"
                class="text-xs"
                placeholder="Commentaire…"
                @blur="sauvegarderCommentaire(null)"
            />
        </div>

        <!-- ─── Overrides par étudiant (si correction groupe existe) ──── -->
        <template
            v-if="correctionGroupe && membres.length > 0 && showOverrides"
        >
            <div
                v-for="membre in membres"
                :key="membre.id"
                class="ml-2 space-y-1"
            >
                <div class="flex items-center gap-1.5">
                    <!-- Nom court du membre -->
                    <span
                        class="w-24 shrink-0 truncate text-[10px] text-muted-foreground"
                    >
                        {{ membre.prenom }}
                        {{ membre.nom.charAt(0) }}.
                    </span>

                    <!-- Pas d'override : bouton fork -->
                    <template v-if="!correctionPourMembre(membre.id)">
                        <button
                            type="button"
                            class="flex items-center gap-0.5 text-[10px] text-muted-foreground hover:text-foreground"
                            :title="`Valeur différente pour ${membre.prenom}`"
                            @click="forkerPourMembre(membre.id)"
                        >
                            <GitFork class="h-3 w-3" />
                        </button>
                        <Loader2
                            v-if="saving[String(membre.id)]"
                            class="h-3 w-3 animate-spin text-muted-foreground"
                        />
                    </template>

                    <!-- Override existant -->
                    <template v-else>
                        <Input
                            v-model="pointsDraft[String(membre.id)]"
                            type="number"
                            min="0"
                            :max="critere.pointage"
                            step="0.25"
                            class="h-6 w-20 text-xs"
                            @blur="sauvegarderPoints(membre.id)"
                            @keydown.enter.prevent="
                                sauvegarderPoints(membre.id)
                            "
                        />
                        <button
                            type="button"
                            :class="[
                                'shrink-0 transition-colors',
                                showCommentaire[String(membre.id)] ||
                                correctionPourMembre(membre.id)?.commentaire
                                    ? 'text-blue-500'
                                    : 'text-muted-foreground hover:text-blue-400',
                            ]"
                            title="Commentaire"
                            @click="
                                showCommentaire[String(membre.id)] =
                                    !showCommentaire[String(membre.id)]
                            "
                        >
                            <MessageSquare class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="shrink-0 text-muted-foreground hover:text-destructive"
                            :title="`Retirer l'override pour ${membre.prenom}`"
                            @click="
                                supprimerCorrection(
                                    correctionPourMembre(membre.id)!.id,
                                    membre.id,
                                )
                            "
                        >
                            <Trash2 class="h-3 w-3" />
                        </button>
                        <Loader2
                            v-if="saving[String(membre.id)]"
                            class="h-3 w-3 shrink-0 animate-spin text-muted-foreground"
                        />
                    </template>
                </div>

                <!-- Commentaire override membre (indenté) -->
                <div
                    v-if="
                        showCommentaire[String(membre.id)] &&
                        correctionPourMembre(membre.id)
                    "
                    class="ml-[6.5rem]"
                >
                    <Textarea
                        v-model="commentaireDraft[String(membre.id)]"
                        rows="2"
                        class="text-xs"
                        placeholder="Commentaire…"
                        @blur="sauvegarderCommentaire(membre.id)"
                    />
                </div>
            </div>
        </template>
    </div>
</template>
