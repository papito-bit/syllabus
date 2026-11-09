import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        // Les images du dossier public (comme /logo-istd.jpg) restent des URL ordinaires.
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
});
