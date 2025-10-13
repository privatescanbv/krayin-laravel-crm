{!! view_render_event('admin.leads.index.kanban.before') !!}

<!-- Kanban Vue Component -->
<v-leads-kanban ref="leadsKanban">
    <div class="flex flex-col gap-4">
        <!-- Shimmer -->
        <x-admin::shimmer.leads.index.kanban />
    </div>
</v-leads-kanban>

{!! view_render_event('admin.leads.index.kanban.after') !!}

@pushOnce('scripts')
    @include('admin::leads.partials.open_activities_confirm_helper')
    <script
        type="text/x-template"
        id="v-leads-kanban-template"
    >
        <template v-if="isLoading">
            <div class="flex flex-col gap-4">
                <x-admin::shimmer.leads.index.kanban />
            </div>
        </template>

        <template v-else>
                            <div class="flex flex-col gap-4">
                    @include('admin::leads.index.kanban.toolbar')

                    {!! view_render_event('admin.leads.index.kanban.content.before') !!}

                <div class="flex gap-2.5 overflow-x-auto">
                    <!-- Stage Cards -->
                    <div
                        class="flex min-w-[275px] max-w-[275px] flex-col gap-1 rounded-lg border border-gray-200 dark:border-gray-800"
                        v-for="(stage, index) in stageLeads"
                    >
                        {!! view_render_event('admin.leads.index.kanban.content.stage.header.before') !!}

                                                <!-- Stage Header -->
                        <div class="flex flex-col px-2 py-3 rounded-t-lg" style="background-color: var(--brand-blue);">
                            <!-- Stage Title and Action -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-white">
                                    @{{ stage.name }}
                                </span>

                                <div class="flex items-center gap-1">
                                    <span class="inline-flex items-center justify-center rounded-full bg-white text-[10px] leading-none min-w-[18px] h-[18px] px-1" style="color: var(--brand-blue);">
                                        @{{ stage.leads.meta.total }}
                                    </span>

                                    @if (bouncer()->hasPermission('leads.create'))
                                        <a
                                            :href="'{{ route('admin.leads.create') }}' + '?stage_id=' + stage.id"
                                            class="icon-add cursor-pointer rounded p-1 text-lg text-white transition-all hover:bg-white hover:bg-opacity-20"
                                            target="_blank"
                                        >
                                        </a>
                                    @endif
                                </div>
                            </div>


                        </div>

                        {!! view_render_event('admin.leads.index.kanban.content.stage.header.after') !!}

                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.before') !!}

                        <!-- Draggable Stage Lead Cards -->
                        <draggable
                            class="flex h-[calc(100vh-317px)] flex-col gap-2 overflow-y-auto p-2"
                            :class="{ 'justify-center': stage.leads.data.length === 0 }"
                            ghost-class="draggable-ghost"
                            handle=".lead-item"
                            v-bind="{animation: 200}"
                            :list="stage.leads.data"
                            item-key="id"
                            group="leads"
                            @scroll="handleScroll(stage, $event)"
                            @change="updateStage(stage, $event)"
                            :scroll-sensitivity="100"
                            :force-fallback="false"
                        >
                            <template #header>
                                <div
                                    class="flex flex-col items-center justify-center"
                                    v-if="! stage.leads.data.length"
                                >
                                    <img
                                        class="dark:mix-blend-exclusion dark:invert"
                                        src="{{ vite()->asset('images/empty-placeholders/pipedrive.svg') }}"
                                    >

                                    <div class="flex flex-col items-center gap-4">
                                        <div class="flex flex-col items-center gap-2">
                                            <p class="!text-base font-semibold dark:text-white">
                                                @lang('admin::app.leads.index.kanban.empty-list')
                                            </p>

                                            <p class="!text-sm text-gray-400 dark:text-gray-400">
                                                @lang('admin::app.leads.index.kanban.empty-list-description')
                                            </p>
                                        </div>

                                        @if (bouncer()->hasPermission('leads.create'))
                                            <a
                                                :href="'{{ route('admin.leads.create') }}' + '?stage_id=' + stage.id"
                                                class="secondary-button"
                                            >
                                                @lang('admin::app.leads.index.kanban.create-lead-btn')
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </template>

                            <!-- Lead Card -->
                            <template #item="{ element, index }">
                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.before') !!}

                                <a
                                    class="lead-item flex cursor-pointer flex-col gap-2 rounded-md border border-gray-100 bg-gray-50 p-1.5 dark:border-gray-400 dark:bg-gray-400"
                                    :href="'{{ route('admin.leads.view', 'replaceId') }}'.replace('replaceId', element.id)"
                                    style="min-height:unset;"
                                >
                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.header.before') !!}

                                                                        <!-- Header -->
                                    <div class="flex items-start justify-between gap-2">
                                       <div class="flex items-center gap-1 min-w-0 flex-1">
                                           <div class="flex flex-col gap-0.5 min-w-0">
                                               <span class="text-[11px] font-medium truncate">
                                                   @{{ element.persons && element.persons.length > 0 ? element.persons[0]?.name : (element.first_name ? `${element.first_name} ${element.last_name}` : element.name) }}
                                               </span>
                                               <span class="text-[9px] leading-normal truncate" v-if="element.has_multiple_persons">
                                                   +@{{ element.persons_count - 1 }} meer
                                               </span>
                                               <span class="text-[9px] leading-normal" v-if="element.persons && element.persons.length > 0 && element.persons[0]?.organization?.name">
                                                   @{{ element.persons[0]?.organization?.name }}
                                               </span>
                                           </div>
                                       </div>

                                       <!-- Date and Rotten Days Indicator -->
                                       <div class="flex items-center gap-1 flex-shrink-0">
                                           <!-- Date -->
                                           <span class="text-[9px] text-gray-500 whitespace-nowrap">
                                               @{{ formatDate(element.created_at) }}
                                           </span>

                                           <!-- Rotten Days Indicator -->
                                           <div
                                               class="group relative flex-shrink-0"
                                               v-if="element.rotten_days > 0"
                                           >
                                               <span class="icon-rotten cursor-default text-sm text-rose-600"></span>
                                               <div class="absolute -top-1 right-7 hidden w-max flex-col items-center group-hover:flex">
                                                   <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                       @{{ "@lang('admin::app.leads.index.kanban.rotten-days', ['days' => 'replaceDays'])".replace('replaceDays', element.rotten_days) }}
                                                   </span>
                                                   <div class="absolute -right-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                               </div>
                                           </div>
                                       </div>
                                    </div>

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.header.after') !!}

                                                                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.before') !!}

                                    <!-- Lost Reason (only for lost status) -->
                                    <div
                                        class="text-[10px] text-red-700 dark:text-red-400 mt-1"
                                        v-if="element?.stage?.code && String(element.stage.code).toLowerCase().startsWith('lost') && element.lost_reason_label"
                                    >
                                        <span class="font-medium">Verliesreden:</span>
                                        @{{ element.lost_reason_label }}
                                    </div>

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.after') !!}

                                    <!-- Card Footer -->
                                    <div
                                        class="flex items-center justify-between mt-2 pt-2 border-t border-gray-200 dark:border-gray-600"
                                        v-if="element.has_duplicates || element.open_activities_count === 0 || (element.open_activities_count && element.open_activities_count > 0) || (element.unread_emails_count && element.unread_emails_count > 0) || element.mri_status || element.has_diagnosis_form"
                                    >
                                        <div class="flex items-center gap-3">
                                            <!-- Open Activities Count -->
                                            <div class="group relative flex items-center gap-1 text-[10px] text-gray-600 dark:text-gray-400">
                                                <span class="icon-activity text-xs"></span>
                                                <span>@{{ element.open_activities_count || 0 }}</span>
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
                                                <span>@{{ element.unread_emails_count || 0 }}</span>
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
                                                v-if="element.has_duplicates"
                                            >
                                                <span class="icon-warning cursor-default text-xs text-orange-600"></span>
                                                <div class="absolute -top-1 left-0 hidden w-max flex-col items-center group-hover:flex">
                                                    <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                        Mogelijke duplicate gevonden (@{{ element.duplicates_count }} gelijkenissen)
                                                    </span>
                                                    <div class="absolute -left-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                </div>
                                            </div>

                                            <!-- No Open Activities Warning -->
                                            <div
                                                class="group relative flex items-center gap-1"
                                                v-if="element.open_activities_count === 0 && !(
                                                    element?.stage?.code && (
                                                        String(element.stage.code).toLowerCase().startsWith('lost') ||
                                                        String(element.stage.code).toLowerCase().startsWith('won')
                                                    )
                                                )"
                                            >
                                                <span class="icon-warning cursor-default text-xs text-red-600"></span>
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
                                                v-if="element.days_until_due_date === null"
                                                class="text-gray-500"
                                            >
                                                -
                                            </span>
                                            <span
                                                v-else-if="element.days_until_due_date > 0"
                                                class="text-green-600"
                                            >
                                                @{{ element.days_until_due_date }}d
                                            </span>
                                            <span
                                                v-else-if="element.days_until_due_date === 0"
                                                class="text-orange-600 font-medium"
                                            >
                                                Vandaag
                                            </span>
                                            <span
                                                v-else
                                                class="text-red-600 font-medium"
                                            >
                                                @{{ Math.abs(element.days_until_due_date) }}d over
                                            </span>

                                            <!-- Diagnosis Form Icon bottom-right (to the left of MRI) -->
                                            <div v-if="element.has_diagnosis_form"
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
                                            <div v-if="element.mri_status"
                                                 class="absolute -bottom-1 -right-1 group">
                                                <span class="icon-image text-xs"></span>
                                                <div class="absolute -top-1 right-5 hidden w-max flex-col items-center group-hover:flex">
                                                    <span class="whitespace-no-wrap relative rounded-md bg-black px-2 py-1 text-[10px] leading-none text-white shadow-lg">
                                                        @{{ element.mri_status_label }}
                                                    </span>
                                                    <div class="absolute -right-1 top-2 h-2 w-2 rotate-45 bg-black"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>

                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.after') !!}
                            </template>
                        </draggable>

                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.after') !!}
                    </div>
                </div>

                {!! view_render_event('admin.leads.index.kanban.content.after') !!}
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
                            Lead "<strong>@{{ getLeadName(currentStageUpdate.lead) }}</strong>" wordt verplaatst naar status "Verloren"
                        </p>

                        <!-- Lost Reason -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                Reden van verlies
                            </x-admin::form.control-group.label>

                            <select
                                name="lost_reason"
                                class="!w-full min-h-[38px] border border-gray-300 dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900 text-sm"
                                v-model="currentStageUpdate.lost_reason"
                                required
                            >
                                <option value="">Selecteer reden...</option>
                                @foreach(\App\Enums\LostReason::cases() as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                        </x-admin::form.control-group>

                        <!-- Closed At -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                Gesloten op
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                name="closed_at"
                                v-model="currentStageUpdate.closed_at"
                                placeholder="dd-mm-yyyy"
                                required
                            />
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
        document.addEventListener('DOMContentLoaded', function() {
            app.component('v-leads-kanban', {
            template: '#v-leads-kanban-template',

            data() {
                return {
                    available: {
                        columns: @json($columns),
                    },

                    applied: {
                        filters: {
                            columns: [],
                        }
                    },

                    stages: @json($pipeline->stages->toArray()),

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
                totalStagesAmount() {
                    return 0;
                },

                /**
                 * Generate unique src identifier including pipeline for localStorage
                 */
                src() {
                    const pipelineId = "{{ request('pipeline_id') ?? '' }}";
                    return `{{ route('admin.leads.index') }}${pipelineId ? '?pipeline_id=' + pipelineId : ''}`;
                },

                /**
                 * Get the current pipeline ID for localStorage key
                 */
                currentPipelineId() {
                    return "{{ request('pipeline_id') ?? '' }}";
                }
            },

            mounted () {
                this.boot();
                this.setWonLostButtonText();
            },

            methods: {
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
                        // Default to hidden for performance (70k leads)
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
                 * Fetches the leads based on the applied filters.
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
                        .get("{{ route('admin.leads.get') }}", {
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
                            console.error('Error fetching leads:', error);
                        });
                },

                /**
                 * Filters the leads based on the applied filters.
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
                 * Searches the leads based on the applied filters.
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
                 * Appends the leads to the stage.
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

                /**
                 * Updates the stage with the latest lead data.
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

                    // Check if moving to any lost stage (e.g., 'lost', 'lost-hernia')
                    if (stage.code && String(stage.code).toLowerCase().startsWith('lost')) {
                        this.showLostModal(stage, event.added.element);
                        return;
                    }

                    // Update stage counters for non-lost stages
                    this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total + 1;

                    this.updateLeadStageWithChecks(event.added.element, stage);
                },

                /**
                 * Show modal for lost stage with required fields
                 */
                showLostModal(stage, lead) {
                    this.currentStageUpdate = {
                        stage: stage,
                        lead: lead,
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

                    this.updateLeadStage(
                        this.currentStageUpdate.lead.id,
                        this.currentStageUpdate.stage.id,
                        {
                            lost_reason: this.currentStageUpdate.lost_reason,
                            closed_at: this.currentStageUpdate.closed_at
                        }
                    );

                    if (this.$refs.lostStageModal) {
                        this.$refs.lostStageModal.close();
                    }
                    this.currentStageUpdate = null;
                },

                /**
                 * Update lead stage with optional additional data
                 */
                async updateLeadStage(leadId, stageId, additionalData = {}) {
                    const data = {
                        'lead_pipeline_stage_id': stageId,
                        ...additionalData
                    };
                    this.$axios
                        .put("{{ route('admin.leads.stage.update', 'replace') }}".replace('replace', leadId), data)
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
                async updateLeadStageWithChecks(lead, stage) {
                    try {
                        const openCount = Number(lead.open_activities_count || 0);

                        if (openCount > 0) {
                            const message = await window.buildOpenActivitiesConfirmMessage(this.$axios, lead.id, openCount);
                            const confirmClose = await new Promise((resolve) => {
                                resolve(window.confirm(message));
                            });

                            if (!confirmClose) {
                                // Revert UI count since we optimistically incremented earlier
                                this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total - 1;
                                return;
                            }

                            await this.updateLeadStage(lead.id, stage.id, { close_open_activities: true });
                            return;
                        }

                        await this.updateLeadStage(lead.id, stage.id);
                    } catch (e) {
                        // No-op; errors are handled in updateLeadStage
                    }
                },

                /**
                 * Get lead name for display
                 */
                getLeadName(lead) {
                    const firstName = lead.first_name || '';
                    const lastName = lead.last_name || '';
                    return (firstName + ' ' + lastName).trim() || 'Onbekende lead';
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
                 * Handles the scroll event on the stage leads with debouncing for performance.
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

                /**
                 * Toggle the visibility of won/lost stages and refetch data accordingly
                 * This method optimizes performance by only fetching data for visible stages
                 */
                toggleWonLost() {
                    this.hideWonLost = !this.hideWonLost;
                    this.updateKanbans();

                    // Store pipeline-specific setting
                    const pipelineSpecificKey = `kanban_hideWonLost_pipeline_${this.currentPipelineId}`;
                    localStorage.setItem(pipelineSpecificKey, JSON.stringify(this.hideWonLost));

                    // Update button text
                    this.setWonLostButtonText();

                    // Clear existing data and refetch with new exclude_won_lost parameter
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
                            console.error('Error toggling won/lost stages:', error);
                            // Revert the toggle if there's an error
                            this.hideWonLost = !this.hideWonLost;
                            this.updateKanbans();

                            // Update button text back
                            this.setWonLostButtonText();
                        });
                },

                /**
                 * Sync toggle button label with state.
                 */
                setWonLostButtonText() {
                    this.wonLostLabel = this.hideWonLost ? 'Toon gewonnen/verloren' : 'Verberg gewonnen/verloren';
                },
            }
        });
        });
    </script>
@endPushOnce
