<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, Eye, FileBarChart, FileText } from 'lucide-vue-next';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';

defineProps<{
    baseUrl: string;
    estEnseignant: boolean;
}>();
</script>

<template>
    <BoutonTooltip
        texte="Voir le rendu final du projet"
        variant="ghost"
        size="sm"
        as-child
    >
        <Link :href="`${baseUrl}/apercu`">
            <Eye class="h-4 w-4" />
            Aperçu
        </Link>
    </BoutonTooltip>
    <BoutonTooltip
        texte="Télécharger une version PDF (s'ouvre dans un nouvel onglet)"
        variant="ghost"
        size="sm"
        as-child
    >
        <a :href="`${baseUrl}/pdf`" target="_blank">
            <FileText class="h-4 w-4" />
            PDF
        </a>
    </BoutonTooltip>
    <BoutonTooltip
        texte="Télécharger une version Word (.docx)"
        variant="ghost"
        size="sm"
        as-child
    >
        <a :href="`${baseUrl}/word`">
            <Download class="h-4 w-4" />
            Word
        </a>
    </BoutonTooltip>
    <BoutonTooltip
        v-if="estEnseignant"
        texte="Voir les notes de tous les étudiants du groupe"
        variant="ghost"
        size="sm"
        as-child
    >
        <Link :href="`${baseUrl}/apercu-notes`">
            <FileBarChart class="h-4 w-4" />
            Notes
        </Link>
    </BoutonTooltip>
</template>
