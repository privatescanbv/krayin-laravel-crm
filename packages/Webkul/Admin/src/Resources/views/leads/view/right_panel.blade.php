@if (($showLeadAiSummary ?? false) && isset($lead))
    <v-lead-ai-summary
        summary-url="{{ route('admin.leads.ai-summary.show', $lead->id) }}"
        generate-url="{{ route('admin.leads.ai-summary.generate', $lead->id) }}"
        feedback-url="{{ route('admin.leads.ai-feedback.store', $lead->id) }}"
        :can-edit="{{ bouncer()->hasPermission('leads.edit') ? 'true' : 'false' }}"
    ></v-lead-ai-summary>

    @pushOnce('scripts', 'lead-ai-summary')
    <script type="text/x-template" id="v-lead-ai-summary-template">
        <div class="flex flex-col gap-4 p-4 text-gray-700 dark:text-gray-200">
            <section class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900 dark:text-white">AI-samenvatting</h2>

                    <button
                        v-if="canEdit"
                        type="button"
                        class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-gray-800 dark:hover:text-white"
                        :disabled="isGenerating"
                        title="Samenvatting opnieuw genereren"
                        @click="generate"
                    >
                        <span
                            class="icon-refresh text-base"
                            :class="{ 'animate-spin': isGenerating || ['queued', 'processing', 'retrying'].includes(summary?.status) }"
                        ></span>
                    </button>
                </div>

                <div v-if="isLoading" class="flex flex-col gap-2">
                    <div class="h-3 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-3 w-5/6 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-3 w-3/5 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>

                <template v-else>
                    <div
                        v-if="summary && summary.status === 'failed'"
                        class="rounded border border-orange-200 bg-orange-50 p-2 text-xs text-orange-800 dark:border-orange-800 dark:bg-orange-950/30 dark:text-orange-200"
                    >
                        Vernieuwen is mislukt. Een eerdere geldige samenvatting blijft zichtbaar.
                    </div>

                    <div
                        v-if="summary && ['queued', 'processing', 'retrying'].includes(summary.status)"
                        class="rounded bg-blue-50 p-2 text-xs text-blue-700 dark:bg-blue-950/30 dark:text-blue-200"
                    >
                        @{{ {
                            queued: 'De samenvatting staat in de wachtrij…',
                            retrying: 'Nieuwe poging wordt gepland na een verbindingsprobleem…',
                        }[summary.status] || 'De samenvatting wordt bijgewerkt…' }}
                    </div>

                    <p
                        v-if="summary && summary.summary"
                        class="max-h-24 overflow-hidden break-words text-sm leading-5 text-gray-700 dark:text-gray-200"
                    >
                        @{{ summary.summary }}
                    </p>

                    <div
                        v-else-if="!summary || !['queued', 'processing', 'retrying'].includes(summary.status)"
                        class="rounded bg-gray-50 p-3 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                    >
                        Er is nog geen AI-samenvatting beschikbaar.
                    </div>

                    <div
                        v-if="summary && (summary.next_action_title || summary.next_action_reason)"
                        class="flex flex-col gap-1 rounded border border-gray-200 p-3 dark:border-gray-700"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <strong class="text-xs text-gray-900 dark:text-white">Volgende actie</strong>
                            <span
                                v-if="summary.priority"
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="priorityClass(summary.priority)"
                            >
                                @{{ priorityLabel(summary.priority) }}
                            </span>
                        </div>

                        <strong v-if="summary.next_action_title" class="break-words text-sm">
                            @{{ summary.next_action_title }}
                        </strong>

                        <p
                            v-if="summary.next_action_reason"
                            class="max-h-20 overflow-hidden break-words text-xs leading-5 text-gray-600 dark:text-gray-300"
                        >
                            @{{ summary.next_action_reason }}
                        </p>
                    </div>

                    <div v-if="summary && summary.highlights && summary.highlights.length" class="flex flex-col gap-1">
                        <strong class="text-xs text-gray-900 dark:text-white">Commerciële highlights</strong>

                        <div
                            v-for="(highlight, index) in summary.highlights.slice(0, 3)"
                            :key="`highlight-${index}`"
                            class="flex gap-1 text-xs"
                        >
                            <strong class="shrink-0">@{{ highlight.label }}:</strong>
                            <span class="truncate" :title="highlight.value">@{{ highlight.value }}</span>
                        </div>
                    </div>

                    <div v-if="summary && summary.attention_points && summary.attention_points.length" class="flex flex-col gap-1">
                        <strong class="text-xs text-gray-900 dark:text-white">Aandachtspunten</strong>

                        <ul class="list-disc space-y-1 pl-4 text-xs">
                            <li
                                v-for="(point, index) in summary.attention_points.slice(0, 3)"
                                :key="`attention-${index}`"
                                class="break-words"
                            >
                                <span class="block max-h-10 overflow-hidden">@{{ point.text }}</span>

                                <span v-if="point.source" class="mt-0.5 block text-[11px] text-gray-400">
                                    <a
                                        v-if="point.source.url"
                                        :href="point.source.url"
                                        class="text-blue-600 hover:underline dark:text-blue-400"
                                        :title="`Open ${point.source.label}`"
                                    >
                                        Bron: @{{ point.source.label }}
                                    </a>

                                    <span v-else>Bron: @{{ point.source.label }}</span>

                                    <span>
                                        · @{{ point.source.date_label }} @{{ formatSourceDate(point.source.date) }}
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <p v-if="summary && summary.generated_at" class="text-[11px] text-gray-400">
                        Gegenereerd: @{{ formatDate(summary.generated_at) }}
                    </p>
                </template>
            </section>

            <section class="border-t border-gray-200 pt-3 dark:border-gray-700">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 text-left"
                    @click="feedbackOpen = !feedbackOpen"
                >
                    <span>
                        <strong class="block text-xs text-gray-900 dark:text-white">AI-correcties</strong>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            @{{ feedback.length }} actieve @{{ feedback.length === 1 ? 'correctie' : 'correcties' }}
                        </span>
                    </span>

                    <span class="text-xs" :class="feedbackOpen ? 'icon-up-arrow' : 'icon-down-arrow'"></span>
                </button>

                <div v-if="feedbackOpen" class="mt-3 flex flex-col gap-3">
                    <article
                        v-for="item in feedback"
                        :key="item.id"
                        class="rounded border border-gray-200 p-2 dark:border-gray-700"
                    >
                        <template v-if="editingId === item.id">
                            <textarea
                                v-model.trim="editText"
                                rows="4"
                                maxlength="1000"
                                class="w-full rounded border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-900"
                            ></textarea>

                            <div class="mt-2 flex gap-2">
                                <button type="button" class="primary-button px-2 py-1 text-xs" @click="saveEdit(item)">
                                    Opslaan
                                </button>
                                <button type="button" class="secondary-button px-2 py-1 text-xs" @click="cancelEdit">
                                    Annuleren
                                </button>
                            </div>
                        </template>

                        <template v-else>
                            <p class="break-words text-xs leading-5">@{{ item.feedback }}</p>
                            <p class="mt-1 text-[11px] text-gray-400">
                                @{{ item.author }} · @{{ formatDate(item.created_at) }}
                            </p>

                            <div v-if="canEdit" class="mt-2 flex gap-3 text-xs">
                                <button type="button" class="text-blue-600 hover:underline" @click="startEdit(item)">
                                    Wijzigen
                                </button>
                                <button type="button" class="text-red-600 hover:underline" @click="confirmDelete(item)">
                                    Verwijderen
                                </button>
                            </div>
                        </template>
                    </article>

                    <div v-if="canEdit" class="flex flex-col gap-2">
                        <textarea
                            v-model.trim="newFeedback"
                            rows="4"
                            maxlength="1000"
                            placeholder="Voeg een correctie toe…"
                            class="w-full rounded border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-900"
                        ></textarea>

                        <button
                            type="button"
                            class="primary-button justify-center px-3 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!newFeedback || isSaving"
                            @click="addFeedback"
                        >
                            Correctie toevoegen
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </script>

    <script type="module">
        app.component('v-lead-ai-summary', {
            template: '#v-lead-ai-summary-template',

            props: {
                summaryUrl: {
                    type: String,
                    required: true,
                },
                generateUrl: {
                    type: String,
                    required: true,
                },
                feedbackUrl: {
                    type: String,
                    required: true,
                },
                canEdit: {
                    type: Boolean,
                    default: false,
                },
            },

            data() {
                return {
                    summary: null,
                    feedback: [],
                    isLoading: true,
                    isGenerating: false,
                    isSaving: false,
                    feedbackOpen: false,
                    newFeedback: '',
                    editingId: null,
                    editText: '',
                    pollTimer: null,
                    pollAttempts: 0,
                };
            },

            mounted() {
                this.load();
            },

            beforeUnmount() {
                if (this.pollTimer) {
                    window.clearTimeout(this.pollTimer);
                }
            },

            methods: {
                async load(showLoader = true) {
                    if (showLoader) {
                        this.isLoading = true;
                    }

                    try {
                        const response = await this.$axios.get(this.summaryUrl);
                        this.summary = response.data.data.summary;
                        this.feedback = response.data.data.feedback;

                        if (!this.summary || ['queued', 'processing', 'retrying'].includes(this.summary.status)) {
                            this.schedulePoll();
                        } else {
                            this.pollAttempts = 0;
                        }
                    } catch (error) {
                        this.flashError(error, 'De AI-samenvatting kon niet worden geladen.');
                    } finally {
                        this.isLoading = false;
                    }
                },

                async generate() {
                    if (this.isGenerating) {
                        return;
                    }

                    this.isGenerating = true;

                    try {
                        const response = await this.$axios.post(this.generateUrl);

                        if (this.summary) {
                            this.summary.status = 'queued';
                        } else {
                            this.summary = { status: 'queued' };
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });

                        this.pollAttempts = 0;
                        this.schedulePoll();
                    } catch (error) {
                        this.flashError(error, 'De generatie kon niet worden gestart.');
                    } finally {
                        this.isGenerating = false;
                    }
                },

                async addFeedback() {
                    if (!this.newFeedback || this.isSaving) {
                        return;
                    }

                    this.isSaving = true;

                    try {
                        const response = await this.$axios.post(this.feedbackUrl, {
                            feedback: this.newFeedback,
                        });

                        this.feedback.push(response.data.data);
                        this.newFeedback = '';
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    } catch (error) {
                        this.flashError(error, 'De correctie kon niet worden toegevoegd.');
                    } finally {
                        this.isSaving = false;
                    }
                },

                startEdit(item) {
                    this.editingId = item.id;
                    this.editText = item.feedback;
                },

                cancelEdit() {
                    this.editingId = null;
                    this.editText = '';
                },

                async saveEdit(item) {
                    if (!this.editText || this.isSaving) {
                        return;
                    }

                    this.isSaving = true;

                    try {
                        const response = await this.$axios.put(`${this.feedbackUrl}/${item.id}`, {
                            feedback: this.editText,
                        });
                        const index = this.feedback.findIndex((entry) => entry.id === item.id);
                        this.feedback.splice(index, 1, response.data.data);
                        this.cancelEdit();
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    } catch (error) {
                        this.flashError(error, 'De correctie kon niet worden bijgewerkt.');
                    } finally {
                        this.isSaving = false;
                    }
                },

                confirmDelete(item) {
                    this.$emitter.emit('open-confirm-modal', {
                        title: 'AI-correctie verwijderen',
                        message: 'Weet je zeker dat je deze correctie wilt verwijderen?',
                        options: {
                            btnDisagree: 'Annuleren',
                            btnAgree: 'Verwijderen',
                        },
                        agree: () => this.deleteFeedback(item),
                    });
                },

                async deleteFeedback(item) {
                    try {
                        const response = await this.$axios.delete(`${this.feedbackUrl}/${item.id}`);
                        this.feedback = this.feedback.filter((entry) => entry.id !== item.id);
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    } catch (error) {
                        this.flashError(error, 'De correctie kon niet worden verwijderd.');
                    }
                },

                priorityClass(priority) {
                    return {
                        high: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200',
                        medium: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-200',
                        low: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200',
                    }[priority] || 'bg-gray-100 text-gray-700';
                },

                priorityLabel(priority) {
                    return { high: 'Hoog', medium: 'Middel', low: 'Laag' }[priority] || priority;
                },

                formatDate(value) {
                    if (!value) {
                        return '—';
                    }

                    return new Intl.DateTimeFormat('nl-NL', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    }).format(new Date(value));
                },

                formatSourceDate(value) {
                    if (!value) {
                        return '—';
                    }

                    return new Intl.DateTimeFormat('nl-NL', {
                        dateStyle: 'short',
                    }).format(new Date(value));
                },

                schedulePoll() {
                    if (this.pollAttempts >= 36) {
                        return;
                    }

                    if (this.pollTimer) {
                        window.clearTimeout(this.pollTimer);
                    }

                    this.pollAttempts++;
                    this.pollTimer = window.setTimeout(() => this.load(false), 5000);
                },

                flashError(error, fallback) {
                    this.$emitter.emit('add-flash', {
                        type: 'error',
                        message: error?.response?.data?.message || fallback,
                    });
                },
            },
        });
    </script>
    @endPushOnce
@else
    <div class="p-4">
        <p>Deze kolom is gereserveerd voor aanvullende widgets en informatie.</p>
    </div>
@endif
