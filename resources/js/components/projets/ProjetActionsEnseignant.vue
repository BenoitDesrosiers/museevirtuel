<script setup lang="ts">
import { CheckCircle2, Lock, Send, Settings2 } from 'lucide-vue-next';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    modeEditionEnseignant: boolean;
    correctionVisible: boolean;
    verrouille: boolean;
}>();

const emit = defineEmits<{
    toggleModeEdition: [];
    toggleCorrection: [];
    toggleVerrouille: [];
}>();
</script>

<template>
    <Button
        :variant="modeEditionEnseignant ? 'default' : 'outline'"
        size="sm"
        @click="emit('toggleModeEdition')"
    >
        <Settings2 class="mr-2 h-4 w-4" />
        {{
            modeEditionEnseignant
                ? 'Mode édition actif'
                : 'Activer mode édition'
        }}
    </Button>
    <BoutonTooltip
        :texte="
            correctionVisible
                ? 'Masquer les corrections aux étudiants'
                : 'Publier les corrections pour que les étudiants puissent les consulter'
        "
        :variant="correctionVisible ? 'default' : 'ghost'"
        size="sm"
        @click="emit('toggleCorrection')"
    >
        <CheckCircle2 v-if="correctionVisible" class="h-4 w-4" />
        <Send v-else class="h-4 w-4" />
        Correction
    </BoutonTooltip>
    <BoutonTooltip
        :texte="
            verrouille
                ? 'Déverrouiller le document pour permettre les modifications'
                : 'Verrouiller le document pour empêcher toute modification'
        "
        :variant="verrouille ? 'destructive' : 'ghost'"
        size="sm"
        @click="emit('toggleVerrouille')"
    >
        <Lock class="h-4 w-4" />
        Verrouiller
    </BoutonTooltip>
</template>
