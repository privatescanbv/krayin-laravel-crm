{!! view_render_event($eventPrefix . '.before') !!}

<!-- Kanban Vue Component -->
<v-base-kanban ref="baseKanban">
    <div class="flex flex-col gap-4">
        <!-- Shimmer -->
        <x-admin::shimmer.leads.index.kanban />
    </div>
</v-base-kanban>

{!! view_render_event($eventPrefix . '.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-base-kanban-template"
    >
        <template v-if="isLoading">
            <div class="flex flex-col gap-4">
                <x-admin::shimmer.leads.index.kanban />
            </div>
        </template>

        <template v-else>
            <div class="flex flex-col gap-4">
                <!-- Toolbar -->
                <div class="flex justify-between gap-2 max-md:flex-wrap">
                    <div class="flex w-full items-center gap-x-1.5 max-md:justify-between">
                        <!-- Search Panel -->
                        <v-kanban-search
                            :is-loading="isLoading"
                            :available="available"
                            :applied="applied"
                            @search="search"
                        >
                        </v-kanban-search>

                        <!-- Filter -->
                        <v-kanban-filter
                            :is-loading="isLoading"
                            :available="available"
                            :applied="applied"
                            @applyFilters="filter"
                        >
                        </v-kanban-filter>

                        <!-- Collapse Won/Lost toggle -->
                        <button
                            type="button"
                            class="secondary-button whitespace-nowrap"
                            @click="toggleWonLost()"
                        >
                            <span>@{{ wonLostLabel }}</span>
                        </button>
                    </div>

                    <!-- View Switcher -->
                    <div class="flex items-center gap-2">
                        <a
                            :href="kanbanUrl"
                            class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-all hover:bg-gray-100 dark:hover:bg-gray-800"
                            :class="viewType === 'kanban' ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white' : 'text-gray-600 dark:text-gray-400'"
                        >
                            <span class="icon-kanban text-lg"></span>
                            Kanban
                        </a>

                        <a
                            :href="tableUrl"
                            class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-all hover:bg-gray-100 dark:hover:bg-gray-800"
                            :class="viewType === 'table' ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white' : 'text-gray-600 dark:text-gray-400'"
                        >
                            <span class="icon-table text-lg"></span>
                            Tabel
                        </a>
                    </div>
                </div>

                <div class="flex gap-2.5 overflow-x-auto">
                    <!-- Stage Cards -->
                    <div
                        class="flex min-w-[275px] max-w-[275px] flex-col gap-1 rounded-lg border border-gray-200 dark:border-gray-800"
                        v-for="(stage, index) in stageLeads"
                    >
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

                                    <a
                                        :href="createUrl + '?pipeline_stage_id=' + stage.id"
                                        class="icon-add cursor-pointer rounded p-1 text-lg text-white transition-all hover:bg-white hover:bg-opacity-20"
                                        target="_blank"
                                        v-if="canCreate"
                                    >
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Draggable Lead Cards -->
                        <draggable
                            class="flex h-[calc(100vh-317px)] flex-col gap-2 overflow-y-auto p-2"
                            :class="{ 'justify-center': stage.leads.data.length === 0 }"
                            ghost-class="draggable-ghost"
                            :handle="dragHandle"
                            v-bind="{animation: 200}"
                            :list="stage.leads.data"
                            item-key="id"
                            :group="dragGroup"
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
                                                @{{ emptyMessage }}
                                            </p>

                                            <p class="!text-sm text-gray-400 dark:text-gray-400">
                                                @{{ emptyDescription }}
                                            </p>
                                        </div>

                                        <a
                                            :href="createUrl + '?pipeline_stage_id=' + stage.id"
                                            class="secondary-button"
                                            v-if="canCreate"
                                        >
                                            @{{ createButtonText }}
                                        </a>
                                    </div>
                                </div>
                            </template>

                            <!-- Lead Card -->
                            <template #item="{ element, index }">
                                <a
                                    :class="cardClass"
                                    :href="viewUrl.replace('replaceId', element.id)"
                                    style="min-height:unset;"
                                >
                                    <!-- Header -->
                                    <div class="flex items-start justify-between gap-2">
                                       <div class="flex items-center gap-1 min-w-0 flex-1">
                                           <div v-if="element.lead?.person?.name" class="flex-shrink-0">
                                               <x-admin::avatar ::name="element.lead?.person?.name" class="w-6 h-6" />
                                           </div>
                                           <div class="flex flex-col gap-0.5 min-w-0">
                                               <span class="text-[11px] font-medium truncate">
                                                   @{{ getLeadName(element) }}
                                               </span>
                                           </div>
                                       </div>

                                       <!-- Date -->
                                       <div class="flex items-center gap-1 flex-shrink-0">
                                           <span class="text-[9px] text-gray-500 whitespace-nowrap">
                                               @{{ formatDate(element.created_at) }}
                                           </span>
                                       </div>
                                    </div>

                                    <!-- Title -->
                                    <p class="text-[12px] font-medium leading-tight mb-0.5">
                                        @{{ getLeadTitle(element) }}
                                    </p>

                                    <!-- Tags -->
                                    <div class="flex flex-wrap gap-0.5">
                                        <div
                                            class="flex items-center gap-0.5 rounded-xl bg-gray-200 px-2 py-0.5 text-[10px] font-medium dark:bg-gray-800 dark:text-white"
                                            v-if="element.user"
                                        >
                                            <span class="icon-settings-user text-xs"></span>
                                            @{{ element.user.name }}
                                        </div>
                                        <div class="rounded-xl bg-gray-200 px-2 py-0.5 text-[10px] font-medium dark:bg-gray-800 dark:text-white">
                                            @{{ element.pipeline_stage?.name || 'No Stage' }}
                                        </div>
                                        <div 
                                            class="rounded-xl px-2 py-0.5 text-[10px] font-medium"
                                            :class="getOrderStatusClass(element.order_status)"
                                            v-if="element.order_status"
                                        >
                                            @{{ element.order_status }}
                                        </div>
                                    </div>
                                </a>
                            </template>
                        </draggable>
                    </div>
                </div>
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-base-kanban', {
            template: '#v-base-kanban-template',

            props: {
                available: {
                    type: Object,
                    required: true
                },
                applied: {
                    type: Object,
                    required: true
                },
                stages: {
                    type: Array,
                    required: true
                },
                getUrl: {
                    type: String,
                    required: true
                },
                createUrl: {
                    type: String,
                    required: true
                },
                viewUrl: {
                    type: String,
                    required: true
                },
                updateUrl: {
                    type: String,
                    required: true
                },
                kanbanUrl: {
                    type: String,
                    required: true
                },
                tableUrl: {
                    type: String,
                    required: true
                },
                viewType: {
                    type: String,
                    default: 'kanban'
                },
                canCreate: {
                    type: Boolean,
                    default: true
                },
                dragHandle: {
                    type: String,
                    default: '.lead-item'
                },
                dragGroup: {
                    type: String,
                    required: true
                },
                cardClass: {
                    type: String,
                    default: 'lead-item flex cursor-pointer flex-col gap-2 rounded-md border border-gray-100 bg-gray-50 p-1.5 dark:border-gray-400 dark:bg-gray-400'
                },
                emptyMessage: {
                    type: String,
                    default: 'Geen items'
                },
                emptyDescription: {
                    type: String,
                    default: 'Maak je eerste item aan'
                },
                createButtonText: {
                    type: String,
                    default: 'Item aanmaken'
                }
            },

            data() {
                return {
                    stageLeads: {},
                    isLoading: true,
                    hideWonLost: true,
                    wonLostLabel: 'Toon gewonnen/verloren',
                    scrollTimeouts: {},
                };
            },

            mounted() {
                this.boot();
                this.setWonLostButtonText();
            },

            methods: {
                /**
                 * Format date to a more readable format
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
                 * Get lead name for display
                 */
                getLeadName(element) {
                    if (element.lead?.person?.name) {
                        return element.lead.person.name;
                    }
                    if (element.persons && element.persons.length > 0) {
                        return element.persons[0]?.name || (element.first_name ? `${element.first_name} ${element.last_name}` : element.name);
                    }
                    return element.first_name ? `${element.first_name} ${element.last_name}` : element.name;
                },

                /**
                 * Get lead title for display
                 */
                getLeadTitle(element) {
                    return element.description || element.name || '';
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
                 * Fetches the data
                 */
                get(requestedParams = {}) {
                    let params = {
                        view_type: 'kanban',
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
                        .get(this.getUrl, {
                            params: {
                                ...params,
                                ...requestedParams,
                            }
                        })
                        .then(response => {
                            this.isLoading = false;
                            return response;
                        })
                        .catch(error => {
                            console.log(error);
                            this.isLoading = false;
                        });
                },

                /**
                 * Filters the data
                 */
                filter(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index === 'all')),
                        ...filters.columns,
                    ];

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
                 * Searches the data
                 */
                search(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index !== 'all')),
                        ...filters.columns,
                    ];

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
                 * Toggle won/lost visibility
                 */
                toggleWonLost() {
                    this.hideWonLost = !this.hideWonLost;
                    this.setWonLostButtonText();

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
                 * Set won/lost button text
                 */
                setWonLostButtonText() {
                    this.wonLostLabel = this.hideWonLost ? 'Toon gewonnen/verloren' : 'Verberg gewonnen/verloren';
                },

                /**
                 * Handle scroll event
                 */
                handleScroll(stage, event) {
                    if (this.scrollTimeouts && this.scrollTimeouts[stage.id]) {
                        clearTimeout(this.scrollTimeouts[stage.id]);
                    }

                    if (!this.scrollTimeouts) {
                        this.scrollTimeouts = {};
                    }

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
                    }, 150);
                },

                /**
                 * Append data
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

                /**
                 * Update stage
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
                            .put(this.updateUrl.replace('replace', event.added.element.id), {
                                'pipeline_stage_id': stage.id
                            })
                            .then(response => {
                                this.$emitter.emit('add-flash', { type: 'success', message: 'Stage updated successfully.' });
                            })
                            .catch(error => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Failed to update stage.' });
                            });
                    }
                },
            }
        });
    </script>
@endPushOnce