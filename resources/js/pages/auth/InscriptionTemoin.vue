<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/inscription/temoin';

type Thematique = {
    id: number;
    nom: string;
};

defineProps<{
    thematiques: Thematique[];
}>();

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string } }).flash);

const OPTION_AUTRE = '__autre__';
const choixThematique = ref<string>('');

const form = useForm({
    prenom: '',
    nom: '',
    email: '',
    password: '',
    password_confirmation: '',
    thematique_id: null as number | null,
    theme_libre: '',
    description: '',
});

function onThematiqueChange(value: string) {
    choixThematique.value = value;
    if (value === OPTION_AUTRE) {
        form.thematique_id = null;
    } else {
        form.thematique_id = Number(value);
        form.theme_libre = '';
    }
}

function submit() {
    form.post(store.url(), {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <AuthBase
        title="Demande d'inscription"
        description="Remplissez ce formulaire pour soumettre votre demande. Un administrateur examinera votre inscription."
    >
        <Head title="Inscription — Témoin" />

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
                    <Label for="prenom">Prénom</Label>
                    <Input
                        id="prenom"
                        v-model="form.prenom"
                        type="text"
                        required
                        autocomplete="given-name"
                        placeholder="Marie"
                    />
                    <InputError :message="form.errors.prenom" />
                </div>
                <div class="grid gap-2">
                    <Label for="nom">Nom</Label>
                    <Input
                        id="nom"
                        v-model="form.nom"
                        type="text"
                        required
                        autocomplete="family-name"
                        placeholder="Tremblay"
                    />
                    <InputError :message="form.errors.nom" />
                </div>
            </div>

            <!-- Courriel -->
            <div class="grid gap-2">
                <Label for="email">Courriel</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="marie.tremblay@exemple.com"
                />
                <InputError :message="form.errors.email" />
            </div>

            <!-- Mot de passe -->
            <div class="grid gap-2">
                <Label for="password">Mot de passe</Label>
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
                <Label for="password_confirmation">Confirmer le mot de passe</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <!-- Thème de recherche -->
            <div class="grid gap-2">
                <Label for="thematique">Thème de recherche</Label>
                <Select :model-value="choixThematique" @update:model-value="onThematiqueChange">
                    <SelectTrigger id="thematique" class="w-full">
                        <SelectValue placeholder="Choisissez un thème…" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="t in thematiques"
                            :key="t.id"
                            :value="String(t.id)"
                        >
                            {{ t.nom }}
                        </SelectItem>
                        <SelectItem :value="OPTION_AUTRE">
                            Autre (préciser ci-dessous)
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.thematique_id" />
            </div>

            <!-- Thème libre (conditionnel) -->
            <div v-if="choixThematique === OPTION_AUTRE" class="grid gap-2">
                <Label for="theme_libre">Précisez votre thème de recherche</Label>
                <Input
                    id="theme_libre"
                    v-model="form.theme_libre"
                    type="text"
                    placeholder="Ex. : Les métiers d'autrefois au Québec"
                />
                <InputError :message="form.errors.theme_libre" />
            </div>

            <!-- Description bio -->
            <div class="grid gap-2">
                <Label for="description">Parlez-nous de vous</Label>
                <p class="text-muted-foreground text-xs">
                    Qui êtes-vous ? D'où venez-vous ? Qu'est-ce qui vous a amené à cette démarche ?
                </p>
                <Textarea
                    id="description"
                    v-model="form.description"
                    required
                    rows="4"
                    placeholder="Je suis né(e) à…"
                />
                <InputError :message="form.errors.description" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Soumettre ma demande
            </Button>
        </form>
    </AuthBase>
</template>
