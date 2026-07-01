<script setup lang="ts">
import { GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type EchelleNiveau = {
    label: string;
    points: number;
    description: string | null;
};

const props = defineProps<{
    modelValue: EchelleNiveau[];
    pointageTotal: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: EchelleNiveau[]];
}>();

const { t } = useI18n();

/**
 * Proxy réactif pour VueDraggable — émet immédiatement sur set.
 */
const niveaux = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

/**
 * Calcule le pourcentage affiché pour un niveau donné.
 * Retourne 0 si pointageTotal vaut 0 pour éviter une division par zéro.
 */
function pourcentageDuNiveau(idx: number): number {
    if (props.pointageTotal === 0) return 0;
    return Math.round((props.modelValue[idx].points / props.pointageTotal) * 10000) / 100;
}

/**
 * Convertit un pourcentage saisi en points et met à jour le niveau.
 * Le pourcentage est borné entre 0 et 100.
 */
function updatePourcentage(idx: number, pct: string | number) {
    const pctNum = Math.min(100, Math.max(0, Number(pct)));
    const pts = Math.round((pctNum / 100) * props.pointageTotal * 100) / 100;
    updateNiveau(idx, 'points', pts);
}

/**
 * Ajoute un niveau vide à la fin.
 */
function ajouterNiveau() {
    emit('update:modelValue', [
        ...props.modelValue,
        { label: '', points: 0, description: null },
    ]);
}

/**
 * Supprime le niveau à l'index donné.
 */
function supprimerNiveau(idx: number) {
    const copy = [...props.modelValue];
    copy.splice(idx, 1);
    emit('update:modelValue', copy);
}

/**
 * Distribue le pointage selon la séquence N/N, (N-1)/N, ..., 2/N, 0/N.
 * Le palier 1/N est intentionnellement sauté (pas de note passable minimale).
 */
function diviserAuto() {
    const n = props.modelValue.length;

    if (n === 0) {
        return;
    }

    emit(
        'update:modelValue',
        props.modelValue.map((niv, i) => {
            const rang = n - i; // N, N-1, ..., 1
            const multiplicateur = rang === 1 ? 0 : rang; // saute 1/N → 0
            const pts = Math.round((multiplicateur / n) * props.pointageTotal * 100) / 100;
            return { ...niv, points: pts };
        }),
    );
}

/**
 * Met à jour un champ d'un niveau spécifique.
 * Lorsque le champ est « points », la valeur est bornée entre 0 et pointageTotal.
 */
function updateNiveau(
    idx: number,
    champ: keyof EchelleNiveau,
    val: string | number | null,
) {
    let valeur: string | number | null = val;

    if (champ === 'points') {
        valeur = Math.round(Math.min(props.pointageTotal, Math.max(0, Number(val))) * 100) / 100;
    }

    const copy = props.modelValue.map((n, i) =>
        i === idx ? { ...n, [champ]: valeur } : n,
    );
    emit('update:modelValue', copy);
}
</script>

<template>
    <div class="space-y-2">
        <!-- Lignes draggables -->
        <VueDraggable
            v-model="niveaux"
            handle=".echelle-drag"
            :animation="150"
            class="space-y-2"
        >
            <div
                v-for="(niveau, idx) in niveaux"
                :key="idx"
                class="flex items-start gap-1.5"
            >
                <GripVertical
                    class="echelle-drag mt-2 h-4 w-4 shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing"
                />

                <!-- Label du niveau -->
                <Input
                    :model-value="niveau.label"
                    :placeholder="t('criteres.echelle_label_placeholder')"
                    class="min-w-0 flex-1 text-sm"
                    @update:model-value="
                        updateNiveau(idx, 'label', String($event))
                    "
                />

                <!-- Pourcentage lié au pointage -->
                <div class="flex items-center gap-0.5">
                    <Input
                        :model-value="pourcentageDuNiveau(idx)"
                        type="number"
                        step="1"
                        min="0"
                        max="100"
                        class="w-16 text-sm"
                        placeholder="%"
                        @update:model-value="updatePourcentage(idx, $event)"
                    />
                    <span class="text-xs text-muted-foreground">%</span>
                </div>

                <!-- Points -->
                <Input
                    :model-value="niveau.points"
                    type="number"
                    step="0.25"
                    min="0"
                    :max="pointageTotal"
                    class="w-20 text-sm"
                    :placeholder="t('criteres.echelle_points')"
                    @update:model-value="
                        updateNiveau(idx, 'points', Number($event))
                    "
                />

                <!-- Description (textarea multi-ligne) -->
                <Textarea
                    :model-value="niveau.description ?? ''"
                    :placeholder="t('criteres.echelle_description_placeholder')"
                    class="min-w-0 flex-1 text-sm"
                    rows="2"
                    @update:model-value="
                        updateNiveau(idx, 'description', $event || null)
                    "
                />

                <!-- Supprimer -->
                <button
                    type="button"
                    class="mt-2 shrink-0 text-muted-foreground hover:text-destructive"
                    @click="supprimerNiveau(idx)"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
            </div>
        </VueDraggable>

        <!-- Boutons d'action -->
        <div class="flex justify-end gap-1.5 pt-1">
            <Button
                type="button"
                size="sm"
                variant="outline"
                class="text-xs"
                @click="diviserAuto"
            >
                {{ t('criteres.echelle_diviser') }}
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                class="text-xs"
                @click="ajouterNiveau"
            >
                <Plus class="mr-1 h-3 w-3" />
                {{ t('criteres.echelle_ajouter') }}
            </Button>
        </div>
    </div>
</template>
