import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import path from "path";

export default defineConfig(({ mode }) => {
    const envDir = "../../../";

    Object.assign(process.env, loadEnv(mode, envDir));

    return {
        build: {
            emptyOutDir: true,
        },

        envDir,

        server: {
            host: process.env.VITE_HOST || "localhost",
            // Use a dedicated dev port for the Admin package so the correct server serves Admin assets
            port: process.env.VITE_ADMIN_PORT || 5174,
            cors: true,
            hmr: {
                host: process.env.VITE_HOST || 'localhost',
                port: process.env.VITE_ADMIN_PORT || 5174,
            },
            fs: {
                // 👇 hiermee mag de devserver ook je packages-map uitlezen
                allow: [
                    path.resolve(__dirname, 'resources'),
                    path.resolve(__dirname, 'packages'),
                ],
            },
        },

        plugins: [
            vue(),

            laravel({
                // Separate hotfile so Laravel can distinguish this dev server from the root one
                hotFile: "../../../public/admin-vite.hot",
                publicDirectory: "../../../public",
                buildDirectory: "admin/build",
                input: [
                    "src/Resources/assets/css/app.css",
                    "src/Resources/assets/js/app.js",
                    "src/Resources/assets/js/chart.js",
                ],
                refresh: true,
            }),
        ],
        experimental: {
            renderBuiltUrl(filename, { hostId, hostType, type }) {
                if (hostType === "css") {
                    return path.basename(filename);
                }
            },
        },
    };
});
