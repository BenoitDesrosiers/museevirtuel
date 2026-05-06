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
    note: string | null;
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

/** Texte brut formaté : "DA NOTE\n" pour chaque étudiant. */
const texte = computed(() =>
    props.lignes
        .map((l) => `${l.da} ${l.note ?? ''}`.trimEnd())
        .join('\n'),
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
                <BoutonTooltip texte="Retour au projet" variant="ghost" as-child>
                    <Link :href="retourUrl">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </BoutonTooltip>
                <Heading
                    :title="`Aperçu notes — Groupe ${groupe.numero}`"
                    :description="`${typeProjet.nom} · ${lignes.length} étudiant(s)`"
                />
            </div>

            <!-- Zone texte copiable -->
            <div class="rounded-md border bg-muted p-4">
                <pre class="font-mono text-sm leading-relaxed whitespace-pre-wrap">{{ texte || '(Aucune note saisie)' }}</pre>
            </div>

            <!-- Bouton copier -->
            <div class="flex gap-3">
                <Button variant="outline" @click="copierTexte">
                    <Copy class="mr-2 h-4 w-4" />
                    {{ copie ? 'Copié !' : 'Copier' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="retourUrl">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour au projet
                    </Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
