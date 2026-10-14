<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageSquare, Settings2, SpellCheck } from 'lucide-vue-next';
import ConsentementVideo from '@/components/projets/ConsentementVideo.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { edit as editTypeProjet } from '@/routes/types-projets';

defineProps<{
    peutEditer: boolean;
    verrouille: boolean;
    hasVideoOrAudioSection: boolean;
    estEnseignant: boolean;
    consentement: {
        accepte: boolean;
        signed_at: string | null;
    } | null;
    coursId: number;
    groupeId: number;
    typeProjetId: number;
    champsVisiblesCount: number;
    tousCommentairesReduits: boolean;
}>();

const emit = defineEmits<{
    antidote: [];
    toggleCommentaires: [];
}>();
</script>

<template>
    <BoutonTooltip
        v-if="peutEditer && !verrouille"
        texte="Lancer la correction orthographique globale avec Antidote"
        variant="ghost"
        size="sm"
        class="text-green-700 hover:bg-green-50 hover:text-green-700"
        @click="emit('antidote')"
    >
        <SpellCheck class="h-4 w-4" />
        Antidote
    </BoutonTooltip>
    <ConsentementVideo
        v-if="hasVideoOrAudioSection && !estEnseignant"
        :params="{
            cours: coursId,
            groupe: groupeId,
            typeProjet: typeProjetId,
        }"
        :consentement="consentement"
    />
    <BoutonTooltip
        v-if="estEnseignant"
        texte="Gérer les sections disponibles pour ce type de projet"
        variant="ghost"
        size="sm"
        as-child
    >
        <Link
            :href="
                editTypeProjet.url({
                    cours: coursId,
                    typeProjet: typeProjetId,
                })
            "
        >
            <Settings2 class="h-4 w-4" />
            Sections
        </Link>
    </BoutonTooltip>
    <BoutonTooltip
        v-if="champsVisiblesCount > 0"
        :texte="
            tousCommentairesReduits
                ? 'Développer tous les commentaires enseignant'
                : 'Réduire tous les commentaires enseignant'
        "
        variant="ghost"
        size="sm"
        @click="emit('toggleCommentaires')"
    >
        <MessageSquare class="h-4 w-4" />
        Commentaires
    </BoutonTooltip>
</template>
