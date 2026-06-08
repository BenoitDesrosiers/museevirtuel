<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LocaleController from '@/actions/App/Http/Controllers/Settings/LocaleController';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

defineProps<Props>();

useI18n();

const page = usePage();
const currentLocale = computed(() => page.props.locale as string);

const handleLogout = () => {
    router.flushAll();
};

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
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ $t('nav.settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <div class="flex items-center gap-1 px-2 py-1">
        <button
            class="flex-1 rounded px-2 py-1 text-xs font-medium transition-colors"
            :class="
                currentLocale === 'fr'
                    ? 'bg-primary text-primary-foreground'
                    : 'hover:bg-muted'
            "
            @click.stop="switchLocale('fr')"
        >
            🇫🇷 FR
        </button>
        <button
            class="flex-1 rounded px-2 py-1 text-xs font-medium transition-colors"
            :class="
                currentLocale === 'en'
                    ? 'bg-primary text-primary-foreground'
                    : 'hover:bg-muted'
            "
            @click.stop="switchLocale('en')"
        >
            🇬🇧 EN
        </button>
    </div>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ $t('nav.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
