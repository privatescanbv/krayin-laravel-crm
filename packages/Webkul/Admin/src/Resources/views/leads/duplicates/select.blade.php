<x-admin::layouts>
    <x-slot:title>
        Lead samenvoegen - {{ $lead->name }}
    </x-slot>

    <v-manual-lead-merge
        entity-name="{{ $lead->name }}"
        :exclude-lead-id='@json($lead->id)'
        back-url="{{ route('admin.leads.view', $lead->id) }}"
        search-url="{{ route('admin.leads.search') }}"
        continue-url-template="{{ route('admin.leads.duplicates.index', ['id' => $lead->id, 'with' => '__LEAD_ID__']) }}"
    ></v-manual-lead-merge>

    @pushOnce('scripts', 'manual-lead-merge-page')
        <script type="text/x-template" id="v-manual-lead-merge-template">
            <div class="flex flex-col gap-4 pt-3">
                <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Huidige lead</span>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">@{{ entityName }}</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">ID: @{{ excludeLeadId }}</p>
                    </div>

                    <a :href="backUrl" class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                        <span class="icon-arrow-left text-base"></span>
                        <span>Terug naar lead</span>
                    </a>
                </div>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr),360px]">
                    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-4 flex flex-col gap-1">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Andere lead zoeken</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Zoek op naam, e-mail of telefoonnummer. Ook leads buiten de automatische detectieperiode zijn beschikbaar.
                            </p>
                        </div>

                        <div class="relative">
                            <input
                                v-model="query"
                                type="text"
                                class="w-full rounded border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition-all focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                placeholder="Zoek lead"
                                @input="queueSearch"
                            >
                            <span v-if="loading" class="absolute right-3 top-2.5 text-sm text-gray-400">Zoeken...</span>
                        </div>

                        <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                            <button
                                v-for="lead in leads"
                                :key="lead.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 text-left last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                                :class="selectedLead && selectedLead.id === lead.id ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-white dark:bg-gray-900'"
                                @click="selectLead(lead)"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">
                                        @{{ lead.name || [lead.first_name, lead.last_name].filter(Boolean).join(' ') || ('Lead #' + lead.id) }}
                                    </span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                        #@{{ lead.id }}
                                        <template v-if="lead.stage?.name"> · @{{ lead.stage.name }}</template>
                                        <template v-if="contactLine(lead)"> · @{{ contactLine(lead) }}</template>
                                    </span>
                                </span>

                                <span
                                    v-if="selectedLead && selectedLead.id === lead.id"
                                    class="icon-check shrink-0 text-lg text-activity-note-text"
                                ></span>
                            </button>

                            <div v-if="! loading && query.trim() && leads.length === 0" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Geen leads gevonden.
                            </div>

                            <div v-if="! loading && ! query.trim()" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Typ om te zoeken naar een lead om samen te voegen.
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Selectie</h2>

                            <div v-if="selectedLead" class="flex flex-col gap-3">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        @{{ selectedLead.name || [selectedLead.first_name, selectedLead.last_name].filter(Boolean).join(' ') || ('Lead #' + selectedLead.id) }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        #@{{ selectedLead.id }}
                                        <template v-if="selectedLead.stage?.name"> · @{{ selectedLead.stage.name }}</template>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">@{{ contactLine(selectedLead) || 'Geen contactgegevens' }}</div>
                                </div>

                                <a
                                    :href="continueUrl"
                                    class="primary-button w-full justify-center"
                                >
                                    Verder
                                </a>
                            </div>

                            <div v-else class="flex flex-col gap-3">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Selecteer één andere lead uit de zoekresultaten.
                                </p>

                                <button
                                    type="button"
                                    disabled
                                    class="primary-button w-full justify-center opacity-50 cursor-not-allowed"
                                >
                                    Verder
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-manual-lead-merge', {
                template: '#v-manual-lead-merge-template',

                props: {
                    entityName: String,
                    excludeLeadId: Number,
                    backUrl: String,
                    searchUrl: String,
                    continueUrlTemplate: String,
                },

                data() {
                    return {
                        query: '',
                        leads: [],
                        selectedLead: null,
                        loading: false,
                        timer: null,
                    };
                },

                computed: {
                    continueUrl() {
                        if (! this.selectedLead) {
                            return '#';
                        }

                        return this.continueUrlTemplate.replace('__LEAD_ID__', this.selectedLead.id);
                    },
                },

                methods: {
                    queueSearch() {
                        clearTimeout(this.timer);
                        this.timer = setTimeout(() => this.search(), 250);
                    },

                    async search() {
                        const term = this.query.trim();

                        if (! term) {
                            this.leads = [];
                            this.loading = false;

                            return;
                        }

                        this.loading = true;

                        try {
                            const response = await fetch(`${this.searchUrl}?${new URLSearchParams({
                                query: term,
                                limit: '25',
                            }).toString()}`, {
                                headers: {
                                    'Accept': 'application/json',
                                },
                            });

                            const payload = await response.json();
                            const result = payload?.data ?? payload ?? [];

                            this.leads = (Array.isArray(result) ? result : [])
                                .filter(lead => Number(lead.id) !== Number(this.excludeLeadId));
                        } finally {
                            this.loading = false;
                        }
                    },

                    selectLead(lead) {
                        this.selectedLead = lead;
                    },

                    contactLine(lead) {
                        const email = this.firstValue(lead.emails);
                        const phone = this.firstValue(lead.phones);

                        return [email, phone].filter(Boolean).join(' | ');
                    },

                    firstValue(items) {
                        if (! Array.isArray(items) || ! items.length) {
                            return '';
                        }

                        return items[0]?.value || '';
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
