<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Minus, Plus, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type CritereInput = { id?: number; label: string; ponderation: number };
type MalusInput = { id?: number; label: string; deduction: number; description: string };

type GrilleExistante = {
    id: number;
    nom: string;
    description: string | null;
    criteres: (CritereInput & { ordre: number })[];
    malus: (MalusInput & { ordre: number })[];
};

type ClasseInfo = { id: number; nom_cours: string; code: string; groupe: string };

type Props = {
    classe: ClasseInfo;
    grille: GrilleExistante | null;
};

const props = defineProps<Props>();

const isEdit = computed(() => props.grille !== null);

const form = useForm<{
    nom: string;
    description: string;
    criteres: CritereInput[];
    malus: MalusInput[];
}>({
    nom: props.grille?.nom ?? '',
    description: props.grille?.description ?? '',
    criteres: props.grille?.criteres.map(c => ({ id: c.id, label: c.label, ponderation: c.ponderation }))
        ?? [{ label: '', ponderation: 0 }],
    malus: props.grille?.malus.map(m => ({ id: m.id, label: m.label, deduction: m.deduction, description: m.description ?? '' }))
        ?? [],
});

// ─── Calcul en temps réel ────────────────────────────────────────────────────
const sommeEnCours = computed(() =>
    form.criteres.reduce((s, c) => s + (Number(c.ponderation) || 0), 0)
);
const ponderationsValides = computed(() => sommeEnCours.value === 100);

// ─── Critères ────────────────────────────────────────────────────────────────
function ajouterCritere() {
    form.criteres.push({ label: '', ponderation: 0 });
}

function supprimerCritere(index: number) {
    if (form.criteres.length <= 1) {
        return;
    }
    form.criteres.splice(index, 1);
}

// ─── Malus ───────────────────────────────────────────────────────────────────
function ajouterMalus() {
    form.malus.push({ label: '', deduction: 0, description: '' });
}

function supprimerMalus(index: number) {
    form.malus.splice(index, 1);
}

// ─── Soumission ──────────────────────────────────────────────────────────────
function submit() {
    if (isEdit.value) {
        form.put(`/classes/${props.classe.id}/grille`);
    } else {
        form.post(`/classes/${props.classe.id}/grille`);
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="isEdit ? $t('grille.page_title_edit') : $t('grille.page_title_new')" />

        <div class="mx-auto flex max-w-3xl flex-col gap-6 p-6">
            <!-- Retour vers la classe -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/classes/${classe.id}`">
                        ← {{ classe.code }} — {{ classe.groupe }}
                    </Link>
                </Button>
            </div>

            <Heading
                :title="isEdit ? $t('grille.heading_edit') : $t('grille.heading_new')"
                :description="isEdit ? $t('grille.description_edit') : $t('grille.description_new')"
            />

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <!-- Informations générales -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ $t('grille.info_title') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="nom">{{ $t('grille.label_name') }} <span class="text-destructive">*</span></Label>
                            <Input
                                id="nom"
                                v-model="form.nom"
                                :placeholder="$t('grille.placeholder_name')"
                                required
                            />
                            <InputError :message="form.errors.nom" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="description">{{ $t('grille.label_description') }}</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                :placeholder="$t('grille.placeholder_description')"
                                rows="2"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Compétences / critères -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base">{{ $t('grille.competencies_title') }}</CardTitle>
                            <!-- Indicateur de somme -->
                            <div class="flex items-center gap-2">
                                <Badge
                                    :class="ponderationsValides
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'"
                                    class="tabular-nums"
                                >
                                    {{ sommeEnCours }} / 100
                                </Badge>
                                <TriangleAlert
                                    v-if="!ponderationsValides"
                                    class="text-amber-500 h-4 w-4"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <!-- En-têtes colonnes -->
                        <div class="grid grid-cols-[1fr_100px_36px] gap-2 px-1 text-xs font-medium text-muted-foreground">
                            <span>{{ $t('grille.col_label') }}</span>
                            <span class="text-center">{{ $t('grille.col_weight') }}</span>
                            <span />
                        </div>

                        <!-- Lignes critères -->
                        <div
                            v-for="(critere, index) in form.criteres"
                            :key="index"
                            class="grid grid-cols-[1fr_100px_36px] items-center gap-2"
                        >
                            <div>
                                <Input
                                    v-model="critere.label"
                                    :placeholder="$t('grille.competency_placeholder', { n: index + 1 })"
                                    required
                                />
                                <InputError :message="(form.errors as any)[`criteres.${index}.label`]" />
                            </div>
                            <div>
                                <Input
                                    v-model.number="critere.ponderation"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="text-center tabular-nums"
                                    required
                                />
                                <InputError :message="(form.errors as any)[`criteres.${index}.ponderation`]" />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="form.criteres.length <= 1"
                                class="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
                                @click="supprimerCritere(index)"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>
                        </div>

                        <InputError :message="form.errors.criteres" />

                        <Button type="button" variant="outline" size="sm" class="self-start" @click="ajouterCritere">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('grille.add_competency') }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Malus (optionnel) -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base">{{ $t('grille.penalties_title') }}</CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ $t('grille.penalties_description') }}
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <div
                            v-for="(m, index) in form.malus"
                            :key="index"
                            class="grid grid-cols-[1fr_120px_36px] items-start gap-2"
                        >
                            <div class="flex flex-col gap-1">
                                <Input
                                    v-model="m.label"
                                    :placeholder="$t('grille.penalty_label_placeholder')"
                                    required
                                />
                                <Input
                                    v-model="m.description"
                                    :placeholder="$t('grille.penalty_description_placeholder')"
                                    class="text-sm"
                                />
                                <InputError :message="(form.errors as any)[`malus.${index}.label`]" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="relative">
                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">−</span>
                                    <Input
                                        v-model.number="m.deduction"
                                        type="number"
                                        min="0.01"
                                        max="100"
                                        step="0.5"
                                        class="pl-6 tabular-nums"
                                        required
                                    />
                                </div>
                                <span class="text-center text-xs text-muted-foreground">{{ $t('grille.penalty_points') }}</span>
                                <InputError :message="(form.errors as any)[`malus.${index}.deduction`]" />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="mt-1 h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
                                @click="supprimerMalus(index)"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>
                        </div>

                        <Button type="button" variant="outline" size="sm" class="self-start" @click="ajouterMalus">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('grille.add_penalty') }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" as-child>
                        <Link :href="`/classes/${classe.id}`">{{ $t('grille.cancel') }}</Link>
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || !ponderationsValides"
                    >
                        {{ isEdit ? $t('grille.save_changes') : $t('grille.create_grid') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
