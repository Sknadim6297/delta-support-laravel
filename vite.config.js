import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    experimental: {
        renderBuiltUrl(filename, { hostType }) {
            if (hostType === 'css') {
                return { relative: true };
            }
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/panel.css',
                'resources/css/panel-v2.css',
                'resources/css/features/rewards.css',
                'resources/css/site.css',
                'resources/css/auth-portal.css',
                'resources/css/register.css',
                'resources/js/app.js',
                'resources/js/panel.js',
                'resources/js/panel-v2.js',
                'resources/js/site.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
