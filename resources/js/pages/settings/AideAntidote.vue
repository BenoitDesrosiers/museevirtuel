<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle, XCircle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { help } from '@/routes/antidote';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.antidote.page_title'),
        href: help(),
    },
]);

const antidoteActif = ref<boolean | null>(null);

onMounted(() => {
    setTimeout(() => {
        antidoteActif.value =
            document.getElementById('antidoteapi_jsconnect_actif') !== null ||
            typeof (
                window as Window & { activeAntidoteAPI_JSConnect?: unknown }
            ).activeAntidoteAPI_JSConnect === 'function';
    }, 800);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.antidote.page_title')" />

        <h1 class="sr-only">{{ $t('settings.antidote.page_title') }}</h1>

        <SettingsLayout>
            <div class="space-y-8">
                <Heading
                    variant="small"
                    :title="$t('settings.antidote.heading_title')"
                    :description="$t('settings.antidote.heading_description')"
                />

                <!-- Statut de détection -->
                <div
                    class="flex items-center gap-3 rounded-lg border px-4 py-3 text-sm"
                    :class="
                        antidoteActif === null
                            ? 'border-muted bg-muted/40 text-muted-foreground'
                            : antidoteActif
                              ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200'
                              : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200'
                    "
                >
                    <CheckCircle
                        v-if="antidoteActif"
                        class="h-5 w-5 shrink-0 text-green-600"
                    />
                    <XCircle
                        v-else-if="antidoteActif === false"
                        class="h-5 w-5 shrink-0 text-amber-500"
                    />
                    <span v-else class="h-5 w-5 shrink-0" />

                    <span v-if="antidoteActif === null">{{
                        $t('settings.antidote.status_checking')
                    }}</span>
                    <span v-else-if="antidoteActif">{{
                        $t('settings.antidote.status_active')
                    }}</span>
                    <span v-else>{{
                        $t('settings.antidote.status_inactive')
                    }}</span>
                </div>

                <!-- Edge -->
                <section class="space-y-3">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <img
                            src="/vendor/antidote/images/icone-antidote.svg"
                            class="h-5 w-5"
                            alt=""
                        />
                        Microsoft Edge
                    </h2>
                    <ol
                        class="ml-4 list-decimal space-y-2 text-sm text-muted-foreground"
                    >
                        <li>{{ $t('settings.antidote.edge.step1') }}</li>
                        <li>
                            {{ $t('settings.antidote.edge.step2') }}
                            <code
                                class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground"
                                >edge://extensions</code
                            >
                        </li>
                        <li>{{ $t('settings.antidote.edge.step3') }}</li>
                        <li>{{ $t('settings.antidote.edge.step4') }}</li>
                        <li>{{ $t('settings.antidote.edge.step5') }}</li>
                    </ol>
                    <p class="ml-4 text-xs text-muted-foreground italic">
                        💡 {{ $t('settings.antidote.edge.tip') }}
                    </p>
                </section>

                <div class="border-t border-input" />

                <!-- Chrome -->
                <section class="space-y-3">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <img
                            src="/vendor/antidote/images/icone-antidote.svg"
                            class="h-5 w-5"
                            alt=""
                        />
                        Google Chrome
                    </h2>
                    <ol
                        class="ml-4 list-decimal space-y-2 text-sm text-muted-foreground"
                    >
                        <li>{{ $t('settings.antidote.chrome.step1') }}</li>
                        <li>
                            {{ $t('settings.antidote.chrome.step2') }}
                            <code
                                class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground"
                                >chrome://extensions</code
                            >
                        </li>
                        <li>{{ $t('settings.antidote.chrome.step3') }}</li>
                        <li>{{ $t('settings.antidote.chrome.step4') }}</li>
                        <li>{{ $t('settings.antidote.chrome.step5') }}</li>
                    </ol>
                    <p class="ml-4 text-xs text-muted-foreground italic">
                        💡 {{ $t('settings.antidote.chrome.tip') }}
                    </p>
                </section>

                <div class="border-t border-input" />

                <!-- Firefox -->
                <section class="space-y-3">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <img
                            src="/vendor/antidote/images/icone-antidote.svg"
                            class="h-5 w-5"
                            alt=""
                        />
                        Mozilla Firefox
                    </h2>
                    <ol
                        class="ml-4 list-decimal space-y-2 text-sm text-muted-foreground"
                    >
                        <li>{{ $t('settings.antidote.firefox.step1') }}</li>
                        <li>
                            {{ $t('settings.antidote.firefox.step2') }}
                            <code
                                class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground"
                                >about:addons</code
                            >
                        </li>
                        <li>{{ $t('settings.antidote.firefox.step3') }}</li>
                        <li>{{ $t('settings.antidote.firefox.step4') }}</li>
                        <li>{{ $t('settings.antidote.firefox.step5') }}</li>
                    </ol>
                    <p class="ml-4 text-xs text-muted-foreground italic">
                        💡 {{ $t('settings.antidote.firefox.tip') }}
                    </p>
                </section>

                <div class="border-t border-input" />

                <!-- Vérification console -->
                <section class="space-y-2">
                    <h2 class="text-sm font-semibold">
                        {{ $t('settings.antidote.verify_title') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ $t('settings.antidote.verify_description') }}
                    </p>
                    <pre
                        class="rounded-md bg-muted px-4 py-3 font-mono text-xs text-foreground"
                    >
typeof window.activeAntidoteAPI_JSConnect</pre
                    >
                    <p class="text-sm text-muted-foreground">
                        {{ $t('settings.antidote.verify_result_ok') }}
                        <code
                            class="rounded bg-green-100 px-1.5 py-0.5 font-mono text-xs text-green-800 dark:bg-green-900 dark:text-green-200"
                            >"function"</code
                        >
                        {{ $t('settings.antidote.verify_result_ok_suffix') }}
                    </p>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
