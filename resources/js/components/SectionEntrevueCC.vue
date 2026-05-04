<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { BotOff, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import conceptsRoutes from '@/routes/projets/sections/concepts';

type RouteParams = {
    cours: number;
    classe: number;
    groupe: number;
    typeProjet: number;
    section: number;
};

type Ligne = {
    id: number;
    indicateur: string;
    ordre: number;
};

type Concept = {
    id: number;
    label: string;
    ordre: number;
    lignes: Ligne[];
};

const SOUS_QUESTIONS_MIN = 6;
const SOUS_QUESTIONS_MAX = 8;

const props = defineProps<{
    params: RouteParams;
    concepts: Concept[];
    readonly?: boolean;
}>();

/** La question principale (premier concept de la section). */
const questionPrincipale = computed<Concept | null>(() => props.concepts[0] ?? null);

/** Nombre de sous-questions actuelles. */
const nombreSousQuestions = computed(() => questionPrincipale.value?.lignes.length ?? 0);

/** Peut-on encore ajouter une sous-question ? */
const peutAjouter = computed(
    () => nombreSousQuestions.value < SOUS_QUESTIONS_MAX,
);

// ─── Formulaire — question principale ────────────────────────────────────────

const formQuestion = useForm({
    label: questionPrincipale.value?.label ?? '',
    dimension: '',
    indicateur: '',
});

/**
 * Crée la question principale si elle n'existe pas encore.
 */
function creerQuestionPrincipale(): void {
    if (questionPrincipale.value || props.readonly) return;

    formQuestion.post(conceptsRoutes.store.url(props.params), {
        preserveScroll: true,
        onSuccess: () => {
            formQuestion.reset('label');
        },
    });
}

/**
 * Met à jour le texte de la question principale.
 */
function mettreAJourQuestion(): void {
    if (!questionPrincipale.value || props.readonly) return;

    useForm({ label: formQuestion.label }).patch(
        conceptsRoutes.update.url({ ...props.params, concept: questionPrincipale.value.id }),
        { preserveScroll: true },
    );
}

// ─── Formulaire — sous-questions ──────────────────────────────────────────────

const nouvelleSousQuestion = ref('');

/**
 * Ajoute une sous-question (stockée dans `indicateur`).
 */
function ajouterSousQuestion(): void {
    if (!questionPrincipale.value || props.readonly || !peutAjouter.value) return;
    if (!nouvelleSousQuestion.value.trim()) return;

    useForm({
        dimension: '',
        indicateur: nouvelleSousQuestion.value.trim(),
        questions: '[]',
    }).post(
        conceptsRoutes.lignes.store.url({ ...props.params, concept: questionPrincipale.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                nouvelleSousQuestion.value = '';
            },
        },
    );
}

/**
 * Supprime une sous-question.
 */
function supprimerSousQuestion(ligne: Ligne): void {
    if (!questionPrincipale.value || props.readonly) return;

    useForm({}).delete(
        conceptsRoutes.lignes.destroy.url({
            ...props.params,
            concept: questionPrincipale.value.id,
            ligne: ligne.id,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-5">
        <!-- Section : Question principale -->
        <div class="space-y-1.5">
            <label class="text-sm font-medium text-foreground">
                Question principale
            </label>

            <!-- Mode création -->
            <div v-if="!questionPrincipale && !readonly" class="flex gap-2">
                <input
                    v-model="formQuestion.label"
                    type="text"
                    placeholder="Saisissez la question principale de l'entrevue…"
                    class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    @keydown.enter.prevent="creerQuestionPrincipale"
                />
                <button
                    type="button"
                    class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    :disabled="formQuestion.processing || !formQuestion.label.trim()"
                    @click="creerQuestionPrincipale"
                >
                    Créer
                </button>
            </div>

            <!-- Mode édition -->
            <div v-else-if="questionPrincipale">
                <input
                    v-model="formQuestion.label"
                    type="text"
                    :readonly="readonly"
                    :class="[
                        'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none',
                        !readonly ? 'focus:ring-2 focus:ring-ring' : 'cursor-default text-muted-foreground',
                    ]"
                    @blur="mettreAJourQuestion"
                    @keydown.enter.prevent="mettreAJourQuestion"
                />
            </div>

            <p v-if="!questionPrincipale && readonly" class="text-sm text-muted-foreground italic">
                Aucune question principale définie.
            </p>
        </div>

        <!-- Section : Sous-questions -->
        <div v-if="questionPrincipale" class="space-y-2">
            <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-foreground">
                    Sous-questions
                    <span
                        :class="[
                            'ml-1.5 text-xs font-normal',
                            nombreSousQuestions < SOUS_QUESTIONS_MIN
                                ? 'text-amber-600'
                                : 'text-muted-foreground',
                        ]"
                    >
                        ({{ nombreSousQuestions }}/{{ SOUS_QUESTIONS_MIN }}–{{ SOUS_QUESTIONS_MAX }})
                    </span>
                </label>

                <!-- Bouton IA (désactivé — API non configurée) -->
                <button
                    v-if="!readonly"
                    type="button"
                    disabled
                    class="flex cursor-not-allowed items-center gap-1.5 rounded-md border border-dashed px-2.5 py-1 text-xs text-muted-foreground opacity-50"
                    title="La génération par IA n'est pas disponible (clé API non configurée)"
                >
                    <BotOff class="h-3.5 w-3.5" />
                    Générer avec l'IA
                </button>
            </div>

            <!-- Avertissement minimum -->
            <p
                v-if="nombreSousQuestions < SOUS_QUESTIONS_MIN && !readonly"
                class="text-xs text-amber-600"
            >
                {{ SOUS_QUESTIONS_MIN - nombreSousQuestions }} sous-question(s) manquante(s) pour atteindre le minimum requis.
            </p>

            <!-- Liste des sous-questions -->
            <ol class="space-y-1.5">
                <li
                    v-for="(ligne, index) in questionPrincipale.lignes"
                    :key="ligne.id"
                    class="flex items-start gap-2 rounded-md border bg-card px-3 py-2"
                >
                    <span class="mt-0.5 shrink-0 text-xs text-muted-foreground">{{ index + 1 }}.</span>
                    <p class="flex-1 text-sm">{{ ligne.indicateur }}</p>
                    <button
                        v-if="!readonly"
                        type="button"
                        class="shrink-0 text-muted-foreground hover:text-destructive"
                        title="Supprimer cette sous-question"
                        @click="supprimerSousQuestion(ligne)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </li>
            </ol>

            <!-- Champ ajout nouvelle sous-question -->
            <div v-if="!readonly && peutAjouter" class="flex gap-2 pt-1">
                <input
                    v-model="nouvelleSousQuestion"
                    type="text"
                    placeholder="Ajouter une sous-question…"
                    class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    @keydown.enter.prevent="ajouterSousQuestion"
                />
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    :disabled="!nouvelleSousQuestion.trim()"
                    @click="ajouterSousQuestion"
                >
                    <Plus class="h-4 w-4" />
                    Ajouter
                </button>
            </div>

            <p v-if="!readonly && !peutAjouter" class="text-xs text-muted-foreground">
                Maximum de {{ SOUS_QUESTIONS_MAX }} sous-questions atteint.
            </p>
        </div>
    </div>
</template>
