<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import questionsRoutes from '@/routes/projets/sections/questions';

type RouteParams = {
    cours: number;
    classe: number;
    groupe: number;
    typeProjet: number;
    section: number;
};

type Question = {
    id: number;
    contenu: string;
    ordre: number;
};

const props = defineProps<{
    params: RouteParams;
    questions: Question[];
    questionsChoisies: number[];
    readonly?: boolean;
}>();

const form = useForm({
    question_ids: [...props.questionsChoisies],
});

/**
 * Bascule la sélection d'une question dans le formulaire.
 */
function toggleQuestion(id: number): void {
    const idx = form.question_ids.indexOf(id);

    if (idx === -1) {
        form.question_ids.push(id);
    } else {
        form.question_ids.splice(idx, 1);
    }
}

/**
 * Enregistre les questions choisies via la route choisir.
 */
function sauvegarder(): void {
    form.post(questionsRoutes.choisir.url(props.params), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-3">
        <!-- Liste vide -->
        <p
            v-if="questions.length === 0"
            class="text-sm text-muted-foreground italic"
        >
            Aucune question disponible dans cette section.
        </p>

        <!-- Liste des questions -->
        <div
            v-for="question in questions"
            :key="question.id"
            :class="[
                'flex items-start gap-3 rounded-md border px-4 py-3 transition-colors',
                form.question_ids.includes(question.id)
                    ? 'border-primary bg-primary/5'
                    : 'bg-card hover:bg-muted/50',
            ]"
        >
            <input
                :id="`q-${question.id}`"
                type="checkbox"
                :value="question.id"
                :checked="form.question_ids.includes(question.id)"
                :disabled="readonly"
                class="mt-0.5 shrink-0 accent-primary"
                @change="toggleQuestion(question.id)"
            />
            <label
                :for="`q-${question.id}`"
                :class="[
                    'cursor-pointer text-sm leading-relaxed',
                    readonly ? 'cursor-default' : '',
                ]"
            >
                {{ question.contenu }}
            </label>
        </div>

        <!-- Compteur + bouton Enregistrer -->
        <div
            v-if="!readonly && questions.length > 0"
            class="flex items-center justify-between pt-1"
        >
            <span class="text-xs text-muted-foreground">
                {{ form.question_ids.length }} question(s) sélectionnée(s)
            </span>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                :disabled="form.processing"
                @click="sauvegarder"
            >
                <Save class="h-3.5 w-3.5" />
                Enregistrer
            </button>
        </div>

        <!-- Mode lecture : liste des questions choisies -->
        <div
            v-if="readonly && questionsChoisies.length === 0"
            class="text-sm text-muted-foreground italic"
        >
            Aucune question sélectionnée.
        </div>
    </div>
</template>
