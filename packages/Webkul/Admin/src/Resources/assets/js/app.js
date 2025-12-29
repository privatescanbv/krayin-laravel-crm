/**
 * This will track all the images and fonts for publishing.
 */
import.meta.glob(["../images/**", "../fonts/**"]);

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
     * Convenience helper voor GVL-formulieren: accepteert 200 én 422 als "ok".
     */
    if (!window.privatescan.ensureGvlForm) {
        window.privatescan.ensureGvlForm = async (url, payload = {}, options = {}) => {
            const { errorPrefix = 'GVL formulier aanmaken/koppelen is mislukt.' } = options;

            const result = await window.privatescan.ajaxJson(url, payload, {
                method: 'POST',
                okStatuses: [200, 422], // 422 = "bestaat al" is hier ook ok
                errorPrefix,
            });

            return result.ok;
        };
    }

    /**
     * Check GVL form status and update button state.
     * Only enables button if status is 'step1'.
     *
     * @param {HTMLElement} button - The button element to check and update
     */
    if (!window.privatescan.checkGvlFormStatus) {
        window.privatescan.checkGvlFormStatus = async (button) => {
            const statusUrl = button.dataset.statusUrl;
            if (!statusUrl) {
                console.error('button not initialized: ' + button.dataset.statusUrl);
                return; // No status URL means no anamnesis/GVL form, button stays in initial state
            }

            // Store initial disabled state (based on portal account check)
            const initiallyDisabled = button.disabled;

            try {
                const response = await fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    console.warn('[InfoMail] Could not fetch GVL form status', response.status);
                    // Disable button on error (but only if it was initially enabled)
                    if (!initiallyDisabled) {
                        button.disabled = true;
                        button.classList.remove('text-activity-note-text', 'hover:text-blue-700');
                        button.classList.add('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                        button.title = 'Fout bij ophalen GVL formulier status.';
                    }
                    return;
                }

                const data = await response.json();
                const status = data.data?.status || data.data?.state || null;
                const statusLower = status ? status.toLowerCase() : '';

                // Helper: disable all buttons for same person/lead to keep state consistent if duplicates exist
                const disableAllMatchingButtons = (personId, leadId, title) => {
                    document
                        .querySelectorAll(`[data-person-id="${personId}"][data-lead-id="${leadId}"]`)
                        .forEach(btn => {
                            btn.disabled = true;
                            btn.classList.remove('text-activity-note-text', 'hover:text-blue-700');
                            btn.classList.add('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                            if (title) {
                                btn.title = title;
                            }
                        });
                };

                // If status is completed: always disable
                if (statusLower === 'completed') {
                    const title = 'Er bestaat al een afgerond GVL formulier.';
                    disableAllMatchingButtons(button.dataset.personId, button.dataset.leadId, title);
                    return;
                }

                // Otherwise: enable only if button was initially enabled (portal/email ok)
                if (!initiallyDisabled) {
                    button.disabled = false;
                    button.classList.remove('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                    button.classList.add('text-activity-note-text', 'hover:text-blue-700');
                    button.title = 'Stuur informatieve mail met GVL link';
                }
            } catch (error) {
                console.error('[InfoMail] Error checking GVL form status', error);
                // On error, disable button to be safe (but only if it was initially enabled)
                if (!initiallyDisabled) {
                    button.disabled = true;
                    button.classList.remove('text-activity-note-text', 'hover:text-blue-700');
                    button.classList.add('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                    button.title = 'Fout bij ophalen GVL formulier status.';
                }
            }
        };
    }

    /**
     * Handle info mail button click: ensure GVL form exists and open email dialog.
     *
     * @param {HTMLElement} button - The info mail button element
     * @param {string} createGvlFormUrl - URL to create/attach GVL form
     */
    if (!window.privatescan.handleInfoMailButtonClick) {
        window.privatescan.handleInfoMailButtonClick = async (button, createGvlFormUrl) => {
            const personId = button.dataset.personId;
            const leadId = button.dataset.leadId;
            const defaultEmail = button.dataset.defaultEmail;

            if (!personId || !leadId || !defaultEmail) {
                console.warn('[InfoMail] Missing required data attributes', { personId, leadId, defaultEmail });
                return false;
            }

            // Ensure GVL form exists for this lead/person
            const ok = await window.privatescan.ensureGvlForm(
                createGvlFormUrl,
                {
                    lead_id: parseInt(leadId),
                    person_id: parseInt(personId),
                },
                {
                    errorPrefix: 'GVL formulier aanmaken/koppelen is mislukt.',
                },
            );

            if (!ok) {
                return false;
            }

            // Prepare payload for email dialog
            const payload = {
                defaultEmail: defaultEmail,
                subject: 'Informatie over uw aanvraag',
                body: '',
                emails: [{ value: defaultEmail, is_default: true }],
                lead_id: leadId,
                person_id: personId,
                default_template: 'informatief-met-gvl',
                entity_type: 'gvl',
            };

            // Dispatch event to open mail dialog
            window.dispatchEvent(new CustomEvent('open-email-dialog', {
                detail: payload
            }));

            // Wait for modal to open, then set template
            const setupFormAndTemplate = (retries = 5) => {
                const form = document.querySelector('form[name="mail-action-form"]');
                if (!form && retries > 0) {
                    setTimeout(() => setupFormAndTemplate(retries - 1), 200);
                    return;
                }

                if (form) {
                    // Add lead_id and person_id to form for template resolution
                    let leadIdInput = form.querySelector('[name="lead_id"]');
                    if (!leadIdInput) {
                        leadIdInput = document.createElement('input');
                        leadIdInput.type = 'hidden';
                        leadIdInput.name = 'lead_id';
                        form.appendChild(leadIdInput);
                    }
                    leadIdInput.value = leadId;

                    let personIdInput = form.querySelector('[name="person_id"]');
                    if (!personIdInput) {
                        personIdInput = document.createElement('input');
                        personIdInput.type = 'hidden';
                        personIdInput.name = 'person_id';
                        form.appendChild(personIdInput);
                    }
                    personIdInput.value = personId;

                    // Store IDs in data attributes for loadTemplate to use
                    form.dataset.leadId = leadId;
                    form.dataset.personId = personId;

                    // Set template (this will trigger loadTemplate)
                    setTimeout(() => {
                        const templateSelect = document.querySelector('[name="email_template"]');
                        if (templateSelect) {
                            templateSelect.value = 'informatief';
                            templateSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }, 100);
                }
            };

            setTimeout(() => setupFormAndTemplate(), 300);
            return true;
        };
    }
}

export default app;

