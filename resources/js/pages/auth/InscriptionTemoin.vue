<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Check, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/inscription/temoin';

const { t } = useI18n();

type Thematique = {
    id: number;
    nom: string;
    etablissement_id: number;
};

type Etablissement = {
    id: number;
    nom: string;
    ville: string;
    thematiques: Thematique[];
};

type Choix = {
    etablissement_id: number;
    thematique_ids: number[];
    theme_libre: string;
};

const props = defineProps<{
    etablissements: Etablissement[];
}>();

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string } }).flash);

// ─── Formulaire ───────────────────────────────────────────────────────────────
const form = useForm({
    prenom: '',
    nom: '',
    email: '',
    password: '',
    password_confirmation: '',
    choix: [] as Choix[],
    description: '',
    disponible_appel_distance: false,
});

// ─── Gestion des cégeps ajoutés ───────────────────────────────────────────────
const cegepAjouter = ref<string>('');

// Cégeps pas encore ajoutés au formulaire
const etablissementsDispo = computed(() =>
    props.etablissements.filter(
        (e) => !form.choix.some((c) => c.etablissement_id === e.id),
    ),
);

function getEtablissement(id: number): Etablissement | undefined {
    return props.etablissements.find((e) => e.id === id);
}

function ajouterCegep() {
    const id = Number(cegepAjouter.value);
    if (!id) return;

    form.choix.push({ etablissement_id: id, thematique_ids: [], theme_libre: '' });
    cegepAjouter.value = '';
}

function retirerCegep(index: number) {
    form.choix.splice(index, 1);
}

function toggleThematique(choixIndex: number, thematiqueId: number) {
    const current = form.choix[choixIndex].thematique_ids;
    const idx = current.indexOf(thematiqueId);
    const newIds = idx >= 0
        ? current.filter((id) => id !== thematiqueId)
        : [...current, thematiqueId];

    // Remplacer form.choix entièrement pour déclencher la réactivité Vue sur le v-for
    form.choix = form.choix.map((c, i) =>
        i === choixIndex ? { ...c, thematique_ids: newIds } : c,
    );
}

