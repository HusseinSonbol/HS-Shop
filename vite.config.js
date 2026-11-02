import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin-bootstrap.css',
                'resources/css/admin-bootstrap.rtl.css',
                'resources/css/storefront-bootstrap5.css',
                'resources/js/app.js',
                'resources/js/storefront-bootstrap5.js',
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
