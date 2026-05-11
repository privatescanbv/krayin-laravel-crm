<x-admin::layouts>
    <x-slot:title>
        Persoon koppelen - {{ $salesLead->name }}
    </x-slot>

    <v-attach-person
        entity-name="{{ $salesLead->name }}"
        back-url="{{ route('admin.sales-leads.view', $salesLead->id) }}"
        create-url="{{ route('admin.contacts.persons.create', ['return_to' => 'sales-lead', 'entity_id' => $salesLead->id]) }}"
        search-url="{{ route('admin.contacts.persons.search') }}"
        store-url="{{ route('admin.sales-leads.attach_person.store', $salesLead->id) }}"
        :lead-id='@json($salesLead->lead_id)'
    ></v-attach-person>

    @pushOnce('scripts', 'attach-person-page')
        <script type="text/x-template" id="v-attach-person-template">
            <div class="flex flex-col gap-4 pt-3">
                <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Sales</span>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">@{{ entityName }}</h1>
                    </div>

                    <a :href="backUrl" class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                        <span class="icon-arrow-left text-base"></span>
                        <span>Terug naar sales</span>
                    </a>
                </div>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr),360px]">
                    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-4 flex flex-col gap-1">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Bestaande persoon zoeken</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Zoek op naam, e-mail of telefoonnummer.</p>
                        </div>

                        <div class="relative">
                            <input
                                v-model="query"
                                type="text"
                                class="w-full rounded border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition-all focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                placeholder="Zoek persoon"
                                @input="queueSearch"
                            >
                            <span v-if="loading" class="absolute right-3 top-2.5 text-sm text-gray-400">Zoeken...</span>
                        </div>

                        <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                            <button
                                v-for="person in persons"
                                :key="person.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 text-left last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                                :class="selectedPerson && selectedPerson.id === person.id ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-white dark:bg-gray-900'"
                                @click="selectPerson(person)"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">@{{ person.name }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                        @{{ contactLine(person) }}
                                    </span>
                                </span>

                                <span v-if="person.match_score_percentage !== undefined" class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    @{{ Math.round(person.match_score_percentage || 0) }}% match
                                </span>
                            </button>

                            <div v-if="! loading && persons.length === 0" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Geen personen gevonden.
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Selectie</h2>

                            <div v-if="selectedPerson" class="flex flex-col gap-3">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">@{{ selectedPerson.name }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">@{{ contactLine(selectedPerson) }}</div>
                                </div>

                                <form method="POST" :action="storeUrl">
                                    @csrf
                                    <input type="hidden" name="person_id" :value="selectedPerson.id">

                                    <button type="submit" class="primary-button w-full justify-center">
                                        Koppelen
                                    </button>
                                </form>
                            </div>

                            <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                                Selecteer een persoon uit de zoekresultaten.
                            </div>
                        </div>

                        <a :href="createUrl" class="secondary-button flex w-full items-center justify-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                            <span class="icon-plus text-base"></span>
                            <span>Nieuwe persoon aanmaken</span>
                        </a>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-attach-person', {
                template: '#v-attach-person-template',

                props: {
                    entityName: String,
                    backUrl: String,
                    createUrl: String,
                    searchUrl: String,
                    storeUrl: String,
                    leadId: Number,
                },

                data() {
                    return {
                        query: '',
                        persons: [],
                        selectedPerson: null,
                        loading: false,
                        timer: null,
                    };
                },

                mounted() {
                    this.search();
                },

                methods: {
                    queueSearch() {
                        clearTimeout(this.timer);
                        this.timer = setTimeout(() => this.search(), 250);
                    },

                    async search() {
                        this.loading = true;

                        const params = new URLSearchParams();

                        if (this.query.trim()) {
                            params.set('search', this.query.trim());
                        }

                        if (this.leadId) {
                            params.set('lead_id', this.leadId);
                        }

                        try {
                            const response = await fetch(`${this.searchUrl}?${params.toString()}`, {
                                headers: {
                                    'Accept': 'application/json',
                                },
                            });

                            const payload = await response.json();
                            const result = payload?.data ?? payload ?? [];

                            this.persons = Array.isArray(result) ? result : [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    selectPerson(person) {
                        this.selectedPerson = person;
                    },

                    contactLine(person) {
                        const email = this.firstValue(person.emails);
                        const phone = this.firstValue(person.phones);

                        return [email, phone].filter(Boolean).join(' | ') || 'Geen contactgegevens';
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
