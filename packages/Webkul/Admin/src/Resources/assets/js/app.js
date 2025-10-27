/**
 * Track all images and fonts for publishing.
 */
import.meta.glob(["../images/**", "../fonts/**"]);

/**
 * Vue main bundler.
 */
import { createApp } from "vue/dist/vue.esm-bundler";
import registerLookupComponent from "./components/lookup";

// Import all plugins and directives
import Admin from "./plugins/admin";
import Axios from "./plugins/axios";
import Emitter from "./plugins/emitter";
import Flatpickr from "./plugins/flatpickr";
import VeeValidate from "./plugins/vee-validate";
import CreateElement from "./plugins/createElement";
import Draggable from "./plugins/draggable";
import VueCal from "./plugins/vue-cal";
import Debounce from "./directives/debounce";
import DOMPurify from "./directives/dompurify";
import ToolTip from "./directives/tooltip";
import CallStatusIcon from "./components/CallStatusIcon.vue";
import Alpine from "alpinejs";
import registerEntitySelector from "./alpine/entity-selector";

/**
 * Create main Vue app.
 */
window.app = createApp({
        data() {
            return {
                isMenuActive: false,
                hoveringMenu: "",
            };
        },

        created() {
            window.addEventListener("click", this.handleFocusOut);
        },

        beforeUnmount() { // ✅ updated lifecycle hook for Vue 3
            window.removeEventListener("click", this.handleFocusOut);
        },

        methods: {
            onSubmit() {},

            onInvalidSubmit({ errors }) {
                setTimeout(() => {
                    const errorKeys = Object.entries(errors)
                        .map(([key, value]) => ({ key, value }))
                        .filter((error) => error.value.length);

                    const firstErrorElement = document.querySelector(
                        '[name="' + errorKeys[0].key + '"]'
                    );

                    if (firstErrorElement) {
                        firstErrorElement.scrollIntoView({
                            behavior: "smooth",
                            block: "center",
                        });
                    }
                }, 100);
            },

            handleMouseOver(event) {
                if (this.isMenuActive) return;
                const parent = event.currentTarget.parentElement;
                if (parent.classList.contains("sidebar-collapsed")) {
                    parent.classList.remove("sidebar-collapsed");
                    parent.classList.add("sidebar-not-collapsed");
                }
            },

            handleMouseLeave(event) {
                if (this.isMenuActive) return;
                const parent = event.currentTarget.parentElement;
                if (parent.classList.contains("sidebar-not-collapsed")) {
                    parent.classList.remove("sidebar-not-collapsed");
                    parent.classList.add("sidebar-collapsed");
                }
            },

            handleFocusOut(event) {
                const sidebar = this.$refs.sidebar;
                if (sidebar && !sidebar.contains(event.target)) {
                    this.isMenuActive = false;
                    const parent = sidebar.parentElement;
                    if (parent.classList.contains("sidebar-not-collapsed")) {
                        parent.classList.remove("sidebar-not-collapsed");
                        parent.classList.add("sidebar-collapsed");
                    }
                }
            }
        },
    });

    /**
     * Global plugin registration.
     */
    [
        Admin,
        Axios,
        Emitter,
        CreateElement,
        Draggable,
        Flatpickr,
        VeeValidate,
        VueCal,
    ].forEach((plugin) => window.app.use(plugin));

    /**
     * Global directives.
     */
    window.app.directive("debounce", Debounce);
    window.app.directive("safe-html", DOMPurify);
    window.app.directive("tooltip", ToolTip);

    window.app.component("CallStatusIcon", CallStatusIcon);

    /**
     * Register lookup (Vue component)
     */
    registerLookupComponent(window.app);

/**
 * ✅ Mount Vue after all components are registered
 */
function mountVueApp() {
    if (!window.app) {
        return;
    }

    if (window.app._instance) {
        return;
    }

    // Process any deferred components first
    if (window._deferredComponents && window._deferredComponents.length > 0) {
        window._deferredComponents.forEach(({ name, def }) => {
            window.app.component(name, def);
        });
        window._deferredComponents = [];
    }

    // Now mount Vue
    window.app.mount("#app");

    // Process any components that were registered after mount
    setTimeout(() => {
        if (window._deferredComponents && window._deferredComponents.length > 0) {
            window._deferredComponents.forEach(({ name, def }) => {
                window.app.component(name, def);
            });
            window._deferredComponents = [];
        }
    }, 100);
}

// Wait for DOM to be ready and all scripts to be processed
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Give Blade templates time to register components
        setTimeout(mountVueApp, 500);
    });
} else {
    // DOM is already ready, but still wait for scripts
    setTimeout(mountVueApp, 500);
}

/**
 * ✅ Then init Alpine (must come after Vue is mounted)
 */
registerEntitySelector(Alpine);

window.Alpine = Alpine;

// Start Alpine after Vue is mounted
function startAlpine() {
    Alpine.start();
}

// Wait for Vue to be mounted before starting Alpine
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(startAlpine, 600);
    });
} else {
    setTimeout(startAlpine, 600);
}


/**
 * Export (optional)
 */
export default window.app;
