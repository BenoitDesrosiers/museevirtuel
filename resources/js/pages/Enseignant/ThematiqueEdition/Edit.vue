<script setup lang="ts">
import ThematiqueForm from '@/components/enseignant/ThematiqueForm.vue';

defineProps<{
    thematique: {
        id: number;
        nom: string;
        description: string | null;
        periode_historique: string | null;
    };
}>();
</script>

<template><ThematiqueForm :thematique="thematique" mode="edit" /></template>
