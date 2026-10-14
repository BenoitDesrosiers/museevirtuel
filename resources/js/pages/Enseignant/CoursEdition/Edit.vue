<script setup lang="ts">
import CoursForm from '@/components/enseignant/CoursForm.vue';

defineProps<{
    cours: {
        id: number;
        nom_cours: string;
        description: string | null;
        code: string;
        groupe: string;
        annee: number;
        session: 'hiver' | 'ete' | 'automne';
        type_cours: 'dep' | 'cours_complementaire' | 'cours_complet' | null;
        taille_equipe_min: number | null;
        taille_equipe_max: number | null;
    };
}>();
</script>

<template><CoursForm :cours="cours" mode="edit" /></template>
