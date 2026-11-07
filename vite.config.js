import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fontsource } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            fonts: [
                fontsource('Poppins', {
                    alias: 'poppins',
                    weights: [100, 200, 300, 400, 500, 600, 700, 800, 900],
                    preload: [{ weight: 400 }],
                    optimizedFallbacks: false,
                }),
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
