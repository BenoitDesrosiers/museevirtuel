<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    Circle,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Classe = {
    id: number;
    numero: string;
    code: string;
    nom: string | null;
    jour_semaine: string | null;
    plage_horaire: string | null;
    cours_id: number;
    groupes_count: number;
};

type EcheancierEtape = {
    id: number;
    semaine: number;
    etape: string;
    is_done: boolean;
    ordre: number;
    etudiant_done: boolean;
};

type Props = {
    cours: Cours;
    classes: Classe[];
    echeancierEtapes: EcheancierEtape[];
};

const props = defineProps<Props>();

const echeancierParSemaine = computed(() => {
    const map = new Map<number, EcheancierEtape[]>();

    for (const etape of props.echeancierEtapes) {
        if (!map.has(etape.semaine)) {
            map.set(etape.semaine, []);
        }

        map.get(etape.semaine)!.push(etape);
    }

    return map;
});

const semaines = computed(() =>
    [...echeancierParSemaine.value.keys()].sort((a, b) => a - b),
);

function toggleEtape(etape: EcheancierEtape) {
    router.patch(
        `/cours/${props.cours.id}/echeancier/${etape.id}/toggle-etudiant`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout>
        <Head
            :title="`${$t('cours.classes.page_title')} — ${cours.nom_cours}`"
        />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link href="/cours">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('cours.classes.back') }}
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="`${cours.code} — Groupe ${cours.groupe}`"
                :description="cours.nom_cours"
            />

            <!-- Liste des classes -->
            <div
                v-if="classes.length === 0"
                class="py-12 text-center text-muted-foreground"
            >
                {{ $t('cours.classes.no_classes') }}
            </div>

            <div v-else class="flex flex-col gap-4">
                <Card
                    v-for="classe in classes"
                    :key="classe.id"
                    class="flex flex-col"
                >
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{ classe.nom ?? `Classe ${classe.numero}` }}
                        </CardTitle>
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ classe.code }} · {{ classe.numero }}
                        </p>
                        <p
                            v-if="classe.jour_semaine || classe.plage_horaire"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                [classe.jour_semaine, classe.plage_horaire]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                    </CardHeader>
                    <CardContent
                        class="flex flex-1 flex-col gap-2 text-sm text-muted-foreground"
                    >
                        <div class="flex items-center gap-1">
                            <Users class="h-3 w-3" />
                            {{ $t('cours.classes.groups_count') }}
                        </div>
                    </CardContent>
                    <CardFooter class="flex justify-end border-t pt-3">
                        <BoutonTooltip
                            :texte="$t('cours.classes.my_group')"
                            size="sm"
                            variant="outline"
                            as-child
                        >
                            <Link
                                :href="`/cours/${cours.id}/classes/${classe.id}/groupes`"
                            >
                                <Users class="h-4 w-4" />
                            </Link>
                        </BoutonTooltip>
                    </CardFooter>
                </Card>
            </div>

            <!-- Échéancier -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <CalendarDays class="h-4 w-4" />
                        {{ $t('cours.classes.schedule_title') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="echeancierEtapes.length === 0"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('cours.classes.schedule_empty') }}
                    </div>

                    <div v-else class="flex flex-col gap-4">
                        <div v-for="semaine in semaines" :key="semaine">
                            <p
                                class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {{
                                    $t('cours.classes.schedule_week', {
                                        n: semaine,
                                    })
                                }}
                            </p>
                            <ul class="flex flex-col gap-1">
                                <li
                                    v-for="etape in echeancierParSemaine.get(
                                        semaine,
                                    )"
                                    :key="etape.id"
                                    class="flex items-start gap-2"
                                >
                                    <button
                                        class="mt-0.5 shrink-0 text-muted-foreground transition-colors hover:text-primary"
                                        :class="{
                                            'text-primary': etape.etudiant_done,
                                        }"
                                        @click="toggleEtape(etape)"
                                    >
                                        <CheckCircle2
                                            v-if="etape.etudiant_done"
                                            class="h-4 w-4"
                                        />
                                        <Circle v-else class="h-4 w-4" />
                                    </button>
                                    <span
                                        class="text-sm"
                                        :class="{
                                            'text-muted-foreground line-through':
                                                etape.is_done,
                                            'font-medium': etape.etudiant_done,
                                        }"
                                    >
                                        {{ etape.etape }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
