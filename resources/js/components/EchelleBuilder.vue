<script setup lang="ts">
import { GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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
 * Somme actuelle des points de tous les niveaux.
 */
const totalPoints = computed(() =>
    props.modelValue.reduce((acc, n) => acc + (n.points || 0), 0),
);

/**
 * Vrai si le total ne correspond pas au pointage du critère.
 */
const totalIncorrect = computed(
    () => Math.abs(totalPoints.value - props.pointageTotal) > 0.001,
);

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
 * Distribue le pointage total équitablement entre tous les niveaux.
 */
function diviserAuto() {
    const n = props.modelValue.length;

    if (n === 0) {
        return;
    }

    const pts = Math.round((props.pointageTotal / n) * 100) / 100;
    emit(
        'update:modelValue',
        props.modelValue.map((niv) => ({ ...niv, points: pts })),
    );
}

/**
 * Met à jour un champ d'un niveau spécifique.
 */
function updateNiveau(
    idx: number,
    champ: keyof EchelleNiveau,
    val: string | number | null,
) {
    const copy = props.modelValue.map((n, i) =>
        i === idx ? { ...n, [champ]: val } : n,
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
            class="space-y-1"
        >
            <div
                v-for="(niveau, idx) in niveaux"
                :key="idx"
                class="flex items-center gap-1.5"
            >
                <GripVertical
                    class="echelle-drag h-4 w-4 shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing"
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

                <!-- Points -->
                <Input
                    :model-value="niveau.points"
                    type="number"
                    step="0.25"
                    min="0"
                    class="w-20 text-sm"
                    :placeholder="t('criteres.echelle_points')"
                    @update:model-value="
                        updateNiveau(idx, 'points', Number($event))
                    "
                />

                <!-- Description optionnelle -->
                <Input
                    :model-value="niveau.description ?? ''"
                    :placeholder="t('criteres.echelle_description_placeholder')"
                    class="min-w-0 flex-1 text-sm"
                    @update:model-value="
                        updateNiveau(idx, 'description', $event || null)
                    "
                />

                <!-- Supprimer -->
                <button
                    type="button"
                    class="shrink-0 text-muted-foreground hover:text-destructive"
                    @click="supprimerNiveau(idx)"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
            </div>
        </VueDraggable>

        <!-- Total + boutons d'action -->
        <div class="flex items-center justify-between gap-2 pt-1">
            <p
                :class="[
                    'text-xs',
                    totalIncorrect
                        ? 'font-medium text-destructive'
                        : 'text-muted-foreground',
                ]"
            >
                {{ totalPoints }} / {{ pointageTotal }}
                <span v-if="totalIncorrect">
                    — {{ t('criteres.echelle_total_incorrect') }}</span
                >
            </p>

            <div class="flex gap-1.5">
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
    </div>
</template>
