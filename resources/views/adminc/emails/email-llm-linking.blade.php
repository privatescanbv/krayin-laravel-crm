<script type="text/x-template" id="v-email-llm-linking-template">
    <div class="flex flex-col gap-2">
        <!-- Error -->
        <div v-if="errorMessage" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
            @{{ errorMessage }}
        </div>

        <!-- Status (no suggestions, or informational) -->
        <div
            v-if="statusMessage && !suggestions.length && !isRunning"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200"
        >
            @{{ statusMessage }}
        </div>

        <!-- Suggestions -->
        <template v-if="suggestions.length">
            <div class="flex flex-col gap-1.5">
                <div
                    v-if="activeSuggestionsHeading"
                    class="text-sm font-semibold text-gray-800 dark:text-gray-300"
                >
                    @{{ activeSuggestionsHeading }}
                </div>
                <div
                    v-for="(s, i) in suggestions"
                    :key="i"
                    class="flex items-center justify-between gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/60"
                >
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300': s.type === 'sales',
                            'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300': s.type === 'lead',
                            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300': s.type === 'person',
                            'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300': s.type === 'order',
                            'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200': s.type === 'clinic',
                        }"
                    >@{{ s.label }}</span>
                    <button
                        type="button"
                        class="primary-button !py-1 !px-2 !text-xs"
                        :disabled="applyingIndex === i"
                        @click="applyLink(s, i)"
                    >
                        <span v-if="applyingIndex === i" class="flex items-center gap-1">
                            <span class="h-3 w-3 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Bezig…
                        </span>
                        <span v-else>Koppelen</span>
                    </button>
                </div>
            </div>
        </template>

        <!-- Run button + spinner -->
        <div v-if="showRunButton" class="flex flex-col gap-1.5">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="secondary-button"
                    :disabled="isRunning"
                    @click="runExtraction(false)"
                >
                    <span v-if="isRunning && !forceRefreshNext" class="flex items-center gap-1.5">
                        <span class="h-3 w-3 animate-spin rounded-full border-2 border-gray-400 border-t-gray-700 dark:border-gray-600 dark:border-t-gray-200"></span>
                        @{{ runningLabel }}
                    </span>
                    <span v-else-if="isRunning">@{{ runningLabel }}</span>
                    <span v-else>@{{ primaryButtonLabel }}</span>
                </button>
                <button
                    v-if="hasCachedLlmExtraction && !isRunning"
                    type="button"
                    class="text-xs text-gray-600 underline hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                    @click="runExtraction(true)"
                >
                    Opnieuw analyseren
                </button>
            </div>
            <p
                v-if="isRunning && forceRefreshNext"
                class="text-xs text-gray-500 dark:text-gray-400"
            >
                Nieuwe AI-analyse kan tot een minuut duren…
            </p>
            <p
                v-else-if="hasCachedLlmExtraction && !isRunning"
                class="text-xs text-gray-500 dark:text-gray-400"
            >
                Eerdere analyse beschikbaar,laden gaat direct. Gebruik “Opnieuw analyseren” voor een nieuwe AI-run.<br/>
                <i>(Gemaakt voor forwards)</i>
            </p>
            <p
                v-else-if="!hasCachedLlmExtraction && isRunning"
                class="text-xs text-gray-500 dark:text-gray-400"
            >
                AI-analyse kan tot een minuut duren…
            </p>
        </div>

        <details
            v-if="showTechnicalOutput && hasTechnicalDetails"
            class="rounded-md border border-gray-200 bg-gray-50 text-sm dark:border-gray-700 dark:bg-gray-800/40"
        >
            <summary class="cursor-pointer select-none px-3 py-2 font-medium text-gray-700 dark:text-gray-300">
                Technische details
            </summary>
            <div class="flex flex-col gap-3 border-t border-gray-200 px-3 py-2 dark:border-gray-700">
                <dl
                    v-if="llmMeta.status"
                    class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs text-gray-600 dark:text-gray-400"
                >
                    <dt class="font-medium text-gray-700 dark:text-gray-300">Status</dt>
                    <dd class="text-gray-800 dark:text-gray-200">@{{ statusLabel(llmMeta.status) }}</dd>
                    <template v-if="llmMeta.duration_ms">
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Duur</dt>
                        <dd class="text-gray-800 dark:text-gray-200">@{{ Math.round(llmMeta.duration_ms / 1000) }}s</dd>
                    </template>
                    <template v-if="llmMeta.last_run_at">
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Laatste run</dt>
                        <dd class="text-gray-800 dark:text-gray-200">@{{ formatDateTime(llmMeta.last_run_at) }}</dd>
                    </template>
                    <template v-if="llmMeta.from_cache">
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Bron</dt>
                        <dd class="text-gray-800 dark:text-gray-200">Cache</dd>
                    </template>
                    <template v-if="llmMeta.error">
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Fout</dt>
                        <dd class="text-red-700 dark:text-red-300">@{{ llmMeta.error }}</dd>
                    </template>
                </dl>

                <div v-if="llmMeta.links && Object.keys(llmMeta.links).length" class="text-xs">
                    <div class="font-medium text-gray-700 dark:text-gray-300">CRM-koppelingen (model)</div>
                    <ul class="mt-1 text-gray-600 dark:text-gray-400">
                        <li v-for="link in displayLinks" :key="link.key">
                            @{{ link.label }} #@{{ link.id }}
                        </li>
                    </ul>
                </div>

                <div>
                    <div class="font-medium text-gray-700 dark:text-gray-300">Gevonden e-mailadressen</div>
                    <ul
                        v-if="llmSenders.length"
                        class="mt-1.5 flex flex-col gap-1.5"
                    >
                        <li
                            v-for="sender in llmSenders"
                            :key="sender.email + '-' + (sender.role || '')"
                            class="rounded border border-gray-200 bg-white px-2 py-1.5 dark:border-gray-600 dark:bg-gray-900"
                        >
                            <div class="font-medium text-gray-900 dark:text-gray-100">@{{ sender.email }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="sender.name">@{{ sender.name }} · </span>
                                @{{ roleLabel(sender.role) }} · @{{ Math.round((sender.confidence || 0) * 100) }}%
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Geen afzenders gedetecteerd door het model.
                    </p>
                </div>
            </div>
        </details>

    </div>
</script>

<script type="module">
    app.component('v-email-llm-linking', {
        template: '#v-email-llm-linking-template',

        props: {
            emailId: { type: Number, required: true },
            defaultPrompt: { type: String, required: true },
            initialSuggestions: { type: Array, default: () => [] },
            suggestionsHeading: { type: String, default: null },
            hasCachedLlmExtraction: { type: Boolean, default: false },
            initialLlmMetadata: { type: Object, default: null },
            showTechnicalOutput: { type: Boolean, default: false },
            showRunButton: { type: Boolean, default: true },
            runRoute: { type: String, required: true },
            applySuggestionRoute: { type: String, required: true },
        },

        emits: ['linked', 'suggestions-changed'],

        data() {
            return {
                isRunning: false,
                applyingIndex: null,
                suggestions: Array.isArray(this.initialSuggestions) ? [...this.initialSuggestions] : [],
                activeSuggestionsHeading: this.suggestionsHeading,
                errorMessage: null,
                statusMessage: null,
                forceRefreshNext: false,
                llmMeta: this.normalizeLlmMeta(this.initialLlmMetadata),
                llmSenders: this.extractSenders(this.initialLlmMetadata),
            };
        },

        computed: {
            hasTechnicalDetails() {
                return !!(
                    this.llmMeta?.status
                    || this.llmSenders.length
                    || this.llmMeta?.error
                );
            },

            displayLinks() {
                const links = this.llmMeta?.links || {};
                const labels = {
                    person_id: 'Contact',
                    lead_id: 'Lead',
                    sales_lead_id: 'Sales',
                    clinic_id: 'Kliniek',
                    order_id: 'Order',
                    activity_id: 'Activiteit',
                };

                return Object.entries(links).map(([key, id]) => ({
                    key,
                    id,
                    label: labels[key] || key,
                }));
            },

            primaryButtonLabel() {
                return this.hasCachedLlmExtraction
                    ? 'Toon AI-resultaat'
                    : 'AI afzender analyseren';
            },

            runningLabel() {
                return this.forceRefreshNext ? 'Opnieuw analyseren…' : 'Analyseren…';
            },
        },

        watch: {
            suggestions: {
                handler(suggestions) {
                    this.$emit('suggestions-changed', suggestions.length);
                },
                immediate: true,
            },
        },

        methods: {
            async runExtraction(forceRefresh = false) {
                this.forceRefreshNext = forceRefresh;
                this.isRunning = true;
                this.errorMessage = null;
                this.statusMessage = null;
                const previousSuggestions = [...this.suggestions];

                try {
                    const response = await this.$axios.post(this.runRoute, {
                        system_prompt: this.defaultPrompt,
                        apply_links: false,
                        force_refresh: forceRefresh,
                    }, { timeout: 200000 });

                    const result = response.data?.result || {};
                    const nextSuggestions = Array.isArray(result.suggestions) ? result.suggestions : [];

                    if (nextSuggestions.length > 0) {
                        this.suggestions = nextSuggestions;
                        this.activeSuggestionsHeading = null;
                    } else if (previousSuggestions.length > 0) {
                        this.suggestions = previousSuggestions;
                    } else {
                        this.suggestions = [];
                    }

                    this.statusMessage = response.data?.message || null;

                    this.llmMeta = this.normalizeLlmMeta(result);
                    this.llmSenders = this.extractSenders(result);

                    if (result.status === 'error' && result.error) {
                        this.errorMessage = result.error;
                    }

                    const flashType = result.status === 'error'
                        ? 'error'
                        : (nextSuggestions.length > 0 ? 'success' : 'warning');

                    if (response.data?.message) {
                        this.$emitter.emit('add-flash', { type: flashType, message: response.data.message });
                    }
                } catch (error) {
                    this.suggestions = previousSuggestions;
                    this.errorMessage = error.response?.data?.message || error.message || 'AI-analyse mislukt';
                    this.$emitter.emit('add-flash', { type: 'error', message: this.errorMessage });
                } finally {
                    this.isRunning = false;
                    this.forceRefreshNext = false;
                }
            },

            normalizeLlmMeta(source) {
                if (! source || typeof source !== 'object') {
                    return {};
                }

                const meta = source.metadata && typeof source.metadata === 'object'
                    ? source.metadata
                    : {};

                return {
                    status: source.status || meta.status || null,
                    duration_ms: source.duration_ms ?? meta.duration_ms ?? null,
                    last_run_at: source.last_run_at || meta.last_run_at || null,
                    links: source.links || meta.links || {},
                    error: source.error || meta.error || null,
                    from_cache: !!(source.from_cache ?? meta.from_cache),
                };
            },

            extractSenders(source) {
                if (! source || typeof source !== 'object') {
                    return [];
                }

                const meta = source.metadata && typeof source.metadata === 'object'
                    ? source.metadata
                    : {};
                const senders = source.senders ?? meta.senders;

                return Array.isArray(senders) ? [...senders] : [];
            },

            statusLabel(status) {
                const labels = {
                    linked: 'Gekoppeld',
                    matched: 'Match gevonden',
                    no_match: 'Geen CRM-match',
                    no_senders: 'Geen afzenders',
                    error: 'Fout',
                };

                return labels[status] || status || '—';
            },

            roleLabel(role) {
                const labels = {
                    original_sender: 'Oorspronkelijke afzender',
                    forwarder: 'Doorstuurder',
                    other: 'Overig',
                };

                return labels[role] || role || '—';
            },

            formatDateTime(value) {
                if (! value) {
                    return '';
                }

                return new Date(value).toLocaleString('nl-NL');
            },

            async applyLink(suggestion, index) {
                this.applyingIndex = index;

                try {
                    const response = await this.$axios.post(this.applySuggestionRoute, {
                        links: suggestion.links,
                    });

                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    this.$emit('linked', response.data.email);
                    window.location.reload();
                } catch (error) {
                    this.errorMessage = error.response?.data?.message || 'Koppelen mislukt';
                } finally {
                    this.applyingIndex = null;
                }
            },
        },
    });
</script>
