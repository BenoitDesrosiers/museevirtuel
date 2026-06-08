<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
</script>

<template>
    <AuthBase
        :title="$t('auth.register.title')"
        :description="$t('auth.register.description')"
    >
        <Head :title="$t('auth.register.title')" />

        <div
            class="flex flex-col gap-4 text-center text-sm text-muted-foreground"
        >
            <p>
                {{ $t('auth.register.message') }}
            </p>
            <TextLink :href="login()" class="underline underline-offset-4">
                {{ $t('auth.register.back_to_login') }}
            </TextLink>
        </div>
    </AuthBase>
</template>
