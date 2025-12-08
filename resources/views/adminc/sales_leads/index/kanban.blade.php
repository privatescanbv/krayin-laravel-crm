@props([
    'columns',
    'stages',
])

{!! view_render_event('admin.sales-leads.index.kanban.before') !!}

<!-- Kanban Vue Component -->
<v-sales-leads-kanban ref="salesLeadsKanban">
    <div class="flex flex-col gap-4">
        <!-- Shimmer -->
        <x-admin::shimmer.leads.index.kanban />
    </div>
</v-sales-leads-kanban>

{!! view_render_event('admin.sales-leads.index.kanban.after') !!}

@pushOnce('scripts')
    @include('admin::leads.partials.open_activities_confirm_helper')
    <script
        type="text/x-template"
        id="v-sales-leads-kanban-template"
    >
        <template v-if="isLoading">
            <div class="flex flex-col gap-4">
                <x-admin::shimmer.leads.index.kanban />
            </div>
        </template>

        <template v-else>
            <div class="flex flex-col gap-4">
                <!-- Toolbar -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <h2 class="text-lg font-semibold dark:text-white">Backoffice</h2>
                        <button
                            type="button"
                            class="secondary-button whitespace-nowrap"
                            @click="$root.$refs.salesLeadsKanban && $root.$refs.salesLeadsKanban.toggleWonLost()"
                        >
                            <span>@{{ $root.$refs.salesLeadsKanban ? $root.$refs.salesLeadsKanban.wonLostLabel : 'Toon gewonnen/verloren' }}</span>
                        </button>
                    </div>
                </div>

                {!! view_render_event('admin.sales-leads.index.kanban.content.before') !!}

                <div class="flex gap-2.5 overflow-x-auto">
                    <!-- Pipeline Stage Cards -->
                    <div
                        class="flex min-w-[275px] max-w-[275px] flex-col gap-1 rounded-lg border border-gray-200 dark:border-gray-800"
                        v-for="(stage, index) in stageLeads"
                    >
                        {!! view_render_event('admin.sales-leads.index.kanban.content.stage.header.before') !!}

                        <!-- Stage Header -->
                        <div class="flex flex-col px-2 py-3 rounded-t-lg" style="background-color: var(--brand-privatescan);">
                            <!-- Stage Title and Action -->
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-medium text-white cursor-help"
                                    :title="stage.description || null"
                                    v-if="stage.description"
                                >
                                    @{{ stage.name }}
                                </span>
                                <span
                                    class="text-xs font-medium text-white"
                                    v-else
                                >
                                    @{{ stage.name }}
                                </span>

                                <div class="flex items-center gap-1">
                                    <span class="inline-flex items-center justify-center rounded-full bg-white text-[10px] leading-none min-w-[18px] h-[18px] px-1" style="color: var(--brand-privatescan);">
                                        @{{ stage.leads.meta.total }}
                                    </span>

                                </div>
                            </div>
                        </div>

                        {!! view_render_event('admin.sales-leads.index.kanban.content.stage.header.after') !!}

                        {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.before') !!}

                        <!-- Draggable Sales Lead Cards -->
                        <draggable
                            class="flex h-[calc(100vh-317px)] flex-col gap-2 overflow-y-auto p-2"
                            :class="{ 'justify-center': stage.leads.data.length === 0 }"
                            ghost-class="draggable-ghost"
                            handle=".sales-lead-item"
                            v-bind="{animation: 200}"
                            :list="stage.leads.data"
                            item-key="id"
                            group="sales-leads"
                            @scroll="handleScroll(stage, $event)"
                            @change="updateStage(stage, $event)"
                            :scroll-sensitivity="100"
                            :force-fallback="false"
                        >
                            <template v-if="! stage.leads.data.length">
                                <div class="flex flex-col items-center justify-center">
                                    <img
                                        class="dark:mix-blend-exclusion dark:invert"
                                        src="{{ vite()->asset('images/empty-placeholders/pipedrive.svg') }}"
                                    >

                                    <div class="flex flex-col items-center gap-4">
                                        <div class="flex flex-col items-center gap-2">
                                            <p class="!text-base font-semibold dark:text-white">
                                                No sales
                                            </p>
                                        </div>

                                    </div>
                                </div>
                            </template>

                            <!-- Sales Lead Card -->
                            <template #item="{ element: salesLead }">
                                {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.before') !!}

                                <a
                                    class="sales-lead-item flex cursor-pointer flex-col gap-2 rounded-md border border-gray-100 bg-gray-50 p-1.5 dark:border-gray-400 dark:bg-gray-400"
                                    :href="'{{ route('admin.sales-leads.view', 'replaceId') }}'.replace('replaceId', salesLead.id)"
                                    style="min-height:unset;"
                                >
                                    {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.header.before') !!}

                                    <!-- Header -->
                                    <div class="flex items-start justify-between gap-2">
                                       <div class="flex items-center gap-1 min-w-0 flex-1">
                                           <div class="flex flex-col gap-0.5 min-w-0">
                                               <span class="text-[11px] font-medium truncate">
                                                   @{{ salesLead.lead?.person?.name || salesLead.name }}
                                               </span>
                                               <span class="text-[9px] leading-normal truncate" v-if="salesLead.has_multiple_persons">
                                                   +@{{ salesLead.persons_count - 1 }} meer
                                               </span>
                                               <span class="text-[9px] leading-normal" v-if="salesLead.lead?.person?.organization?.name">
                                                   @{{ salesLead.lead?.person?.organization?.name }}
                                               </span>
                                           </div>
                                       </div>

                                       <!-- Date and Rotten Days Indicator -->
                                       <div class="flex items-center gap-1 flex-shrink-0">
                                           <!-- Date -->
                                           <span class="text-[9px] text-gray-500 whitespace-nowrap">
                                               @{{ formatDate(salesLead.created_at) }}
                                           </span>

                                           <!-- Rotten Days Indicator -->
                                           <div
                                               class="group relative flex-shrink-0"
                                               v-if="salesLead.rotten_days > 0"
                                           >
                                               <span class="icon-rotten cursor-default text-sm text-rose-600"></span>
                                               <div class="absolute -top-1 right-7 hidden w-max flex-col items-center group-hover:flex">
                                                   <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                       @{{ "@lang('admin::app.leads.index.kanban.rotten-days', ['days' => 'replaceDays'])".replace('replaceDays', salesLead.rotten_days) }}
                                                   </span>
                                                   <div class="absolute -right-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                               </div>
                                           </div>
                                       </div>
                                    </div>

                                    {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.header.after') !!}

                                    {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.title.before') !!}

                                    <!-- Lost Reason (only for lost status) -->
                                    <div
                                        class="text-[10px] text-red-700 dark:text-red-400 mt-1"
                                        v-if="salesLead?.pipeline_stage?.code && String(salesLead.pipeline_stage.code).toLowerCase().startsWith('lost') && salesLead.lost_reason_label"
                                    >
                                        <span class="font-medium">Verliesreden:</span>
                                        @{{ salesLead.lost_reason_label }}
                                    </div>

                                    <!-- Order Status -->
                                    <div
                                        class="text-[10px] font-medium mt-1"
                                        v-if="salesLead.orders && salesLead.orders.length > 0"
                                    >
                                        <span class="text-gray-600 dark:text-gray-400">Order status:</span>
                                        <span
                                            class="ml-1 px-1.5 py-0.5 rounded text-[9px]"
                                            :class="{
                                                'bg-neutral-bg text-gray-800 dark:bg-gray-700 dark:text-gray-300': salesLead.orders[0].status === 'new',
                                                'bg-blue-100 text-activity-task-text dark:bg-blue-900 dark:text-blue-300': salesLead.orders[0].status === 'planned',
                                                'bg-yellow-100 text-status-on_hold-text dark:bg-yellow-900 dark:text-yellow-300': salesLead.orders[0].status === 'sent',
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': salesLead.orders[0].status === 'approved',
                                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': salesLead.orders[0].status === 'rejected'
                                            }"
                                        >
                                            @{{ salesLead.orders[0].status_label || salesLead.orders[0].status }}
                                        </span>
                                    </div>

                                    {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.title.after') !!}

                                    <!-- Card Footer -->
                                    <div
                                        class="flex items-center justify-between mt-2 pt-2 border-t border-gray-200 dark:border-gray-600"
                                        v-if="salesLead.has_duplicates || salesLead.open_activities_count === 0 || (salesLead.open_activities_count && salesLead.open_activities_count > 0) || (salesLead.unread_emails_count && salesLead.unread_emails_count > 0) || salesLead.mri_status || salesLead.has_diagnosis_form"
                                    >
                                        <div class="flex items-center gap-3">
                                            <!-- Open Activities Count -->
                                            <div class="group relative flex items-center gap-1 text-[10px] text-gray-600 dark:text-gray-400">
                                                <span class="icon-activity text-xs"></span>
                                                <span>@{{ salesLead.open_activities_count || 0 }}</span>
                                                <div class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                    <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                        Openstaande activiteiten
                                                    </span>
                                                    <div class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                </div>
                                            </div>

                                            <!-- Unread Emails Count (includes nested activity emails) -->
                                            <div class="group relative flex items-center gap-1 text-[10px] text-gray-600 dark:text-gray-400">
                                                <span class="icon-mail text-xs"></span>
                                                <span>@{{ salesLead.unread_emails_count || 0 }}</span>
                                                <div class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                    <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                        Ongelezen e-mails
                                                    </span>
                                                    <div class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                </div>
                                            </div>

                                            <!-- Duplicate Indicator -->
                                            <div
                                                class="group relative flex items-center gap-1"
                                                v-if="salesLead.has_duplicates"
                                            >
                                                <span class="icon-warning cursor-default text-xs text-orange-600"></span>
                                                <div class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                    <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                        Mogelijke duplicate gevonden (@{{ salesLead.duplicates_count }} gelijkenissen)
                                                    </span>
                                                    <div class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                </div>
                                            </div>

                                            <!-- No Open Activities Warning -->
                                            <div
                                                class="group relative flex items-center gap-1"
                                                v-if="salesLead.open_activities_count === 0 && !(
                                                    salesLead?.pipeline_stage?.code && (
                                                        String(salesLead.pipeline_stage.code).toLowerCase().startsWith('lost') ||
                                                        String(salesLead.pipeline_stage.code).toLowerCase().startsWith('won')
                                                    )
                                                )"
                                            >
                                                <span class="icon-warning cursor-default text-xs text-status-expired-text"></span>
                                                <div class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                    <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                        Geen open activiteiten
                                                    </span>
                                                    <div class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Days Until Due Date -->
                                        <div class="relative text-[10px] text-gray-600 dark:text-gray-400">
                                            <span
                                                v-if="salesLead.days_until_due_date === null"
                                                class="text-gray-500"
                                            >
                                                -
                                            </span>
                                            <span
                                                v-else-if="salesLead.days_until_due_date > 0"
                                                class="text-status-active-text"
                                            >
                                                @{{ salesLead.days_until_due_date }}d
                                            </span>
                                            <span
                                                v-else-if="salesLead.days_until_due_date === 0"
                                                class="text-orange-600 font-medium"
                                            >
                                                Vandaag
                                            </span>
                                            <span
                                                v-else
                                                class="text-status-expired-text font-medium"
                                            >
                                                @{{ Math.abs(salesLead.days_until_due_date) }}d over
                                            </span>

                                            <!-- Diagnosis Form Icon bottom-right (to the left of MRI) -->
                                            <div v-if="salesLead.has_diagnosis_form"
                                                 class="absolute -bottom-1 right-4 group">
                                                <span class="icon-attachment text-xs"></span>
                                                <div class="absolute -top-1 right-5 hidden w-max flex-col items-center group-hover:flex">
                                                    <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                        Diagnoseformulier aanwezig
                                                    </span>
                                                    <div class="absolute -right-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                </div>
                                            </div>

                                            <!-- MRI Status Icon bottom-right -->
                                            <div v-if="salesLead.mri_status"
                                                 class="absolute -bottom-1 -right-1 group">
                                                <span class="icon-image text-xs"></span>
                                                <div class="absolute -top-1 right-5 hidden w-max flex-col items-center group-hover:flex">
                                                    <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                        @{{ salesLead.mri_status_label }}
                                                    </span>
                                                    <div class="absolute -right-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>

                                {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.after') !!}
                            </template>
                        </draggable>

                        {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.after') !!}
                    </div>
                </div>

                {!! view_render_event('admin.sales-leads.index.kanban.content.after') !!}
            </div>

            <!-- Lost Stage Modal -->
            <x-admin::modal ref="lostStageModal">
                <x-slot:header>
                    <h3 class="text-base font-semibold dark:text-white">
                        Meer details nodig
                    </h3>
                </x-slot>

                <x-slot:content>
                    <div v-if="currentStageUpdate">
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                            Sales "<strong>@{{ getSalesLeadName(currentStageUpdate.salesLead) }}</strong>" wordt verplaatst naar status "Verloren"
                        </p>

                        <!-- Lost Reason -->
                        <x-adminc::components.field
                            type="text"
                            name="closed_at"
                            v-model="currentStageUpdate.closed_at"
                            placeholder="dd-mm-yyyy"
                            required
                            label="Gesloten op"
                        />

                        <x-admin::form.control-group>
                            <select
                                name="lost_reason"
                                class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                                v-model="currentStageUpdate.lost_reason"
                                required
                            >
                                <option value="">Selecteer reden...</option>
                                @foreach (\App\Enums\LostReason::cases() as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                            <x-admin::form.control-group.label>
                                Reden van verlies
                            </x-admin::form.control-group.label>

                        </x-admin::form.control-group>
                    </div>
                    <div v-else>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Laden...
                        </p>
                    </div>
                </x-slot>

                <x-slot:footer>
                    <button
                        type="button"
                        class="secondary-button mr-2"
                        @click="cancelLostStage"
                    >
                        Annuleren
                    </button>

                    <button
                        type="button"
                        class="primary-button"
                        @click="handleLostStageSubmit"
                    >
                        Opslaan
                    </button>
                </x-slot>
            </x-admin::modal>
        </template>
    </script>

    <script type="module">
        app.component('v-sales-leads-kanban', {
            template: '#v-sales-leads-kanban-template',

            data() {
                return {
                    available: {
                        columns: {{ Js::from($columns) }},
                    },

                    applied: {
                        filters: {
                            columns: [],
                        }
                    },

                    stages: {{ Js::from($stages) }},

                    stageLeads: {},

                    isLoading: true,

                    tagTextColor: {
                        '#FEE2E2': '#DC2626',
                        '#FFEDD5': '#EA580C',
                        '#FEF3C7': '#D97706',
                        '#FEF9C3': '#CA8A04',
                        '#ECFCCB': '#65A30D',
                        '#DCFCE7': '#16A34A',
                    },
                    hideWonLost: true,
                    wonLostLabel: 'Toon gewonnen/verloren',
                    currentStageUpdate: null,
                    scrollTimeouts: {},
                };
            },

            computed: {
                src() {
                    const pipelineId = "{{ request('pipeline_id') ?? '' }}";
                    return `{{ route('admin.sales-leads.index') }}${pipelineId ? '?pipeline_id=' + pipelineId : ''}`;
                },
                currentPipelineId() {
                    return "{{ request('pipeline_id') ?? '' }}";
                }
            },

            mounted () {
                this.boot();
            },

            methods: {
                /**
                 * Sync toggle button label with state
                 */
                setWonLostButtonText() {
                    this.wonLostLabel = this.hideWonLost ? 'Toon gewonnen/verloren' : 'Verberg gewonnen/verloren';
                },
                /**
                 * Format date to a more readable format
                 *
                 * @param {string} dateString - The date string to format
                 * @returns {string} Formatted date string
                 */
                formatDate(dateString) {
                    if (!dateString) return '';

                    const date = new Date(dateString);
                    const now = new Date();
                    const diffTime = Math.abs(now - date);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    if (diffDays === 1) {
                        return 'Vandaag';
                    } else if (diffDays === 2) {
                        return 'Gisteren';
                    } else if (diffDays <= 7) {
                        return `${diffDays - 1} dagen geleden`;
                    } else {
                        return date.toLocaleDateString('nl-NL', {
                            day: '2-digit',
                            month: '2-digit',
                            year: '2-digit'
                        });
                    }
                },

                /**
                 * Initialization: This function checks for any previously saved filters in local storage and applies them as needed.
                 *
                 * @returns {void}
                 */
                boot() {
                    let kanbans = this.getKanbans();

                    if (kanbans?.length) {
                        const currentKanban = kanbans.find(({ src }) => src === this.src);

                        if (currentKanban) {
                            this.applied.filters = currentKanban.applied.filters;

                            if (typeof currentKanban.hideWonLost === 'boolean') {
                                this.hideWonLost = currentKanban.hideWonLost;
                            }

                            this.setWonLostButtonText();

                            this.get()
                                .then(response => {
                                    if (response && response.data) {
                                        for (let [sortOrder, data] of Object.entries(response.data)) {
                                            this.stageLeads[sortOrder] = data;
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading kanban data:', error);
                                    this.isLoading = false;
                                });

                            return;
                        }
                    }

                    // Check for pipeline-specific won/lost setting
                    const pipelineSpecificKey = `kanban_hideWonLost_pipeline_${this.currentPipelineId}`;
                    const pipelineSpecificSetting = localStorage.getItem(pipelineSpecificKey);
                    if (pipelineSpecificSetting !== null) {
                        this.hideWonLost = JSON.parse(pipelineSpecificSetting);
                    } else {
                        // Default to hidden for performance
                        this.hideWonLost = true;
                    }
                    this.setWonLostButtonText();

                    this.get()
                        .then(response => {
                            if (response && response.data) {
                                for (let [sortOrder, data] of Object.entries(response.data)) {
                                    this.stageLeads[sortOrder] = data;
                                }
                            }
                            this.setWonLostButtonText();
                        })
                        .catch(error => {
                            console.error('Error loading kanban data:', error);
                            this.isLoading = false;
                        });
                },

                /**
                 * Fetches the sales leads based on the applied filters.
                 *
                 * @param {object} requestedParams - The requested parameters.
                 * @returns {Promise} The promise object representing the request.
                 */
                get(requestedParams = {}) {
                    let params = {
                        search: '',
                        searchFields: '',
                        pipeline_id: "{{ request('pipeline_id') }}",
                        limit: 10,
                        exclude_won_lost: this.hideWonLost, // Performance optimization: exclude won/lost stages when hidden
                    };

                    // Carry search params from URL (so Mega Search deep-linking preserves filters)
                    try {
                        const urlParams = new URLSearchParams(window.location.search);
                        const qsSearch = urlParams.get('search');
                        const qsSearchFields = urlParams.get('searchFields');
                        const qsJoin = urlParams.get('searchJoin');
                        if (qsSearch) params.search = qsSearch;
                        if (qsSearchFields) params.searchFields = qsSearchFields;
                        if (qsJoin) params.searchJoin = qsJoin;
                        // If a search is present via deep-link, include won/lost to match mega search results
                        if (qsSearch) {
                            this.hideWonLost = false;
                            params.exclude_won_lost = false;
                            this.setWonLostButtonText();
                        }
                    } catch (e) {
                        console.error('Failed to parse search params from URL:', e);
                    }

                    this.applied.filters.columns.forEach((column) => {
                        if (column.index === 'all') {
                            if (! column.value.length) {
                                return;
                            }

                            params['search'] += `name:${column.value.join(',')};`;
                            params['searchFields'] += `name:like;`;

                            return;
                        }

                        /**
                         * If the column is a searchable dropdown, then we need to append the column value
                         * with the column label. Otherwise, we can directly append the column value.
                         */
                        params['search'] += column.filterable_type === 'searchable_dropdown'
                            ? `${column.index}:${column.value.map(option => option.value).join(',')};`
                            : `${column.index}:${column.value.join(',')};`;

                        params['searchFields'] += `${column.index}:${column.search_field};`;
                    });

                    return this.$axios
                        .get("{{ route('admin.sales-leads.get') }}", {
                            params: {
                                ...params,

                                ...requestedParams,
                            }
                        })
                        .then(response => {
                            this.isLoading = false;

                            this.updateKanbans();

                            return response;
                        })
                        .catch(error => {
                            console.error('Error fetching sales:', error);
                        });
                },

                /**
                 * Filters the sales based on the applied filters.
                 * Clears existing data and refetches with new filters and current exclude_won_lost state.
                 *
                 * @param {object} filters - The filters to be applied.
                 * @returns {void}
                 */
                filter(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index === 'all')),
                        ...filters.columns,
                    ];

                    // Clear existing data before applying new filters
                    this.stageLeads = {};
                    this.get()
                        .then(response => {
                            if (response && response.data) {
                                for (let [sortOrder, data] of Object.entries(response.data)) {
                                    this.stageLeads[sortOrder] = data;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error applying filters:', error);
                        });
                },

                /**
                 * Searches the sales leads based on the applied filters.
                 * Clears existing data and refetches with new search criteria and current exclude_won_lost state.
                 *
                 * @param {object} filters - The filters to be applied.
                 * @returns {void}
                 */
                search(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index !== 'all')),
                        ...filters.columns,
                    ];

                    // Clear existing data before applying new search
                    this.stageLeads = {};
                    this.get()
                        .then(response => {
                            if (response && response.data) {
                                for (let [sortOrder, data] of Object.entries(response.data)) {
                                    this.stageLeads[sortOrder] = data;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error applying search:', error);
                        });
                },

                /**
                 * Toggle won/lost visibility and refetch
                 */
                toggleWonLost() {
                    this.hideWonLost = !this.hideWonLost;
                    this.updateKanbans();

                    const pipelineSpecificKey = `kanban_hideWonLost_pipeline_${this.currentPipelineId}`;
                    localStorage.setItem(pipelineSpecificKey, JSON.stringify(this.hideWonLost));

                    this.setWonLostButtonText();

                    // Clear and refetch
                    this.stageLeads = {};
                    this.get()
                        .then(response => {
                            if (response && response.data) {
                                for (let [sortOrder, data] of Object.entries(response.data)) {
                                    this.stageLeads[sortOrder] = data;
                                }
                            }
                        })
                        .catch(() => {
                            // revert on error
                            this.hideWonLost = !this.hideWonLost;
                            this.setWonLostButtonText();
                        });
                },

                /**
                 * Updates the stage with the latest sales lead data.
                 *
                 * @param {object} stage - The stage object.
                 * @param {object} event - The event object.
                 * @returns {void}
                 */
                updateStage(stage, event) {
                    if (event.moved) {
                        return;
                    }

                    if (event.removed) {
                        this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total - 1;
                        return;
                    }

                    if (event.added) {
                        // Check if moving to any lost stage (e.g., 'lost', 'lost-hernia')
                        if (stage.code && String(stage.code).toLowerCase().startsWith('lost')) {
                            this.showLostModal(stage, event.added.element);
                            return;
                        }

                        // Update stage counters for non-lost stages
                        this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total + 1;

                        this.updateSalesLeadStageWithChecks(event.added.element, stage);
                    }
                },

                /**
                 * Show modal for lost stage with required fields
                 */
                showLostModal(stage, salesLead) {
                    this.currentStageUpdate = {
                        stage: stage,
                        salesLead: salesLead,
                        lost_reason: '',
                        closed_at: new Date().toLocaleDateString('nl-NL')
                    };

                    // Use nextTick to ensure the modal is rendered before opening
                    this.$nextTick(() => {
                        this.$refs.lostStageModal.open();
                    });
                },

                /**
                 * Handle form submission for lost stage
                 */
                handleLostStageSubmit() {
                    if (!this.currentStageUpdate.lost_reason.trim()) {
                        this.$emitter.emit('add-flash', { type: 'error', message: 'Reden van verlies is verplicht' });
                        return;
                    }

                    // Submit lost details to dedicated endpoint
                    this.$axios
                        .put("{{ route('admin.sales-leads.lost', 'replace') }}".replace('replace', this.currentStageUpdate.salesLead.id), {
                            lost_reason: this.currentStageUpdate.lost_reason,
                            closed_at: this.currentStageUpdate.closed_at
                        })
                        .then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            // Increment the target stage count
                            const stage = this.currentStageUpdate.stage;
                            if (stage && this.stageLeads[stage.sort_order]) {
                                this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total + 1;
                            }
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error?.response?.data?.message || 'Bijwerken mislukt' });
                        });

                    if (this.$refs.lostStageModal) {
                        this.$refs.lostStageModal.close();
                    }
                    this.currentStageUpdate = null;
                },

                /**
                 * Update sales lead stage with optional additional data
                 */
                async updateSalesLeadStage(salesLeadId, stageId, additionalData = {}) {
                    const data = {
                        'lead_pipeline_stage_id': stageId,
                        ...additionalData
                    };
                    this.$axios
                        .put("{{ route('admin.sales-leads.stage.update', 'replace') }}".replace('replace', salesLeadId), data)
                        .then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            // Update stage counters after successful update
                            const stage = this.stages.find(s => s.id === stageId);
                            if (stage) {
                                this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total + 1;
                            }
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        });
                },

                /**
                 * Update stage but first confirm if there are open activities
                 */
                async updateSalesLeadStageWithChecks(salesLead, stage) {
                    try {
                        const openCount = Number(salesLead.open_activities_count || 0);

                        if (openCount > 0) {
                            const message = await window.buildOpenActivitiesConfirmMessage(this.$axios, salesLead.id, openCount, 'sales');
                            const confirmClose = await new Promise((resolve) => {
                                resolve(window.confirm(message));
                            });

                            if (!confirmClose) {
                                // Revert UI count since we optimistically incremented earlier
                                this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total - 1;
                                return;
                            }

                            await this.updateSalesLeadStage(salesLead.id, stage.id, { close_open_activities: true });
                            return;
                        }

                        await this.updateSalesLeadStage(salesLead.id, stage.id);
                    } catch (e) {
                        // No-op; errors are handled in updateSalesLeadStage
                    }
                },

                /**
                 * Get sales name for display
                 */
                getSalesLeadName(salesLead) {
                    const firstName = salesLead.first_name || '';
                    const lastName = salesLead.last_name || '';
                    return (firstName + ' ' + lastName).trim() || salesLead.name || 'Onbekende sales';
                },

                /**
                 * Cancel lost stage modal
                 */
                cancelLostStage() {
                    if (this.$refs.lostStageModal) {
                        this.$refs.lostStageModal.close();
                    }
                    this.currentStageUpdate = null;
                },

                /**
                 * Handles the scroll event on the stage saless with debouncing for performance.
                 *
                 * @param {object} stage - The stage object.
                 * @param {object} event - The scroll event.
                 * @returns {void}
                 */
                handleScroll(stage, event) {
                    // Clear existing timeout for this stage
                    if (this.scrollTimeouts && this.scrollTimeouts[stage.id]) {
                        clearTimeout(this.scrollTimeouts[stage.id]);
                    }

                    // Initialize scrollTimeouts if not exists
                    if (!this.scrollTimeouts) {
                        this.scrollTimeouts = {};
                    }

                    // Debounce scroll handling
                    this.scrollTimeouts[stage.id] = setTimeout(() => {
                        const element = event.target;
                        const bottom = Math.abs(element.scrollHeight - element.scrollTop - element.clientHeight) < 1;

                        if (! bottom) {
                            return;
                        }

                        if (this.stageLeads[stage.sort_order].leads.meta.current_page == this.stageLeads[stage.sort_order].leads.meta.last_page) {
                            return;
                        }

                        this.append({
                            pipeline_stage_id: stage.id,
                            pipeline_id: stage.lead_pipeline_id,
                            page: this.stageLeads[stage.sort_order].leads.meta.current_page + 1,
                            limit: 10,
                        });
                    }, 150); // 150ms debounce
                },

                /**
                 * Appends the sales to the stage.
                 * Ensures the exclude_won_lost parameter is included for performance optimization.
                 *
                 * @param {object} params - The parameters to be appended.
                 * @returns {void}
                 */
                append(params) {
                    // Ensure exclude_won_lost parameter is included for performance optimization
                    const paramsWithExclude = {
                        ...params,
                        exclude_won_lost: this.hideWonLost,
                    };

                    this.get(paramsWithExclude)
                        .then(response => {
                            if (response && response.data) {
                                for (let [sortOrder, data] of Object.entries(response.data)) {
                                    if (! this.stageLeads[sortOrder]) {
                                        this.stageLeads[sortOrder] = data;
                                    } else {
                                        this.stageLeads[sortOrder].leads.data = this.stageLeads[sortOrder].leads.data.concat(data.leads.data);

                                        this.stageLeads[sortOrder].leads.meta = data.leads.meta;
                                    }
                                }
                            }
                        });
                },

                //=======================================================================================
                // Support for previous applied values in kanban's. All code is based on local storage.
                //=======================================================================================

                /**
                 * Updates the kanban's stored in local storage with the latest data.
                 *
                 * @returns {void}
                 */
                updateKanbans() {
                    let kanbans = this.getKanbans();

                    if (kanbans?.length) {
                        const currentKanban = kanbans.find(({ src }) => src === this.src);

                        if (currentKanban) {
                            kanbans = kanbans.map(kanban => {
                                if (kanban.src === this.src) {
                                    return {
                                        ...kanban,
                                        requestCount: ++kanban.requestCount,
                                        available: this.available,
                                        applied: this.applied,
                                        hideWonLost: this.hideWonLost,
                                    };
                                }

                                return kanban;
                            });
                        } else {
                            kanbans.push(this.getKanbanInitialProperties());
                        }
                    } else {
                        kanbans = [this.getKanbanInitialProperties()];
                    }

                    this.setKanbans(kanbans);
                },

                /**
                 * Returns the initial properties for a kanban.
                 *
                 * @returns {object} Initial properties for a kanban.
                 */
                getKanbanInitialProperties() {
                    return {
                        src: this.src,
                        requestCount: 0,
                        available: this.available,
                        applied: this.applied,
                        hideWonLost: this.hideWonLost,
                    };
                },

                /**
                 * Returns the storage key for kanban's in local storage.
                 *
                 * @returns {string} Storage key for kanban's.
                 */
                getKanbansStorageKey() {
                    return 'kanbans';
                },

                /**
                 * Retrieves the kanban's stored in local storage.
                 *
                 * @returns {Array} Kanban's stored in local storage.
                 */
                getKanbans() {
                    let kanbans = localStorage.getItem(
                        this.getKanbansStorageKey()
                    );

                    return JSON.parse(kanbans) ?? [];
                },

                /**
                 * Sets the kanban's in local storage.
                 *
                 * @param {Array} kanbans - Kanban's to be stored in local storage.
                 * @returns {void}
                 */
                setKanbans(kanbans) {
                    localStorage.setItem(
                        this.getKanbansStorageKey(),
                        JSON.stringify(kanbans)
                    );
                },
            }
        });
    </script>
@endPushOnce

