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
                                           <div v-if="element.persons && element.persons.length > 0" class="flex-shrink-0">
                                               <x-admin::avatar ::name="element.persons[0]?.name" class="w-6 h-6" />
                                           </div>
                                           <div v-else-if="element.first_name">
                                               <x-admin::avatar ::name="`${element.first_name} ${element.last_name}`" class="w-6 h-6" />
                                           </div>
                                           <div class="flex flex-col gap-0.5 min-w-0">
                                               <span class="text-[11px] font-medium truncate">
                                                   @{{ element.persons && element.persons.length > 0 ? element.persons[0]?.name : (element.first_name ? `${element.first_name} ${element.last_name}` : element.name) }}
                                               </span>
                                               <span class="text-[9px] leading-normal truncate" v-if="element.persons && element.persons.length > 1">
                                                   +@{{ element.persons.length - 1 }} meer
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

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.after') !!}

                                    <!-- Card Footer -->
                                    <div
                                        class="flex items-center justify-between mt-2 pt-2 border-t border-gray-200 dark:border-gray-600"
                                        v-if="(element.open_activities_count && element.open_activities_count > 0) || (element.unread_emails_count && element.unread_emails_count > 0)"
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

                                            <!-- Unread Emails Count -->
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
                                        </div>

                                        <!-- Days Until Due Date -->
                                        <div class="text-[10px] text-gray-600 dark:text-gray-400">
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
        </template>
    </script>

    <script type="module">
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
                };
            },

            computed: {
                totalStagesAmount() {
                    return 0;
                }
            },

            mounted () {
                this.boot();
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

                            this.get()
                                .then(response => {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                });

                            return;
                        }
                    }

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
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
                    };

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
                            console.log(error)
                        });
                },

                /**
                 * Filters the leads based on the applied filters.
                 *
                 * @param {object} filters - The filters to be applied.
                 * @returns {void}
                 */
                filter(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index === 'all')),
                        ...filters.columns,
                    ];

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Searches the leads based on the applied filters.
                 *
                 * @param {object} filters - The filters to be applied.
                 * @returns {void}
                 */
                search(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index !== 'all')),
                        ...filters.columns,
                    ];

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Appends the leads to the stage.
                 *
                 * @param {object} params - The parameters to be appended.
                 * @returns {void}
                 */
                append(params) {
                    this.get(params)
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                if (! this.stageLeads[sortOrder]) {
                                    this.stageLeads[sortOrder] = data;
                                } else {
                                    this.stageLeads[sortOrder].leads.data = this.stageLeads[sortOrder].leads.data.concat(data.leads.data);

                                    this.stageLeads[sortOrder].leads.meta = data.leads.meta;
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
                        stage.lead_value = 0;

                        this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total - 1;

                        return;
                    }

                    stage.lead_value = 0;

                    this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total + 1;

                    this.$axios
                        .put("{{ route('admin.leads.stage.update', 'replace') }}".replace('replace', event.added.element.id), {
                            'lead_pipeline_stage_id': stage.id
                        })
                        .then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        });
                },

                /**
                 * Handles the scroll event on the stage leads.
                 *
                 * @param {object} stage - The stage object.
                 * @param {object} event - The scroll event.
                 * @returns {void}
                 */
                handleScroll(stage, event) {
                    const bottom = event.target.scrollHeight - event.target.scrollTop === event.target.clientHeight;

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
