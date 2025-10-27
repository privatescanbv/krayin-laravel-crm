import { defineConfig } from 'vite';
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
            input: [
                'resources/css/app.css', 
                'resources/js/app.js',
                'packages/Webkul/Admin/src/Resources/assets/css/app.css',
                'packages/Webkul/Admin/src/Resources/assets/js/app.js'
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            'alpinejs': 'alpinejs/dist/module.esm.min.js'
        }
    },
    build: {
        sourcemap: command === 'build' ? true : false,
        minify: command === 'build' ? 'esbuild' : false,
        rollupOptions: {
            external: (id) => {
                // Don't process Vue components as external
                return false;
            }
        }
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
    },
}));
