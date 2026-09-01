/**
 * Static images for publishing are declared via `assets` in vite.config.js (laravel-vite-plugin 3 + Vite 8).
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

    created() {
        window.addEventListener('click', this.handleFocusOut);
    },

    beforeDestroy() {
        window.removeEventListener('click', this.handleFocusOut);
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

        handleMouseOver(event) {
            if (this.isMenuActive) {
                return;
            }

            const parentElement = event.currentTarget.parentElement;

            if (parentElement.classList.contains('sidebar-collapsed')) {
                parentElement.classList.remove('sidebar-collapsed');

                parentElement.classList.add('sidebar-not-collapsed');
            }

        },

        handleMouseLeave(event) {
            if (this.isMenuActive) {
                return;
            }

            const parentElement = event.currentTarget.parentElement;

            if (parentElement.classList.contains('sidebar-not-collapsed')) {
                parentElement.classList.remove('sidebar-not-collapsed');

                parentElement.classList.add('sidebar-collapsed');
            }
        },

        handleFocusOut(event) {
            const sidebar = this.$refs.sidebar;

            if (
                sidebar &&
                !sidebar.contains(event.target)
            ) {
                this.isMenuActive = false;

                const parentElement = sidebar.parentElement;

                if (parentElement.classList.contains('sidebar-not-collapsed')) {
                    parentElement.classList.remove('sidebar-not-collapsed');

                    parentElement.classList.add('sidebar-collapsed');
                }
            }
        },
    },
});

/**
 * Keep a lightweight `trans()` available in Vue templates.
 * Many Blade-driven Vue templates use bindings like `:label="trans('admin::app...')"`
 * which compile to `_ctx.trans(...)`.
 */
app.config.globalProperties.trans = (key, replacements = {}) => {
    const k = typeof key === "string" ? key : String(key ?? "");

    try {
        const fn =
            typeof window !== "undefined"
                ? (typeof window.trans === "function" ? window.trans : (typeof window.__ === "function" ? window.__ : null))
                : null;

        const out = fn ? fn(k, replacements) : k;

        // Fallback replacement if the delegated translator didn't handle it.
        if (out && replacements && typeof replacements === "object") {
            let str = String(out);
            for (const [rk, rv] of Object.entries(replacements)) {
                str = str.replaceAll(`:${rk}`, String(rv));
            }
            return str;
        }

        return out;
    } catch (e) {
        return k;
    }
};

/**
 * Global plugins registration.
 */
import Admin from "./plugins/admin";
import Axios from "./plugins/axios";
import Emitter from "./plugins/emitter";
import Flatpickr from "./plugins/flatpickr";
import VeeValidate from "./plugins/vee-validate";
import CreateElement from "./plugins/createElement";
import Draggable from "./plugins/draggable";
import VueCal from "./plugins/vue-cal";

[
    Admin,
    Axios,
    Emitter,
    CreateElement,
    Draggable,
    Flatpickr,
    VeeValidate,
    VueCal,
].forEach((plugin) => app.use(plugin));

/**
 * Global directives.
 */
import Debounce from "./directives/debounce";
import DOMPurify from "./directives/dompurify";
import ToolTip from "./directives/tooltip";
import CallStatusIcon from "./components/CallStatusIcon.vue";
import ReadMore from "./components/ReadMore.vue";

app.directive("debounce", Debounce);
app.directive("safe-html", DOMPurify);
app.directive("tooltip", ToolTip);

app.component("CallStatusIcon", CallStatusIcon);
app.component("read-more", ReadMore);

/**
 * Globale helpers voor AJAX-calls (beschikbaar op admin frontend).
 * Deze volgen dezelfde structuur als de root-app helpers, maar zijn lokaal
 * gedefinieerd zodat ze altijd beschikbaar zijn wanneer de admin-bundle geladen is.
 */
if (typeof window !== 'undefined') {
    window.privatescan = window.privatescan || {};

    if (!window.privatescan.getCsrfToken) {
        window.privatescan.getCsrfToken = () => {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (metaToken) return metaToken;

            const inputToken = document.querySelector('input[name="_token"]')?.value;
            if (inputToken) return inputToken;

            return '';
        };
    }

    /**
     * Globale AJAX helper met standaard foutafhandeling.
     *
     * Options:
     * - method: HTTP-methode (default: 'POST')
     * - okStatuses: array van statuscodes die als "ok" worden gezien (default: [200])
     * - errorPrefix: tekst die voor de foutmelding wordt gezet in alerts/logs
     * - silent: als true, geen alert tonen bij fout (alleen console)
     *
     * Retourneert een object: { ok: boolean, status: number, data: any|null }
     */
    if (!window.privatescan.ajaxJson) {
        window.privatescan.ajaxJson = async (url, body = null, options = {}) => {
            const {
                method = 'POST',
                okStatuses = [200],
                errorPrefix = 'Er is een fout opgetreden.',
                silent = false,
                headers: extraHeaders = {},
            } = options;

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'X-CSRF-TOKEN': window.privatescan.getCsrfToken(),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...extraHeaders,
                    },
                    body: body !== null ? JSON.stringify(body) : undefined,
                });

                const status = response.status;
                let data = null;

                try {
                    data = await response.clone().json();
                } catch (e) {
                    data = null;
                }

                const isOk = okStatuses.includes(status);

                if (!isOk) {
                    const message = (data && data.message) ? data.message : errorPrefix;
                    console.error('[AJAX] Request failed', { url, status, data, message });
                    if (!silent) {
                        alert(message);
                    }
                }

                return { ok: isOk, status, data };
            } catch (error) {
                console.error('[AJAX] Request error', { url, error });
                if (!silent) {
                    alert(errorPrefix + ' Backend niet bereikbaar.');
                }

                return { ok: false, status: 0, data: null };
            }
        };
    }

    /**
     * Helper voor "confirm + POST JSON + success feedback + redirect/reload".
     *
     * opts:
     * - confirmText: string|null
     * - successMessage: string|null (fallback als response.data.message ontbreekt)
     * - onSuccess: 'reload' | 'redirect' | null
     * - redirectUrl: string|null (alleen bij onSuccess='redirect')
     * - okStatuses: number[] (default [200])
     * - errorPrefix: string
     */
    if (!window.privatescan.runJsonAction) {
        window.privatescan.runJsonAction = async (url, payload = {}, opts = {}) => {
            const {
                confirmText = null,
                successMessage = null,
                onSuccess = null,
                redirectUrl = null,
                okStatuses = [200],
                errorPrefix = 'Er is een fout opgetreden.',
            } = opts;

            if (confirmText && !window.confirm(confirmText)) {
                return { ok: false, status: 0, data: null, cancelled: true };
            }

            const result = await window.privatescan.ajaxJson(url, payload, {
                method: 'POST',
                okStatuses,
                errorPrefix,
            });

            if (result.ok) {
                const msg = result?.data?.message || successMessage;
                if (msg) {
                    alert(msg);
                }

                if (onSuccess === 'reload') {
                    window.location.reload();
                } else if (onSuccess === 'redirect' && redirectUrl) {
                    window.location.href = redirectUrl;
                }
            }

            return result;
        };
    }
}

(async () => {
    const cfg = typeof window !== "undefined" ? window.__markerIo : null;

    if (!cfg?.enabled || !cfg?.project) {
        return;
    }

    try {
        const { default: markerSDK } = await import("@marker.io/browser");

        await markerSDK.loadWidget({
            project: cfg.project,
            useNativeScreenshot: true,
        });
    } catch (e) {
        if (import.meta.env.DEV) {
            console.warn("[Marker.io] widget not loaded:", e);
        }
    }
})();

export default app;

