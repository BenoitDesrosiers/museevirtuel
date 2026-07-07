<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Copy } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

type Ligne = {
    da: string;
    prenom: string;
    nom: string;
    note: number | null;
};

type Props = {
    cours: { id: number };
    classe: { id: number; cours_id: number };
    groupe: { id: number; numero: number; classe_id: number };
    typeProjet: { id: number; nom: string };
    lignes: Ligne[];
};

const props = defineProps<Props>();

const copie = ref(false);

/** Texte brut formaté : "DA NOTE\n" par étudiant — format pour import dans système externe. */
const texte = computed(() =>
    props.lignes.map((l) => `${l.da} ${l.note ?? ''}`.trimEnd()).join('\n'),
);

const retourUrl = computed(
    () =>
        `/cours/${props.classe.cours_id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets/${props.typeProjet.id}/edit`,
);

async function copierTexte(): Promise<void> {
    await navigator.clipboard.writeText(texte.value);
    copie.value = true;
    setTimeout(() => {
        copie.value = false;
    }, 2000);
}
</script>

<template>
    <AppLayout>
        <Head :title="`Aperçu notes — Groupe ${groupe.numero}`" />

        <div class="mx-auto max-w-xl space-y-6 px-4 py-8">
            <div class="flex items-center gap-3">
                <BoutonTooltip
                    texte="Retour au projet"
                    variant="ghost"
                    as-child
                >
                    <Link :href="retourUrl">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </BoutonTooltip>
                <Heading
                    :title="`Aperçu notes — Groupe ${groupe.numero}`"
                    :description="`${typeProjet.nom} · ${lignes.length} étudiant(s)`"
                />
            </div>

            <!-- Tableau DA + Nom + Note -->
            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-xs text-muted-foreground">
                            <th class="px-3 py-2 text-left font-medium">DA</th>
                            <th class="px-3 py-2 text-left font-medium">Nom</th>
                            <th class="px-3 py-2 text-right font-medium">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ligne in lignes"
                            :key="ligne.da"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2 font-mono text-muted-foreground">
                                {{ ligne.da }}
                            </td>
                            <td class="px-3 py-2">
                                {{ ligne.prenom }} {{ ligne.nom }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ ligne.note ?? '—' }}
                            </td>
                        </tr>
                        <tr v-if="lignes.length === 0">
                            <td
                                colspan="3"
                                class="px-3 py-6 text-center text-muted-foreground"
                            >
                                Aucune note saisie
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bouton copier (format "DA NOTE" pour import externe) -->
            <div class="flex gap-3">
                <Button variant="outline" @click="copierTexte">
                    <Copy class="mr-2 h-4 w-4" />
                    {{ copie ? 'Copié !' : 'Copier DA + note' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="retourUrl">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour au projet
                    </Link>
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                Le bouton "Copier" copie les données au format <span class="font-mono">DA NOTE</span> (une ligne par étudiant) pour import dans un système externe.
            </p>
        </div>
    </AppLayout>
</template>
