<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Grid2x2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FormDialog from '@/components/FormDialog.vue';
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

const { t } = useI18n();

type GrilleResume = { id: number; nom: string } | null;

type SectionType = 'texte' | 'paragraphes' | 'individuel';

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    type: SectionType;
};

const sectionTypeLabels = computed<Record<SectionType, string>>(() => ({
    texte: t('types_projet.edit.section_type_texte_label'),
    paragraphes: t('types_projet.edit.section_type_paragraphes_label'),
    individuel: t('types_projet.edit.section_type_individuel_label'),
}));

type TypeProjet = {
    id: number;
    nom: string;
    description: string | null;
    accessible: boolean;
    grille: GrilleResume;
    sections: Section[];
};

type Props = {
    typesProjets: TypeProjet[];
};

const props = defineProps<Props>();

// ─── Création TypeProjet ──────────────────────────────────────────────────────
const showCreateDialog = ref(false);
const createForm = useForm({
    nom: '',
    description: '',
    sections: [] as { label: string; description: string; type: SectionType }[],
});

function ajouterSectionCreate() {
    createForm.sections.push({ label: '', description: '', type: 'texte' });
}

function supprimerSectionCreate(idx: number) {
    createForm.sections.splice(idx, 1);
}

function creer() {
    createForm.post(typesProjets.store.url(), {
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
        },
    });
}

// ─── Toggle accessible ────────────────────────────────────────────────────────
const toggleForm = useForm({});

function toggleAccessible(tp: TypeProjet) {
    toggleForm.patch(typesProjets.toggleAccessible.url(tp.id));
}

// ─── Suppression TypeProjet ───────────────────────────────────────────────────
const deleteForm = useForm({});

