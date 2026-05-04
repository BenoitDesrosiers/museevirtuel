<script setup lang="ts">
import { Check, MinusCircle, PlusCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

// ─── Types ────────────────────────────────────────────────────────────────────

type GrilleMalusPersonnalisee = {
    id: number;
    label: string;
    deduction: number;
    description: string | null;
    ordre: number;
};

type Membre = {
    id: number;
    prenom: string;
    nom: string;
};

// ─── Props / Emits ────────────────────────────────────────────────────────────

const props = defineProps<{
    open: boolean;
    malus: GrilleMalusPersonnalisee[];
    membres: Membre[];
    /** malusGrille[userId][malusId] = applique */
    malusGrille: Record<number, Record<number, boolean>>;
    saving: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    /** Basculer le malus pour un étudiant précis */
    'toggle': [malusId: number, membreId: number, applique: boolean];
    /** Basculer le malus pour tous les étudiants du groupe */
    'toggle-pour-tous': [malusId: number, applique: boolean];
}>();

// ─── État local ───────────────────────────────────────────────────────────────

const selectedMalusId = ref<number | null>(null);
const selectedTarget = ref<number | 'tous'>('tous');

/** Réinitialise les sélections à l'ouverture du modal. */
watch(
    () => props.open,
    (ouvert) => {
        if (ouvert) {
            selectedMalusId.value = props.malus[0]?.id ?? null;
            selectedTarget.value = 'tous';
        }
    },
);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function isMalusApplique(malusId: number, membreId: number): boolean {
    return !!(props.malusGrille[membreId]?.[malusId]);
}

/**
 * Retourne true si le malus sélectionné est appliqué à tous les membres,
 * false si aucun, null si état mixte.
 */
const etatTous = computed((): boolean | null => {
    if (!selectedMalusId.value || props.membres.length === 0) return null;
    const etats = props.membres.map((m) => isMalusApplique(selectedMalusId.value!, m.id));
    if (etats.every(Boolean)) return true;
    if (etats.every((e) => !e)) return false;
    return null;
});

/** true/false selon la cible sélectionnée, null si état mixte (tous + partiel). */
const etatCible = computed((): boolean | null => {
    if (!selectedMalusId.value) return null;
    if (selectedTarget.value === 'tous') return etatTous.value;
    return isMalusApplique(selectedMalusId.value, selectedTarget.value as number);
});

/** L'action Appliquer est disponible quand la cible n'a pas encore le malus (ou partiellement). */
const peutAppliquer = computed(() => {
    if (!selectedMalusId.value || props.saving) return false;
    return etatCible.value !== true;
});

/** L'action Retirer est disponible quand la cible a déjà le malus (ou partiellement). */
const peutRetirer = computed(() => {
    if (!selectedMalusId.value || props.saving) return false;
    return etatCible.value !== false;
});

// ─── Actions ──────────────────────────────────────────────────────────────────

function appliquer(): void {
    if (!selectedMalusId.value) return;
    if (selectedTarget.value === 'tous') {
        emit('toggle-pour-tous', selectedMalusId.value, true);
    } else {
        emit('toggle', selectedMalusId.value, selectedTarget.value as number, true);
    }
}

function retirer(): void {
    if (!selectedMalusId.value) return;
    if (selectedTarget.value === 'tous') {
        emit('toggle-pour-tous', selectedMalusId.value, false);
    } else {
        emit('toggle', selectedMalusId.value, selectedTarget.value as number, false);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Appliquer un malus</DialogTitle>
            </DialogHeader>

            <!-- ── Choix du malus ──────────────────────────────────────────── -->
            <div class="space-y-2">
                <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    Malus
                </Label>
                <div class="space-y-0.5">
                    <label
                        v-for="m in malus"
                        :key="m.id"
                        class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-muted"
                        :class="{ 'bg-muted': selectedMalusId === m.id }"
                    >
                        <input
                            type="radio"
                            :value="m.id"
                            v-model="selectedMalusId"
                            class="mt-0.5 shrink-0"
                        />
                        <span class="flex-1 text-sm leading-snug">
                            {{ m.label }}
                            <span class="ml-1 font-semibold text-destructive">
                                −{{ m.deduction }} pt{{ m.deduction === 1 ? '' : 's' }}
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- ── Choix de la cible ───────────────────────────────────────── -->
            <div class="space-y-2">
                <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    Étudiant
                </Label>
                <div class="space-y-0.5">
                    <!-- Tous les étudiants -->
                    <label
                        class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-muted"
                        :class="{ 'bg-muted': selectedTarget === 'tous' }"
                    >
                        <input type="radio" value="tous" v-model="selectedTarget" class="shrink-0" />
                        <span class="flex-1 text-sm font-medium">Tous les étudiants</span>
                        <span v-if="etatTous === true" class="text-xs font-medium text-green-600">
                            ● Tous appliqués
                        </span>
                        <span v-else-if="etatTous === null && membres.length > 0" class="text-xs font-medium text-amber-600">
                            ⚡ Partiel
                        </span>
                    </label>

                    <!-- Étudiants individuels -->
                    <label
                        v-for="membre in membres"
                        :key="membre.id"
                        class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-muted"
                        :class="{ 'bg-muted': selectedTarget === membre.id }"
                    >
                        <input
                            type="radio"
                            :value="membre.id"
                            v-model="selectedTarget"
                            class="shrink-0"
                        />
                        <span class="flex-1 text-sm">{{ membre.prenom }} {{ membre.nom }}</span>
                        <Check
                            v-if="selectedMalusId && isMalusApplique(selectedMalusId, membre.id)"
                            class="h-3.5 w-3.5 shrink-0 text-green-600"
                        />
                    </label>
                </div>
            </div>

            <!-- ── Boutons d'action ────────────────────────────────────────── -->
            <div class="flex gap-2 pt-1">
                <Button
                    class="flex-1"
                    :disabled="!peutAppliquer"
                    @click="appliquer"
                >
                    <PlusCircle class="mr-1.5 h-4 w-4" />
                    Appliquer
                </Button>
                <Button
                    variant="outline"
                    class="flex-1"
                    :disabled="!peutRetirer"
                    @click="retirer"
                >
                    <MinusCircle class="mr-1.5 h-4 w-4" />
                    Retirer
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