// ─── Soumission ───────────────────────────────────────────────────────────────
function submit() {
    form.post(store.url(), {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <AuthBase
        :title="t('inscription_temoin.title')"
        :description="t('inscription_temoin.description')"
    >
        <Head :title="t('inscription_temoin.page_title')" />

        <!-- Message de succès -->
        <div
            v-if="flash?.success"
            class="rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-950 dark:text-green-300"
        >
            {{ flash.success }}
        </div>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <!-- Prénom + Nom -->
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                    <Label for="prenom">{{ $t('inscription_temoin.label_first_name') }}</Label>
                    <Input
                        id="prenom"
                        v-model="form.prenom"
                        type="text"
                        required
                        autocomplete="given-name"
                        :placeholder="$t('inscription_temoin.placeholder_first_name')"
                    />
                    <InputError :message="form.errors.prenom" />
                </div>
                <div class="grid gap-2">
                    <Label for="nom">{{ $t('inscription_temoin.label_last_name') }}</Label>
                    <Input
                        id="nom"
                        v-model="form.nom"
                        type="text"
                        required
                        autocomplete="family-name"
                        :placeholder="$t('inscription_temoin.placeholder_last_name')"
                    />
                    <InputError :message="form.errors.nom" />
                </div>
            </div>

            <!-- Courriel -->
            <div class="grid gap-2">
                <Label for="email">{{ $t('inscription_temoin.label_email') }}</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    :placeholder="$t('inscription_temoin.placeholder_email')"
                />
                <InputError :message="form.errors.email" />
            </div>

            <!-- Mot de passe -->
            <div class="grid gap-2">
                <Label for="password">{{ $t('inscription_temoin.label_password') }}</Label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <!-- Confirmation mot de passe -->
            <div class="grid gap-2">
                <Label for="password_confirmation">{{ $t('inscription_temoin.label_password_confirmation') }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <!-- Section cégeps -->
            <div class="grid gap-3">
                <div>
                    <p class="text-sm font-medium">{{ $t('inscription_temoin.label_cegeps') }}</p>
                    <p class="text-muted-foreground text-xs">{{ $t('inscription_temoin.hint_cegeps') }}</p>
                </div>

                <!-- Cégeps déjà ajoutés -->
                <div
                    v-for="(choix, index) in form.choix"
                    :key="choix.etablissement_id"
                    class="rounded-lg border"
                >
                    <!-- En-tête du cégep -->
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <span class="text-sm font-semibold">
                            {{ getEtablissement(choix.etablissement_id)?.nom }}
                            <span class="text-muted-foreground font-normal">
                                — {{ getEtablissement(choix.etablissement_id)?.ville }}
                            </span>
                        </span>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-destructive"
                            :aria-label="$t('inscription_temoin.remove_cegep')"
                            @click="retirerCegep(index)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Thématiques de ce cégep -->
                    <div class="px-4 pt-3">
                        <p class="text-muted-foreground mb-2 text-xs">{{ $t('inscription_temoin.label_thematiques') }}</p>
                        <div
                            v-if="getEtablissement(choix.etablissement_id)?.thematiques.length"
                            class="mb-3 flex flex-col gap-2"
                        >
                            <div
                                v-for="thematique in getEtablissement(choix.etablissement_id)?.thematiques"
                                :key="thematique.id"
                                class="flex cursor-pointer select-none items-center gap-2"
                                @click="toggleThematique(index, thematique.id)"
                            >
                                <div
                                    class="size-4 shrink-0 rounded-[4px] border shadow-xs flex items-center justify-center transition-colors"
                                    :class="choix.thematique_ids.includes(thematique.id)
                                        ? 'bg-primary border-primary text-primary-foreground'
                                        : 'border-input bg-background'"
                                >
                                    <Check v-if="choix.thematique_ids.includes(thematique.id)" class="size-3.5" />
                                </div>
                                <span class="text-sm font-normal">{{ thematique.nom }}</span>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground mb-3 text-xs italic">
                            {{ $t('inscription_temoin.no_thematiques') }}
                        </p>
                        <InputError :message="(form.errors as Record<string, string>)[`choix.${index}.thematique_ids`]" />
                    </div>

                    <!-- Thème libre pour ce cégep -->
                    <div class="px-4 pb-4">
                        <Label :for="`theme-libre-${index}`" class="text-xs">
                            {{ $t('inscription_temoin.label_theme_libre') }}
                        </Label>
                        <Input
                            :id="`theme-libre-${index}`"
                            v-model="choix.theme_libre"
                            type="text"
                            class="mt-1"
                            :placeholder="$t('inscription_temoin.placeholder_theme_libre')"
                        />
                        <InputError :message="(form.errors as Record<string, string>)[`choix.${index}.theme_libre`]" />
                    </div>
                </div>

                <!-- Dropdown pour ajouter un cégep -->
                <div v-if="etablissementsDispo.length > 0" class="flex gap-2">
                    <Select v-model="cegepAjouter" class="flex-1">
                        <SelectTrigger>
                            <SelectValue :placeholder="$t('inscription_temoin.placeholder_ajouter_cegep')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="etab in etablissementsDispo"
                                :key="etab.id"
                                :value="String(etab.id)"
                            >
                                {{ etab.nom }} — {{ etab.ville }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" :disabled="!cegepAjouter" @click="ajouterCegep">
                        <Plus class="mr-1 h-4 w-4" />
                        {{ $t('inscription_temoin.btn_ajouter_cegep') }}
                    </Button>
                </div>

                <InputError :message="form.errors.choix" />
            </div>

            <!-- Description bio -->
            <div class="grid gap-2">
                <Label for="description">{{ $t('inscription_temoin.label_description') }}</Label>
                <p class="text-muted-foreground text-xs">
                    {{ $t('inscription_temoin.description_bio_hint') }}
                </p>
                <Textarea
                    id="description"
                    v-model="form.description"
                    required
                    :rows="4"
                    :placeholder="$t('inscription_temoin.placeholder_description')"
                />
                <InputError :message="form.errors.description" />
            </div>

            <!-- Disponibilité appel à distance -->
            <div class="flex items-start gap-3">
                <div
                    class="mt-0.5 size-4 shrink-0 rounded-[4px] border shadow-xs flex items-center justify-center transition-colors cursor-pointer"
                    :class="form.disponible_appel_distance
                        ? 'bg-primary border-primary text-primary-foreground'
                        : 'border-input bg-background'"
                    @click="form.disponible_appel_distance = !form.disponible_appel_distance"
                >
                    <Check v-if="form.disponible_appel_distance" class="size-3.5" />
                </div>
                <div class="grid gap-1">
                    <label
                        class="text-sm font-medium cursor-pointer"
                        @click="form.disponible_appel_distance = !form.disponible_appel_distance"
                    >
                        {{ $t('inscription_temoin.label_disponible_appel_distance') }}
                    </label>
                    <p class="text-xs text-muted-foreground">
                        {{ $t('inscription_temoin.hint_disponible_appel_distance') }}
                    </p>
                </div>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ $t('inscription_temoin.submit') }}
            </Button>
        </form>
    </AuthBase>
</template>
