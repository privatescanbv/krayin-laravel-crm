import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import fs from 'fs';
import path from 'path';

export default defineConfig(() => {
    const port = Number(process.env.VITE_PORT) || 5173;
    const hmrHost = process.env.VITE_HMR_HOST || 'crm.local.privatescan.nl';

    // Alleen HTTPS-certificaten instellen als de files echt bestaan.
    // Dit voorkomt ENOENT bij lokale builds buiten Docker.
    const httpsConfig =
        fs.existsSync('/certs/local-key.pem') && fs.existsSync('/certs/local.pem')
            ? {
                  https: {
                      key: fs.readFileSync('/certs/local-key.pem'),
                      cert: fs.readFileSync('/certs/local.pem'),
                  },
              }
            : {};

    return {
        server: {
            ...httpsConfig,

            host: '0.0.0.0',
            port,
            origin: `https://${hmrHost}:${port}`, // Force correct origin voor CSS/JS URLs

            // HMR aan, met correcte host/port (over wss)
            hmr: {
                protocol: 'wss',
                host: hmrHost,
                port,
                overlay: false,
            },

            cors: true,
        },

        plugins: [
            vue(),
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: false,
                detectTls: false,

                hotFile: path.resolve(__dirname, 'storage/framework/vite.hot'),
                origin: `https://${hmrHost}:${port}`, // Force correct origin
            }),
        ],
    };
});

