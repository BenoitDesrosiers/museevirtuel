<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { LockKeyhole } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import * as EtudiantController from '@/actions/App/Http/Controllers/EtudiantController';
import * as EnseignantController from '@/actions/App/Http/Controllers/EnseignantController';

type Props = {
    cours: {
        id: number;
        nom_cours: string;
        code: string;
    };
};

defineProps<Props>();

const page = usePage();

/**
 * Redirige vers la page d'accueil correspondant au rôle de l'utilisateur.
 * Un enseignant sans accès au cours retourne vers son tableau de bord, pas vers /etudiant.
 */
const homeUrl = computed(() =>
    page.props.auth.user.role === 'etudiant'
        ? EtudiantController.index.url()
        : EnseignantController.index.url(),
);
</script>

<template>
    <AppLayout>
        <Head :title="$t('cours.verrouille.titre')" />

        <div class="flex min-h-[60vh] flex-col items-center justify-center gap-6 p-6 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                <LockKeyhole class="h-8 w-8 text-muted-foreground" />
            </div>

            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ $t('cours.verrouille.titre') }}
                </h1>
                <p class="max-w-sm text-sm text-muted-foreground">
                    {{ $t('cours.verrouille.description', { cours: `${cours.code} — ${cours.nom_cours}` }) }}
                </p>
            </div>

            <Button as-child>
                <Link :href="homeUrl">
                    {{ $t('cours.verrouille.retour') }}
                </Link>
            </Button>
        </div>
    </AppLayout>
</template>
