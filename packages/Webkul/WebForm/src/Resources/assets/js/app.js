/**
 * Voeg `assets: ['src/Resources/assets/images/**']` toe aan laravel() in vite.config.js
 * als je statische images onder assets wilt publiceren (Vite 8 + laravel-vite-plugin 3).
 */

/**
 * Main vue bundler.
 */
import { createApp } from "vue/dist/vue.esm-bundler";

/**
 * Main root application registry.
 */
window.app = createApp({
    data() {
        return {
            isMenuActive: false,

            hoveringMenu: '',
        };
    },

    methods: {
        onSubmit() {},

        onInvalidSubmit({ values, errors, results }) {
            setTimeout(() => {
                const errorKeys = Object.entries(errors)
                    .map(([key, value]) => ({ key, value }))
                    .filter(error => error["value"].length);

                let firstErrorElement = document.querySelector('[name="' + errorKeys[0]["key"] + '"]');

                firstErrorElement.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
            }, 100);
        },
    },
});

/**
 * Global plugins registration.
 */
import Axios from "./plugins/axios";
import Emitter from "./plugins/emitter";
import Flatpickr from "./plugins/flatpickr";
import VeeValidate from "./plugins/vee-validate";
[
    Axios,
    Emitter,
    Flatpickr,
    VeeValidate,
].forEach((plugin) => app.use(plugin));

export default app;
