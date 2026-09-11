import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        watch: {
            // Wayfinder supprime et recrée ces dossiers à chaque changement PHP,
            // ce qui provoque des erreurs HMR transitoires. On les exclut du watcher ;
            // le full-reload de laravel-vite-plugin (refresh: true) prend le relais.
            ignored: [
                path.resolve(process.cwd(), 'resources/js/actions'),
                path.resolve(process.cwd(), 'resources/js/routes'),
                path.resolve(process.cwd(), 'resources/js/wayfinder'),
            ],
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
