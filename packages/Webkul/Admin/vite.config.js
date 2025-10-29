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
            port: process.env.VITE_PORT || 5173,
            cors: true,
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

        plugins: [
            vue(),

            laravel({
                hotFile: "../../../storage/framework/vite.hot",
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
