<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/inscription/temoin/engagements';

const { t } = useI18n();

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string; error?: string } }).flash);

// ─── Engagements à accepter ───────────────────────────────────────────────────
const engagementKeys = [
    'inscription_engagements.engagement_1',
    'inscription_engagements.engagement_2',
    'inscription_engagements.engagement_3',
    'inscription_engagements.engagement_4',
    'inscription_engagements.engagement_5',
    'inscription_engagements.engagement_6',
] as const;

const form = useForm({
    engagements: engagementKeys.map(() => false) as boolean[],
    signature: '',
});

// Tous les engagements cochés
const tousCoches = computed(() => form.engagements.every(Boolean));

// ─── Prévisualisation de la signature ────────────────────────────────────────
const signaturePreview = computed(() => form.signature.trim());

// ─── Soumission ───────────────────────────────────────────────────────────────
function submit() {
    form.post(store.url());
}

// ─── Retour avec confirmation ─────────────────────────────────────────────────
function retourEtape1() {
    if (confirm(t('inscription_engagements.back_confirm'))) {
        window.location.href = '/inscription/temoin';
    }
}
</script>

<template>
    <AuthBase
        :title="t('inscription_engagements.title')"
        :description="t('inscription_engagements.description')"
    >
        <Head :title="t('inscription_engagements.page_title')" />

        <!-- Erreur session expirée -->
        <div
            v-if="flash?.error"
            class="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950 dark:text-red-300"
        >
            {{ flash.error }}
        </div>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <!-- ─── Liste des engagements ──────────────────────────────────── -->
            <div class="grid gap-4">
                <p class="text-muted-foreground text-xs">
                    {{ $t('inscription_engagements.all_required') }}
                </p>

                <div
                    v-for="(key, index) in engagementKeys"
                    :key="key"
                    class="flex cursor-pointer select-none items-start gap-3 rounded-lg border p-4"
                    :class="form.engagements[index] ? 'border-primary/30 bg-primary/5' : ''"
                    @click="form.engagements = form.engagements.map((v, i) => i === index ? !v : v)"
                >
                    <div
                        class="mt-0.5 size-4 shrink-0 rounded-[4px] border shadow-xs flex items-center justify-center transition-colors"
                        :class="form.engagements[index]
                            ? 'bg-primary border-primary text-primary-foreground'
                            : 'border-input bg-background'"
                    >
                        <Check v-if="form.engagements[index]" class="size-3.5" />
                    </div>
                    <span class="text-sm font-normal leading-relaxed">{{ $t(key) }}</span>
                </div>

                <InputError :message="form.errors.engagements" />
            </div>

            <!-- ─── Signature électronique ─────────────────────────────────── -->
            <div class="grid gap-3">
                <div>
                    <Label for="signature">{{ $t('inscription_engagements.label_signature') }}</Label>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ $t('inscription_engagements.hint_signature') }}
                    </p>
                </div>

                <Input
                    id="signature"
                    v-model="form.signature"
                    type="text"
                    required
                    autocomplete="name"
                    :placeholder="$t('inscription_engagements.placeholder_signature')"
                />
                <InputError :message="form.errors.signature" />

                <!-- Prévisualisation de la signature -->
                <div
                    v-if="signaturePreview"
                    class="rounded-lg border border-dashed p-4 text-center"
                >
                    <p class="text-muted-foreground mb-1 text-xs">{{ $t('inscription_engagements.signature_preview') }}</p>
                    <p class="text-xl text-gray-700 dark:text-gray-300" style="font-family: 'Brush Script MT', cursive, serif;">
                        {{ signaturePreview }}
                    </p>
                </div>
            </div>

            <!-- ─── Actions ────────────────────────────────────────────────── -->
            <div class="flex flex-col gap-2">
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing || !tousCoches || !form.signature.trim()"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('inscription_engagements.submit') }}
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    class="w-full"
                    :disabled="form.processing"
                    @click="retourEtape1"
                >
                    {{ $t('inscription_engagements.back') }}
                </Button>
            </div>
        </form>
    </AuthBase>
</template>