function supprimer(tp: TypeProjet) {
    if (!confirm(t('types_projet.index.confirm_delete', { nom: tp.nom }))) {
        return;
    }
    deleteForm.delete(typesProjets.destroy.url(tp.id));
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('types_projet.index.page_title')" />

        <div class="mx-auto flex max-w-3xl flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <Heading
                    :title="$t('types_projet.index.heading_title')"
                    :description="$t('types_projet.index.heading_description')"
                />
                <Button size="sm" @click="showCreateDialog = true">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ $t('types_projet.index.new_type') }}
                </Button>
            </div>

            <!-- Liste vide -->
            <Card v-if="props.typesProjets.length === 0">
                <CardContent class="py-10 text-center text-muted-foreground">
                    {{ $t('types_projet.index.no_types') }}
                </CardContent>
            </Card>

            <!-- Liste des types -->
            <Card v-for="tp in props.typesProjets" :key="tp.id">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <CardTitle class="flex items-center gap-2 text-base">
                                {{ tp.nom }}
                                <Badge
                                    :class="
                                        tp.accessible
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'
                                    "
                                    class="text-xs"
                                >
                                    {{ tp.accessible ? $t('types_projet.index.badge_accessible') : $t('types_projet.index.badge_not_accessible') }}
                                </Badge>
                            </CardTitle>
                            <p v-if="tp.description" class="mt-1 text-sm text-muted-foreground">
                                {{ tp.description }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                size="sm"
                                :variant="tp.accessible ? 'outline' : 'secondary'"
                                class="text-xs"
                                :disabled="toggleForm.processing"
                                @click="toggleAccessible(tp)"
                            >
                                <ChevronRight v-if="!tp.accessible" class="mr-1 h-3 w-3" />
                                <ChevronDown v-else class="mr-1 h-3 w-3" />
                                {{ tp.accessible ? $t('types_projet.index.btn_hide') : $t('types_projet.index.btn_make_accessible') }}
                            </Button>

                            <Button size="icon" variant="ghost" class="h-8 w-8" as-child>
                                <Link :href="typesProjets.edit.url(tp.id)" :title="$t('common.edit')">
                                    <Pencil class="h-4 w-4" />
                                </Link>
                            </Button>

                            <Button
                                size="icon"
                                variant="ghost"
                                class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                :disabled="deleteForm.processing"
                                @click="supprimer(tp)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="flex flex-col gap-4">
                    <!-- Grille associée -->
                    <div class="flex items-center gap-3">
                        <Grid2x2 class="h-4 w-4 text-muted-foreground" />
                        <span class="text-sm text-muted-foreground">{{ $t('types_projet.index.grille_label') }}</span>
                        <a
                            :href="typesProjets.grille.edit.url(tp.id)"
                            class="text-sm font-medium text-primary hover:underline"
                        >
                            {{ tp.grille ? tp.grille.nom : $t('types_projet.index.configure_grille') }}
                        </a>
                    </div>

                    <!-- Sections (résumé) -->
                    <div class="border-t pt-3">
                        <p class="mb-2 text-xs font-medium text-muted-foreground">
                            {{ tp.sections.length }} section{{ tp.sections.length !== 1 ? 's' : '' }}
                            <span v-if="tp.sections.length === 0" class="font-normal italic">
                                {{ $t('types_projet.index.default_intro') }}
                            </span>
                        </p>
                        <div v-if="tp.sections.length > 0" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="s in [...tp.sections].sort((a, b) => a.ordre - b.ordre)"
                                :key="s.id"
                                class="rounded-md bg-muted px-2 py-0.5 text-xs"
                            >
                                {{ s.ordre }}. {{ s.label }}
                                <span class="ml-1 text-muted-foreground/60">
                                    ({{ sectionTypeLabels[s.type ?? 'texte'] }})
                                </span>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ─── Dialog création ──────────────────────────────────────────────── -->
        <FormDialog
            v-model:open="showCreateDialog"
            :title="$t('types_projet.index.modal_title')"
            :is-loading="createForm.processing"
            :submit-label="$t('types_projet.index.modal_create')"
            scrollable
            @submit="creer"
        >
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="nom-create">{{ $t('types_projet.index.modal_name_label') }} <span class="text-destructive">*</span></Label>
                    <Input
                        id="nom-create"
                        v-model="createForm.nom"
                        :placeholder="$t('types_projet.index.modal_name_placeholder')"
                        required
                    />
                    <InputError :message="createForm.errors.nom" />
                </div>

                <div class="grid gap-2">
                    <Label for="desc-create">{{ $t('types_projet.index.modal_description_label') }}</Label>
                    <Textarea
                        id="desc-create"
                        v-model="createForm.description"
                        :placeholder="$t('types_projet.index.modal_description_placeholder')"
                        rows="2"
                    />
                    <InputError :message="createForm.errors.description" />
                </div>

                <!-- Sections -->
                <div class="grid gap-3 border-t pt-3">
                    <div>
                        <Label>{{ $t('types_projet.index.modal_sections_title') }}</Label>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ $t('types_projet.index.modal_sections_hint') }}
                        </p>
                    </div>

                    <div v-if="createForm.sections.length > 0" class="flex flex-col gap-2">
                        <div
                            v-for="(section, idx) in createForm.sections"
                            :key="idx"
                            class="flex items-start gap-2 rounded-md border bg-muted/30 p-3"
                        >
                            <span class="mt-2 w-5 shrink-0 text-center text-xs text-muted-foreground">
                                {{ idx + 1 }}
                            </span>
                            <div class="flex-1 space-y-1.5">
                                <Input
                                    v-model="createForm.sections[idx].label"
                                    :placeholder="$t('types_projet.index.modal_section_title_placeholder')"
                                    required
                                />
                                <InputError :message="createForm.errors[`sections.${idx}.label`]" />
                                <select
                                    v-model="createForm.sections[idx].type"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs ring-offset-background focus:outline-none focus:ring-1 focus:ring-ring"
                                >
                                    <option v-for="(label, val) in sectionTypeLabels" :key="val" :value="val">
                                        {{ label }}
                                    </option>
                                </select>
                                <Input
                                    v-model="createForm.sections[idx].description"
                                    :placeholder="$t('types_projet.index.modal_section_instruction_placeholder')"
                                />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="mt-0.5 h-7 w-7 shrink-0 text-muted-foreground hover:text-destructive"
                                @click="supprimerSectionCreate(idx)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>

                    <Button type="button" size="sm" variant="outline" @click="ajouterSectionCreate">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('types_projet.index.modal_add_section') }}
                    </Button>
                </div>
            </div>
        </FormDialog>

    </AppLayout>
</template>
