<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Copy } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/shared/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type Ligne = {
    da: string;
    nom: string;
    prenom: string;
    note: number | string | null;
};

type Props = {
    cours: { id: number; nom_cours: string };
    classe: { id: number; numero: string; nom: string | null };
    typeProjet: { id: number; nom: string };
    lignes: Ligne[];
};

const props = defineProps<Props>();

const copie = ref(false);

/** Texte brut formaté : "DA NOTE\n" pour chaque étudiant. */
const texte = computed(() =>
    props.lignes.map((l) => `${l.da} ${l.note ?? ''}`.trimEnd()).join('\n'),
);

const retourUrl = computed(
    () => `/cours/${props.cours.id}/classes/${props.classe.id}`,
);

const titreClasse = computed(
    () => props.classe.nom ?? `Classe ${props.classe.numero}`,
);

async function copierTexte(): Promise<void> {
    await navigator.clipboard.writeText(texte.value);
    copie.value = true;
    setTimeout(() => {
        copie.value = false;
    }, 2000);
}

/**
 * Formate une note pour garder un affichage numérique uniforme dans le tableau.
 */
function formaterNote(note: number | string | null): string {
    if (note === null || note === '') {
        return '—';
    }

    const valeur = Number(note);

    return Number.isFinite(valeur) ? valeur.toFixed(2) : String(note);
}
</script>

<template>
    <AppLayout>
        <Head :title="`Aperçu notes — ${typeProjet.nom}`" />

        <div class="flex gap-3 mt-2 space-y-2 px-2">
            <Button variant="ghost" size="icon" as-child>
                <Link :href="retourUrl">
                    <ArrowLeft class="h-4 w-4"/>
                </Link>
            </Button>
            <Heading
                :title="`Aperçu notes — ${typeProjet.nom}`"
                :description="`${titreClasse} · ${cours.nom_cours} · ${lignes.length} étudiant(s)`"
            />
        </div>
        <div class="mx-50 max-w-7xl space-y-4 mb-2">

            <!-- Tableau des notes -->
            <Card>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50 text-left">
                                <th class="px-4 py-3 font-medium">DA</th>
                                <th class="px-4 py-3 font-medium">Nom</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Note
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(ligne, i) in lignes"
                                :key="`${ligne.da}-${i}`"
                                class="border-b last:border-0 hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ ligne.da || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ ligne.prenom }} {{ ligne.nom }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ formaterNote(ligne.note) }}
                                </td>
                            </tr>
                            <tr v-if="lignes.length === 0">
                                <td
                                    colspan="3"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Aucun étudiant dans cette classe.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- Bouton copier -->
            <div class="flex gap-3">
                <Button variant="outline" @click="copierTexte">
                    <Copy class="mr-2 h-4 w-4" />
                    {{ copie ? 'Copié !' : 'Copier' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="retourUrl">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour à la classe
                    </Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
