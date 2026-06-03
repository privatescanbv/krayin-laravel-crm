@php
    $activityData = [
        'id'            => $activity->id,
        'person_id'     => $activity->person_id,
        'lead_id'       => $activity->lead_id,
        'sales_lead_id' => $activity->sales_lead_id,
        'order_id'      => $activity->order_id,
        'clinic_id'     => $activity->clinic_id,
        'person'        => $activity->person ? ['id' => $activity->person->id, 'name' => $activity->person->name] : null,
        'lead'          => $activity->lead ? ['id' => $activity->lead->id, 'name' => $activity->lead->name] : null,
        'sales_lead'    => $activity->salesLead ? ['id' => $activity->salesLead->id, 'name' => $activity->salesLead->name] : null,
        'order'         => $activity->order ? ['id' => $activity->order->id, 'name' => $activity->order->title ?? ('Order #' . $activity->order->id)] : null,
        'clinic'        => $activity->clinic ? ['id' => $activity->clinic->id, 'name' => $activity->clinic->name] : null,
    ];
@endphp

@pushOnce('scripts')
<script type="text/x-template" id="v-activity-link-panel-template">
    <div class="flex flex-col gap-4">
        <div class="font-semibold text-gray-800 dark:text-gray-300">
            Koppeling beheren
        </div>

        <!-- Existing links -->
        <template v-if="linkedEntities.length">
            <div class="flex flex-col gap-2">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-400">
                    Gekoppeld aan
                </div>
                <div
                    v-for="link in linkedEntities"
                    :key="link.type + '-' + link.id"
                    class="flex items-center justify-between rounded-md border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                >
                    <a
                        v-if="link.url"
                        :href="link.url"
                        class="flex items-center gap-2 flex-1 hover:opacity-80 transition-opacity text-sm"
                    >
                        <span class="icon-link text-gray-600 dark:text-gray-300 text-base"></span>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">@{{ link.label }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">@{{ link.subtitle }}</div>
                        </div>
                    </a>
                    <button
                        type="button"
                        @click="unlink(link.type)"
                        :disabled="saving"
                        class="flex items-center gap-1 rounded border border-red-300 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-900/50 disabled:opacity-50"
                    >
                        <span class="icon-delete text-sm"></span>
                        Ontkoppel
                    </button>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="text-sm text-gray-400 italic dark:text-gray-500">Geen koppelingen</div>
        </template>

        <!-- Search to link -->
        <div class="flex flex-col gap-2">
            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-400">
                Koppel aan
            </div>

            <!-- Entity type selector -->
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="opt in entityOptions"
                    :key="opt.type"
                    type="button"
                    @click="selectEntityType(opt.type)"
                    :class="[
                        'rounded px-2 py-1 text-xs font-medium border transition-colors',
                        activeEntityType === opt.type
                            ? 'bg-brandColor text-white border-brandColor'
                            : 'bg-white text-gray-600 border-gray-300 hover:bg-neutral-bg dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600'
                    ]"
                >
                    @{{ opt.label }}
                </button>
            </div>

            <!-- Search input -->
            <template v-if="activeEntityType">
                <div class="relative">
                    <input
                        type="text"
                        v-model="searchTerm"
                        @input="onSearch"
                        class="w-full rounded border border-gray-200 px-2.5 py-2 pr-10 text-sm text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        placeholder="Zoeken..."
                        ref="searchInput"
                    />
                    <span class="absolute inset-y-0 right-0 flex items-center pr-3">
                        <span v-if="isSearching" class="relative">
                            <x-admin::spinner />
                        </span>
                        <i v-else class="fas fa-search text-gray-500 text-sm"></i>
                    </span>
                </div>

                <ul v-if="results.length" class="max-h-48 overflow-y-auto divide-y divide-gray-100 border rounded bg-white shadow dark:bg-gray-800 dark:border-gray-700">
                    <li
                        v-for="item in results"
                        :key="item.id"
                        @click="linkEntity(item)"
                        class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-gray-800 hover:bg-blue-50 dark:text-white dark:hover:bg-gray-700"
                    >
                        <span class="font-medium">@{{ item.name ?? item.title ?? ('#' + item.id) }}</span>
                    </li>
                </ul>

                <div v-if="searchTerm.length >= 2 && !isSearching && results.length === 0" class="text-xs text-gray-400 dark:text-gray-500">
                    Geen resultaten gevonden
                </div>
            </template>

            <div v-if="saving" class="text-xs text-gray-500 dark:text-gray-400">Opslaan...</div>
        </div>
    </div>
</script>

