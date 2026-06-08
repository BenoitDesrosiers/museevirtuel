<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Copy } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type TypeProjetInfo = {
    id: number;
    nom: string;
    ponderation: number;
};

type Ligne = {
    da: string;
    prenom: string;
    nom: string;
    notes_par_type: Record<number, number | null>;
    total: number;
};

type Props = {
    cours: { id: number; nom_cours: string };
    classe: { id: number; numero: string; nom: string | null };
    typesProjets: TypeProjetInfo[];
    lignes: Ligne[];
    sommePonderations: number;
};

const props = defineProps<Props>();

const copie = ref(false);

const retourUrl = computed(
    () => `/cours/${props.cours.id}/classes/${props.classe.id}`,
);

const titreClasse = computed(
    () => props.classe.nom ?? `Classe ${props.classe.numero}`,
);

/** Vrai si la somme des pondérations ne fait pas 100 %. */
const avertissementSomme = computed(
    () => Math.abs(props.sommePonderations - 100) > 0.01,
);

/** Texte brut formaté : "DA TOTAL\n" pour chaque étudiant — compatible avec le format d'import. */
const texte = computed(() =>
    props.lignes.map((l) => `${l.da} ${l.total.toFixed(2)}`).join('\n'),
);

async function copierTexte(): Promise<void> {
    try {
        await navigator.clipboard.writeText(texte.value);
    } catch {
        // Clipboard API indisponible (contexte non sécurisé ou permission refusée) — échec silencieux
    }

    copie.value = true;
    setTimeout(() => {
        copie.value = false;
    }, 2000);
}

/**
 * Formate une note décimale pour l'affichage (2 décimales, '—' si null).
 */
function formaterNote(note: number | null): string {
    if (note === null) {
        return '—';
    }

    return note.toFixed(2);
}
</script>

<template>
    <AppLayout>
        <Head :title="`Notes accumulées — ${titreClasse}`" />

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-8">
            <!-- En-tête -->
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="retourUrl">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <Heading
                    :title="`Notes accumulées — ${titreClasse}`"
                    :description="`${cours.nom_cours} · ${lignes.length} étudiant(s) · Somme des pondérations : ${sommePonderations} %`"
                />
            </div>

            <!-- Avertissement si la somme des pondérations ne fait pas 100 % -->
            <div
                v-if="avertissementSomme"
                class="flex items-start gap-3 rounded-md border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-950 dark:text-yellow-300"
            >
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    La somme des pondérations est de
                    <strong>{{ sommePonderations }} %</strong> au lieu de 100 %.
                    Le total affiché reflète cette somme partielle.
                </span>
            </div>

            <!-- Tableau des notes -->
            <Card>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50 text-left">
                                <th class="px-4 py-3 font-medium">DA</th>
                                <th class="px-4 py-3 font-medium">Nom</th>
                                <th
                                    v-for="tp in typesProjets"
                                    :key="tp.id"
                                    class="px-4 py-3 text-right font-medium"
                                >
                                    {{ tp.nom }}
                                    <span
                                        class="block text-xs font-normal text-muted-foreground"
                                    >
                                        {{ tp.ponderation }} %
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold">
                                    Total pondéré
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
                                <td
                                    v-for="tp in typesProjets"
                                    :key="tp.id"
                                    class="px-4 py-3 text-right tabular-nums"
                                >
                                    {{
                                        formaterNote(
                                            ligne.notes_par_type[tp.id] ?? null,
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-semibold tabular-nums"
                                >
                                    {{ ligne.total.toFixed(2) }}
                                </td>
                            </tr>
                            <tr v-if="lignes.length === 0">
                                <td
                                    :colspan="typesProjets.length + 3"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Aucun étudiant dans cette classe.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- Actions -->
            <div class="flex gap-3">
                <Button variant="outline" @click="copierTexte">
                    <Copy class="mr-2 h-4 w-4" />
                    {{ copie ? 'Copié !' : 'Copier DA + total' }}
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
