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
                @include('admin.sales_leads.index.kanban.toolbar')

                {!! view_render_event('admin.sales-leads.index.kanban.content.before') !!}

                <div class="flex gap-2.5 overflow-x-auto">
                    <!-- Pipeline Stage Cards -->
                    <div
                        class="flex min-w-[275px] max-w-[275px] flex-col gap-1 rounded-lg border border-gray-200 dark:border-gray-800"
                        v-for="(stage, index) in stageLeads"
                    >
                        {!! view_render_event('admin.sales-leads.index.kanban.content.stage.header.before') !!}

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

                                    @if (bouncer()->hasPermission('sales-leads.create'))
                                        <a
                                            :href="'{{ route('admin.sales-leads.create') }}' + '?pipeline_stage_id=' + stage.id"
                                            class="icon-add cursor-pointer rounded p-1 text-lg text-white transition-all hover:bg-white hover:bg-opacity-20"
                                            target="_blank"
                                        >
                                        </a>
                                    @endif
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
                                                No sales leads
                                            </p>

                                            <p class="!text-sm text-gray-400 dark:text-gray-400">
                                                Create your first sales lead
                                            </p>
                                        </div>

                                        @if (bouncer()->hasPermission('sales-leads.create'))
                                            <a
                                                :href="'{{ route('admin.sales-leads.create') }}' + '?pipeline_stage_id=' + stage.id"
                                                class="secondary-button"
                                            >
                                                Sales order aanmaken
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </template>

                            <!-- Sales Lead Card -->
                            <template #item="{ element: lead }">
                                {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.before') !!}

                                <a
                                    class="sales-lead-item flex cursor-pointer flex-col gap-2 rounded-md border border-gray-100 bg-gray-50 p-1.5 dark:border-gray-400 dark:bg-gray-400"
                                    :href="'{{ route('admin.sales-leads.view', 'replaceId') }}'.replace('replaceId', lead.id)"
                                    style="min-height:unset;"
                                >
                                    {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.header.before') !!}

                                    <!-- Header -->
                                    <div class="flex items-start justify-between gap-2">
                                       <div class="flex items-center gap-1 min-w-0 flex-1">
                                           <div v-if="lead.lead?.person?.name" class="flex-shrink-0">
                                               <x-admin::avatar ::name="lead.lead?.person?.name" class="w-6 h-6" />
                                           </div>
                                           <div class="flex flex-col gap-0.5 min-w-0">
                                               <span class="text-[11px] font-medium truncate">
                                                   @{{ lead.lead?.person?.name || lead.name }}
                                               </span>
                                           </div>
                                       </div>

                                       <!-- Date -->
                                       <div class="flex items-center gap-1 flex-shrink-0">
                                           <span class="text-[9px] text-gray-500 whitespace-nowrap">
                                               @{{ formatDate(lead.created_at) }}
                                           </span>
                                       </div>
                                    </div>

                                    {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.header.after') !!}

                                    {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.title.before') !!}

                                    <!-- Sales Lead Title -->
                                    <p class="text-[12px] font-medium leading-tight mb-0.5">
                                        @{{ lead.description }}
                                    </p>

                                    {!! view_render_event('admin.sales-leads.index.kanban.content.stage.body.card.title.after') !!}

                                    <div class="flex flex-wrap gap-0.5">
                                        <div
                                            class="flex items-center gap-0.5 rounded-xl bg-gray-200 px-2 py-0.5 text-[10px] font-medium dark:bg-gray-800 dark:text-white"
                                            v-if="lead.user"
                                        >
                                            <span class="icon-settings-user text-xs"></span>
                                            @{{ lead.user.name }}
                                        </div>
                                        <div class="rounded-xl bg-gray-200 px-2 py-0.5 text-[10px] font-medium dark:bg-gray-800 dark:text-white">
                                            @{{ lead.pipeline_stage?.name || 'No Stage' }}
                                        </div>
                                        <div 
                                            class="rounded-xl px-2 py-0.5 text-[10px] font-medium"
                                            :class="getOrderStatusClass(lead.order_status)"
                                            v-if="lead.order_status"
                                        >
                                            @{{ lead.order_status }}
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
                    scrollTimeouts: {},
                };
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
                 * Get CSS classes for order status
                 *
                 * @param {string} orderStatus - The order status
                 * @returns {string} CSS classes
                 */
                getOrderStatusClass(orderStatus) {
                    if (!orderStatus) return 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-white';
                    
                    const status = orderStatus.toLowerCase();
                    
                    if (status.includes('pending') || status.includes('wachtend')) {
                        return 'bg-yellow-200 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200';
                    } else if (status.includes('processing') || status.includes('verwerking')) {
                        return 'bg-blue-200 text-blue-800 dark:bg-blue-800 dark:text-blue-200';
                    } else if (status.includes('shipped') || status.includes('verzonden')) {
                        return 'bg-purple-200 text-purple-800 dark:bg-purple-800 dark:text-purple-200';
                    } else if (status.includes('delivered') || status.includes('geleverd')) {
                        return 'bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-200';
                    } else if (status.includes('cancelled') || status.includes('geannuleerd')) {
                        return 'bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-200';
                    } else {
                        return 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-white';
                    }
                },

                /**
                 * Initialization
                 */
                boot() {
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
                },

                /**
                 * Fetches the sales leads
                 */
                get(requestedParams = {}) {
                    let params = {
                        view_type: 'kanban',
                        pipeline_id: '{{ request('pipeline_id') }}',
                        limit: 10,
                        exclude_won_lost: this.hideWonLost,
                    };

                    // Apply search and filter parameters
                    this.applied.filters.columns.forEach((column) => {
                        if (column.index === 'all') {
                            if (! column.value.length) {
                                return;
                            }

                            params['search'] += `name:${column.value.join(',')};`;
                            params['searchFields'] += `name:like;`;

                            return;
                        }

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

                            // Update stageLeads with leads data
                            const data = response.data || {};

                            for (let [sortOrder, stageData] of Object.entries(data)) {
                                this.stageLeads[sortOrder] = stageData;
                            }

                            return response;
                        })
                        .catch(error => {
                            console.log(error);
                            this.isLoading = false;
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
                        this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total + 1;

                        this.$axios
                            .put("{{ route('admin.sales-leads.update', 'replace') }}".replace('replace', event.added.element.id), {
                                'pipeline_stage_id': stage.id
                            })
                            .then(response => {
                                this.$emitter.emit('add-flash', { type: 'success', message: 'Sales lead stage updated successfully.' });
                            })
                            .catch(error => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Failed to update sales lead stage.' });
                            });
                    }
                },

                /**
                 * Filters the sales leads based on the applied filters.
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
                 * Toggle the visibility of won/lost stages
                 */
                toggleWonLost() {
                    this.hideWonLost = !this.hideWonLost;
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
                        });
                },

                /**
                 * Sync toggle button label with state.
                 */
                setWonLostButtonText() {
                    this.wonLostLabel = this.hideWonLost ? 'Toon gewonnen/verloren' : 'Verberg gewonnen/verloren';
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

                /**
                 * Appends the sales leads to the stage.
                 *
                 * @param {object} params - The parameters to be appended.
                 * @returns {void}
                 */
                append(params) {
                    this.get(params)
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
            }
        });
    </script>
@endPushOnce
