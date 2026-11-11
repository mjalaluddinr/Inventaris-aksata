import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            fonts: [
                local('Poppins', {
                    alias: 'poppins',
                    variants: [400, 500, 600, 700, 800].map((weight) => ({
                        weight,
                        src: `node_modules/@fontsource/poppins/files/poppins-latin-${weight}-normal.woff2`,
                    })),
                    preload: [400, 500, 600, 700, 800].map((weight) => ({ weight })),
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
