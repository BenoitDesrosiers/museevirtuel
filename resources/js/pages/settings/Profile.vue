<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LocaleController from '@/actions/App/Http/Controllers/Settings/LocaleController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    temoinAssocieAGroupe: boolean;
};

const props = defineProps<Props>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.profile.page_title'),
        href: edit(),
    },
]);

const page = usePage();
const user = computed(() => page.props.auth.user);

const switchLocale = (locale: string) => {
    router.patch(
        LocaleController.update.url(),
        { locale },
        {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.profile.page_title')" />

        <h1 class="sr-only">{{ $t('settings.profile.heading') }}</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    :title="$t('settings.profile.section_title')"
                    :description="$t('settings.profile.section_description')"
                />

                <Form
                    v-bind="ProfileController.update()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="prenom">{{
                                $t('settings.profile.label_first_name')
                            }}</Label>
                            <Input
                                id="prenom"
                                class="mt-1 block w-full"
                                name="prenom"
                                :default-value="user.prenom"
                                required
                                autocomplete="given-name"
                                :placeholder="
                                    $t(
                                        'settings.profile.placeholder_first_name',
                                    )
                                "
                            />
                            <InputError class="mt-2" :message="errors.prenom" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nom">{{
                                $t('settings.profile.label_last_name')
                            }}</Label>
                            <Input
                                id="nom"
                                class="mt-1 block w-full"
                                name="nom"
                                :default-value="user.nom"
                                required
                                autocomplete="family-name"
                                :placeholder="
                                    $t('settings.profile.placeholder_last_name')
                                "
                            />
                            <InputError class="mt-2" :message="errors.nom" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{
                            $t('settings.profile.label_email')
                        }}</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            :placeholder="
                                $t('settings.profile.placeholder_email')
                            "
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            {{ $t('settings.profile.email_not_verified') }}
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ $t('settings.profile.resend_verification') }}
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            {{ $t('settings.profile.verification_link_sent') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing">{{
                            $t('settings.profile.save')
                        }}</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                {{ $t('settings.profile.saved') }}
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <!-- Language switcher -->
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    :title="$t('settings.profile.language_section_title')"
                    :description="
                        $t('settings.profile.language_section_description')
                    "
                />
                <div class="flex gap-2">
                    <Button
                        :variant="user.locale === 'fr' ? 'default' : 'outline'"
                        size="sm"
                        @click="switchLocale('fr')"
                    >
                        🇫🇷 Français
                    </Button>
                    <Button
                        :variant="user.locale === 'en' ? 'default' : 'outline'"
                        size="sm"
                        @click="switchLocale('en')"
                    >
                        🇬🇧 English
                    </Button>
                </div>
            </div>

            <DeleteUser
                v-if="
                    user.role === 'admin' ||
                    (user.role === 'personne_agee' &&
                        !props.temoinAssocieAGroupe)
                "
            />

            <div
                v-if="
                    user.role === 'personne_agee' && props.temoinAssocieAGroupe
                "
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-200/20 dark:bg-amber-800/10 dark:text-amber-300"
            >
                {{ $t('settings.delete_account.locked_by_groupe') }}
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
