<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Minus, Plus, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import typesProjets from '@/routes/types-projets';

type CritereInput = { id?: number; label: string; ponderation: number };
type MalusInput = { id?: number; label: string; deduction: number; description: string };

type GrilleExistante = {
    id: number;
    nom: string;
    description: string | null;
    criteres: (CritereInput & { ordre: number })[];
    malus: (MalusInput & { ordre: number })[];
};

type TypeProjetInfo = { id: number; nom: string; description: string | null };

type Props = {
    typeProjet: TypeProjetInfo;
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
    if (form.criteres.length <= 1) return;
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
        form.put(typesProjets.grille.update.url(props.typeProjet.id));
    } else {
        form.post(typesProjets.grille.store.url(props.typeProjet.id));
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="isEdit ? 'Modifier la grille' : 'Nouvelle grille de correction'" />

        <div class="mx-auto flex max-w-3xl flex-col gap-6 p-6">
            <!-- Retour vers les types de projet -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="typesProjets.index.url()">
                        ← Types de projet
                    </Link>
                </Button>
            </div>

            <Heading
                :title="isEdit ? 'Modifier la grille' : 'Nouvelle grille de correction'"
                :description="`Type de projet : ${typeProjet.nom}`"
            />

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <!-- Informations générales -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Informations</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="nom">Nom de la grille <span class="text-destructive">*</span></Label>
                            <Input
                                id="nom"
                                v-model="form.nom"
                                placeholder="Ex. : Grille projet histoire 4e secondaire"
                                required
                            />
                            <InputError :message="form.errors.nom" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="description">Description (optionnelle)</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Notes pour vous rappeler quand utiliser cette grille..."
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
                            <CardTitle class="text-base">Compétences</CardTitle>
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
                        <div class="grid grid-cols-[1fr_100px_36px] gap-2 text-xs font-medium text-muted-foreground px-1">
                            <span>Libellé</span>
                            <span class="text-center">Pondération</span>
                            <span />
                        </div>

                        <div
                            v-for="(critere, index) in form.criteres"
                            :key="index"
                            class="grid grid-cols-[1fr_100px_36px] items-center gap-2"
                        >
                            <div>
                                <Input
                                    v-model="critere.label"
                                    :placeholder="`Compétence ${index + 1}`"
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
                                class="text-muted-foreground hover:text-destructive h-8 w-8 shrink-0"
                                @click="supprimerCritere(index)"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>
                        </div>

                        <InputError :message="form.errors.criteres" />

                        <Button type="button" variant="outline" size="sm" class="self-start" @click="ajouterCritere">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter une compétence
                        </Button>
                    </CardContent>
                </Card>

                <!-- Malus (optionnel) -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base">Malus (optionnel)</CardTitle>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    Points déduits de la note finale pour certains cas (ex. : fautes de français).
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
                                    placeholder="Ex. : Fautes de français"
                                    required
                                />
                                <Input
                                    v-model="m.description"
                                    placeholder="Description (optionnelle)"
                                    class="text-sm"
                                />
                                <InputError :message="(form.errors as any)[`malus.${index}.label`]" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="relative">
                                    <span class="text-muted-foreground absolute left-2.5 top-1/2 -translate-y-1/2 text-sm">−</span>
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
                                <span class="text-muted-foreground text-xs text-center">points</span>
                                <InputError :message="(form.errors as any)[`malus.${index}.deduction`]" />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="text-muted-foreground hover:text-destructive mt-1 h-8 w-8 shrink-0"
                                @click="supprimerMalus(index)"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>
                        </div>

                        <Button type="button" variant="outline" size="sm" class="self-start" @click="ajouterMalus">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter un malus
                        </Button>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" as-child>
                        <Link :href="typesProjets.index.url()">Annuler</Link>
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || !ponderationsValides"
                    >
                        {{ isEdit ? 'Enregistrer les modifications' : 'Créer la grille' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
