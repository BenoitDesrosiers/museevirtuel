import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { createI18n } from 'vue-i18n';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';
import { vTooltip } from '@/directives/tooltip';
import en from '@/i18n/en.json';
import fr from '@/i18n/fr.json';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const initialLocale = (props.initialPage.props as { locale?: string }).locale ?? 'fr';

        const i18n = createI18n({
            legacy: false,
            locale: initialLocale,
            fallbackLocale: 'fr',
            messages: { fr, en },
        });

        // Synchronise la locale i18n lors des navigations Inertia (ex: après changement dans profil)
        router.on('navigate', (event) => {
            const newLocale = (event.detail.page.props as { locale?: string }).locale;

            if (newLocale && newLocale !== i18n.global.locale.value) {
                (i18n.global.locale as { value: string }).value = newLocale;
            }
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .directive('tooltip', vTooltip)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
