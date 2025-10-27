import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ command }) => ({
    plugins: [
        vue(),
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/js/app.js',
                'packages/Webkul/Admin/src/Resources/assets/css/app.css',
                'packages/Webkul/Admin/src/Resources/assets/js/app.js'
            ],
            refresh: true,
        }),
    ],
    build: {
        sourcemap: command === 'build' ? true : false,
        minify: command === 'build' ? 'esbuild' : false,
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
    },
}));