<script type="module">
    app.component('v-activity-link-panel', {
        template: '#v-activity-link-panel-template',

        data() {
            return {
                activity: @json($activityData),

                activeEntityType: null,

                searchTerm: '',

                results: [],

                isSearching: false,

                saving: false,

                searchTimeout: null,

                entityOptions: [
                    { type: 'person',     label: 'Persoon',     route: '{{ route('admin.contacts.persons.search') }}' },
                    { type: 'lead',       label: 'Lead',        route: '{{ route('admin.leads.search') }}' },
                    { type: 'sales_lead', label: 'Sales lead',  route: '{{ route('admin.sales-leads.search') }}' },
                    { type: 'order',      label: 'Order',       route: '{{ route('admin.orders.search') }}' },
                    { type: 'clinic',     label: 'Kliniek',     route: '{{ route('admin.clinics.search') }}' },
                ],
            };
        },

        computed: {
            linkedEntities() {
                const links = [];

                if (this.activity.lead_id && this.activity.lead) {
                    links.push({
                        type:     'lead',
                        id:       this.activity.lead_id,
                        label:    this.activity.lead.name,
                        subtitle: 'Lead',
                        url:      '{{ route('admin.leads.view', ':id') }}'.replace(':id', this.activity.lead_id),
                    });
                }

                if (this.activity.sales_lead_id && this.activity.sales_lead) {
                    links.push({
                        type:     'sales_lead',
                        id:       this.activity.sales_lead_id,
                        label:    this.activity.sales_lead.name,
                        subtitle: 'Sales lead',
                        url:      '{{ route('admin.sales-leads.view', ':id') }}'.replace(':id', this.activity.sales_lead_id),
                    });
                }

                if (this.activity.order_id && this.activity.order) {
                    links.push({
                        type:     'order',
                        id:       this.activity.order_id,
                        label:    this.activity.order.name,
                        subtitle: 'Order',
                        url:      '{{ route('admin.orders.view', ':id') }}'.replace(':id', this.activity.order_id),
                    });
                }

                if (this.activity.person_id && this.activity.person) {
                    links.push({
                        type:     'person',
                        id:       this.activity.person_id,
                        label:    this.activity.person.name,
                        subtitle: 'Persoon',
                        url:      '{{ route('admin.contacts.persons.view', ':id') }}'.replace(':id', this.activity.person_id),
                    });
                }

                if (this.activity.clinic_id && this.activity.clinic) {
                    links.push({
                        type:     'clinic',
                        id:       this.activity.clinic_id,
                        label:    this.activity.clinic.name,
                        subtitle: 'Kliniek',
                        url:      '{{ route('admin.clinics.view', ':id') }}'.replace(':id', this.activity.clinic_id),
                    });
                }

                return links;
            },
        },

        methods: {
            selectEntityType(type) {
                this.activeEntityType = this.activeEntityType === type ? null : type;
                this.searchTerm = '';
                this.results = [];
                if (this.activeEntityType) {
                    this.$nextTick(() => this.$refs.searchInput?.focus());
                }
            },

            onSearch() {
                clearTimeout(this.searchTimeout);
                if (this.searchTerm.length < 2) {
                    this.results = [];
                    return;
                }
                this.searchTimeout = setTimeout(this.fetchResults, 300);
            },

            fetchResults() {
                const option = this.entityOptions.find(o => o.type === this.activeEntityType);
                if (!option) return;

                this.isSearching = true;

                this.$axios.get(option.route, { params: { query: this.searchTerm } })
                    .then(response => {
                        const data = response.data?.data ?? response.data ?? [];
                        this.results = Array.isArray(data) ? data : [];
                    })
                    .catch(() => { this.results = []; })
                    .finally(() => { this.isSearching = false; });
            },

            linkEntity(item) {
                const fkMap = {
                    person:     'person_id',
                    lead:       'lead_id',
                    sales_lead: 'sales_lead_id',
                    order:      'order_id',
                    clinic:     'clinic_id',
                };

                const fk = fkMap[this.activeEntityType];
                if (!fk) return;

                this.saving = true;

                this.$axios.post('{{ route('admin.activities.link-entity', ':id') }}'.replace(':id', this.activity.id), {
                    [fk]: item.id,
                })
                    .then(response => {
                        const entityKey = this.activeEntityType;
                        const fkKey = fk;

                        this.activity[fkKey] = item.id;
                        this.activity[entityKey] = { id: item.id, name: item.name ?? item.title ?? ('#' + item.id) };

                        this.activeEntityType = null;
                        this.searchTerm = '';
                        this.results = [];

                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    })
                    .catch(error => {
                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? 'Er is een fout opgetreden.' });
                    })
                    .finally(() => { this.saving = false; });
            },

            unlink(entityType) {
                const fkMap = {
                    person:     'person_id',
                    lead:       'lead_id',
                    sales_lead: 'sales_lead_id',
                    order:      'order_id',
                    clinic:     'clinic_id',
                };

                const fk = fkMap[entityType];
                if (!fk) return;

                this.$emitter.emit('open-confirm-modal', {
                    agree: () => {
                        this.saving = true;

                        this.$axios.post('{{ route('admin.activities.link-entity', ':id') }}'.replace(':id', this.activity.id), {
                            [fk]: null,
                        })
                            .then(response => {
                                this.activity[fk] = null;
                                this.activity[entityType] = null;
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            })
                            .catch(error => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? 'Er is een fout opgetreden.' });
                            })
                            .finally(() => { this.saving = false; });
                    },
                });
            },
        },
    });
</script>
@endPushOnce
