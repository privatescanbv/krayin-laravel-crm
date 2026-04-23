import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import vueDevTools from "vite-plugin-vue-devtools";
import laravel from "laravel-vite-plugin";
import path from "path";
import fs from "fs";

// ==== HTTPS Certs ====

const certDir = "/certs";
const keyFile = path.join(certDir, "local-key.pem");
const certFile = path.join(certDir, "local.pem");

const httpsConfig = fs.existsSync(keyFile) && fs.existsSync(certFile)
    ? {
        https: {
            key: fs.readFileSync(keyFile),
            cert: fs.readFileSync(certFile),
        },
    }
    : {};

// ==== CONFIG ====

export default defineConfig(({ mode }) => {
    const envDir   = "../../../";

    Object.assign(process.env, loadEnv(mode, envDir));

    process.env.LARAVEL_VITE_DETECT_TLS = "false";

    const vueDevtoolsBrowserExtensionOnly =
        process.env.VITE_VUE_DEVTOOLS_BROWSER_EXTENSION_ONLY === "true" ||
        process.env.VITE_VUE_DEVTOOLS_BROWSER_EXTENSION_ONLY === "1";

    const adminAppJsEntry =
        /[/\\]src[/\\]Resources[/\\]assets[/\\]js[/\\]app\.js$/;

    const adminPort = Number(process.env.VITE_ADMIN_PORT) || 5174;
    const host      = process.env.VITE_HMR_HOST || 'crm.local.privatescan.nl';
    const origin    = `https://${host}:${adminPort}`;

    return {
        envDir,

        // Base path voor assets:
        // - in dev: '/' (Vite dev server op :5174)
        // - in build: '/admin/build/' zodat CSS-url(...) naar /admin/build/assets/... wijst
        base: mode === 'production' ? '/admin/build/' : '/',

        define: {
            // Vue feature flags voor betere tree-shaking
            __VUE_OPTIONS_API__: 'true',
            __VUE_PROD_DEVTOOLS__: 'false',
            __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false',
        },

        server: {
            host: "0.0.0.0",
            port: adminPort,
            origin: origin, // Force correct origin for CSS URLs

            ...httpsConfig,

            // ❌ Geen HMR
            // hmr: false,
            // cors: true,
            // ws: false,
            injectClient: false,

            // HMR AAN, met correcte host/port
            hmr: {
                protocol: "wss",
                host: host,       // 'crm.local.privatescan.nl'
                port: adminPort,  // 5174
                overlay: false,   // geen error-overlay
            },

            cors: true,
            fs: {
                allow: [
                    path.resolve(__dirname, 'src/Resources'),
                    path.resolve(__dirname, 'resources'),
                    path.resolve(__dirname, 'packages'),
                    path.resolve(__dirname, 'node_modules'), // Allow node_modules for CSS imports
                    path.resolve(__dirname, '../../../'),
                ],
            },
        },

        build: {
            emptyOutDir: true,
        },

        plugins: [
            vue(),
            ...(mode === "development" && !vueDevtoolsBrowserExtensionOnly
                ? [
                    vueDevTools({
                        appendTo: adminAppJsEntry,
                    }),
                ]
                : []),
            laravel({
                publicDirectory: "../../../public",
                buildDirectory: "admin/build",

                hotFile: path.resolve(__dirname, '../../../storage/framework/admin-vite.hot'),

                input: [
                    "src/Resources/assets/css/app.css",
                    "src/Resources/assets/js/app.js",
                    "src/Resources/assets/js/chart.js",
                ],

                origin: origin, // Force correct origin
            }),
        ],
    };
});
