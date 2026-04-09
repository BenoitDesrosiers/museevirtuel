<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
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

const { t } = useI18n();

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

            <!-- Thème de recherche -->
            <div class="grid gap-2">
                <Label for="thematique">{{ $t('inscription_temoin.label_theme') }}</Label>
                <Select :model-value="choixThematique" @update:model-value="onThematiqueChange">
                    <SelectTrigger id="thematique" class="w-full">
                        <SelectValue :placeholder="$t('inscription_temoin.placeholder_theme')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="thematique in thematiques"
                            :key="thematique.id"
                            :value="String(thematique.id)"
                        >
                            {{ thematique.nom }}
                        </SelectItem>
                        <SelectItem :value="OPTION_AUTRE">
                            {{ $t('inscription_temoin.option_other') }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.thematique_id" />
            </div>

            <!-- Thème libre (conditionnel) -->
            <div v-if="choixThematique === OPTION_AUTRE" class="grid gap-2">
                <Label for="theme_libre">{{ $t('inscription_temoin.label_theme_libre') }}</Label>
                <Input
                    id="theme_libre"
                    v-model="form.theme_libre"
                    type="text"
                    :placeholder="$t('inscription_temoin.placeholder_theme_libre')"
                />
                <InputError :message="form.errors.theme_libre" />
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
                    rows="4"
                    :placeholder="$t('inscription_temoin.placeholder_description')"
                />
                <InputError :message="form.errors.description" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ $t('inscription_temoin.submit') }}
            </Button>
        </form>
    </AuthBase>
</template>
