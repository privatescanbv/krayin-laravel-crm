import { defineConfig } from 'vite';
import path from 'node:path';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ command }) => ({
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) => false,
                }
            }
        }),
        laravel({
            buildDirectory: 'admin/build',
            input: [
                'packages/Webkul/Admin/src/Resources/assets/css/app.css',
                'packages/Webkul/Admin/src/Resources/assets/js/app.js'
            ],
            refresh: [
                'resources/views/**',
                'packages/**/src/Resources/views/**',
            ],
            hotFile: 'sto' +
                'rage/framework/vite.hot',
        }),
    ],
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        fs: {
            // 👇 hiermee mag de devserver ook je packages-map uitlezen
            allow: [
                path.resolve(__dirname, 'resources'),
                path.resolve(__dirname, 'packages'),
            ],
        },
    },
}));
