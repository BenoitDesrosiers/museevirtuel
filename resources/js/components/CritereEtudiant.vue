<script setup lang="ts">
import axios from 'axios';
import { CheckSquare, MessageSquare, Square } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toggleCocheCritere } from '@/actions/App/Http/Controllers/ProjetRechercheController';
import type {
    Critere,
    CorrectionLocale,
} from '@/components/CritereCorrection.vue';

const props = defineProps<{
    critere: Critere;
    /** Correction effective pour cet étudiant (override individuel > groupe). null = non attribuée. */
    correction: CorrectionLocale | null;
    /** true = les corrections sont visibles pour les étudiants. */
    correctionVisible: boolean;
    /** true = cet étudiant a coché ce critère (indicateur personnel). */
    estCoche: boolean;
    /** true = l'utilisateur courant est membre du groupe (peut basculer sa coche). */
    peutCocher: boolean;
    routeArgs: {
        cours: number;
        classe: number;
        groupe: number;
        typeProjet: number;
    };
}>();

const emit = defineEmits<{
    /** Émis après le toggle de la coche personnelle. */
    'updated-coche': [estCoche: boolean];
}>();

const { t } = useI18n();
const toggling = ref(false);

/**
 * En mode correction visible, les négatifs ne s'affichent
 * que si une correction a été posée pour cet étudiant.
 */
const visibleEnCorrection = computed(() => {
    if (!props.correctionVisible) return false;
    if (props.critere.type === 'positif') return true;

    return props.correction !== null;
});

/**
 * Points effectifs accordés/déduits pour cet étudiant.
 * Positif sans correction → 0.
 * Négatif sans correction → null (non affiché).
 */
const ptsObtenus = computed<number | null>(() => {
    if (props.critere.type === 'positif') {
        return props.correction?.points ?? 0;
    }

    return props.correction?.points ?? null;
});

/**
 * Bascule la coche personnelle de l'étudiant pour ce critère.
 * Indicateur local sans effet sur la correction officielle.
 */
async function toggleCoche() {
    if (!props.peutCocher || toggling.value) return;

    toggling.value = true;
    try {
        const { data } = await axios.patch(
            toggleCocheCritere.url({
                ...props.routeArgs,
                critere: props.critere.id,
            }),
        );
        emit('updated-coche', data.coche as boolean);
    } finally {
        toggling.value = false;
    }
}
</script>

<template>
    <!-- ─── Mode rédaction (correction non publiée) ──────────────────── -->
    <div
        v-if="!correctionVisible"
        class="border-l-2 py-1.5 pl-3"
        :class="
            critere.type === 'positif'
                ? 'border-emerald-400'
                : 'border-rose-400'
        "
    >
        <div class="flex items-start gap-2">
            <!-- Badge type + pointage -->
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
            <span v-else class="flex-1 text-xs text-muted-foreground italic">{{
                t('criteres.sans_description')
            }}</span>

            <!-- Coche personnelle (indicateur local, sans effet sur la note) -->
            <button
                v-if="peutCocher"
                type="button"
                :class="[
                    'shrink-0 transition-colors',
                    estCoche
                        ? 'text-emerald-500'
                        : 'text-muted-foreground hover:text-emerald-400',
                ]"
                :disabled="toggling"
                :title="t('criteres.cocher_indicateur')"
                @click="toggleCoche"
            >
                <CheckSquare v-if="estCoche" class="h-4 w-4" />
                <Square v-else class="h-4 w-4" />
            </button>
        </div>
    </div>

    <!-- ─── Mode correction visible ──────────────────────────────────── -->
    <div
        v-else-if="visibleEnCorrection"
        class="space-y-1 border-l-2 py-1.5 pl-3"
        :class="
            critere.type === 'positif'
                ? 'border-emerald-400'
                : 'border-rose-400'
        "
    >
        <div class="flex items-start gap-2">
            <!-- Badge pts obtenus / pointage total -->
            <span
                class="shrink-0 rounded px-1 py-0.5 text-[10px] font-semibold"
                :class="
                    critere.type === 'positif'
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                        : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'
                "
            >
                <template v-if="critere.type === 'positif'">
                    {{ ptsObtenus }}/{{ critere.pointage }}
                </template>
                <template v-else> −{{ ptsObtenus }} </template>
            </span>

            <span v-if="critere.contenu" class="flex-1 text-xs leading-snug">{{
                critere.contenu
            }}</span>
            <span v-else class="flex-1 text-xs text-muted-foreground italic">{{
                t('criteres.sans_description')
            }}</span>
        </div>

        <!-- Commentaire de l'enseignant -->
        <p
            v-if="correction?.commentaire"
            class="ml-5 text-xs text-blue-600 dark:text-blue-400"
        >
            <MessageSquare class="mr-0.5 inline h-3 w-3" />
            {{ correction.commentaire }}
        </p>
    </div>
</template>
