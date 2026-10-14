<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppearanceTabs from '@/components/app/AppearanceTabs.vue';
import Heading from '@/components/shared/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.appearance.page_title'),
        href: edit(),
    },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.appearance.page_title')" />

        <h1 class="sr-only">{{ $t('settings.appearance.heading_title') }}</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    :title="$t('settings.appearance.heading_title')"
                    :description="$t('settings.appearance.section_description')"
                />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
