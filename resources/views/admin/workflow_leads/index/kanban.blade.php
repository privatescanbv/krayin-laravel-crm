{!! view_render_event('admin.workflow-leads.index.kanban.before') !!}

<!-- Kanban Vue Component -->
<v-workflow-leads-kanban ref="workflowLeadsKanban">
    <div class="flex flex-col gap-4">
        <!-- Shimmer -->
        <x-admin::shimmer.leads.index.kanban />
    </div>
</v-workflow-leads-kanban>

{!! view_render_event('admin.workflow-leads.index.kanban.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-workflow-leads-kanban-template"
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
                    </div>
                </div>

                {!! view_render_event('admin.workflow-leads.index.kanban.content.before') !!}

                <div class="flex gap-2.5 overflow-x-auto">
                    <!-- Pipeline Stage Cards -->
                    <div
                        class="flex min-w-[275px] max-w-[275px] flex-col gap-1 rounded-lg border border-gray-200 dark:border-gray-800"
                        v-for="(stage, index) in stages"
                    >
                        {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.header.before') !!}

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

                                    @if (bouncer()->hasPermission('workflow-leads.create'))
                                        <a
                                            :href="'{{ route('admin.workflow-leads.create') }}' + '?pipeline_stage_id=' + stage.id"
                                            class="icon-add cursor-pointer rounded p-1 text-lg text-white transition-all hover:bg-white hover:bg-opacity-20"
                                            target="_blank"
                                        >
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.header.after') !!}

                        {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.body.before') !!}

                        <!-- Draggable Workflow Lead Cards -->
                        <draggable
                            class="flex h-[calc(100vh-317px)] flex-col gap-2 overflow-y-auto p-2"
                            :class="{ 'justify-center': stage.leads.data.length === 0 }"
                            ghost-class="draggable-ghost"
                            handle=".workflow-lead-item"
                            v-bind="{animation: 200}"
                            :list="stage.leads.data"
                            item-key="id"
                            group="workflow-leads"
                            @change="updateStage(stage, $event)"
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
                                                No workflow leads
                                            </p>

                                            <p class="!text-sm text-gray-400 dark:text-gray-400">
                                                Create your first workflow lead
                                            </p>
                                        </div>

                                        @if (bouncer()->hasPermission('workflow-leads.create'))
                                            <a
                                                :href="'{{ route('admin.workflow-leads.create') }}' + '?pipeline_stage_id=' + stage.id"
                                                class="secondary-button"
                                            >
                                                Sales order aanmaken
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </template>

                            <!-- Workflow Lead Card -->
                            <template #item="{ element: lead }">
                                {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.body.card.before') !!}

                                <a
                                    class="workflow-lead-item flex cursor-pointer flex-col gap-2 rounded-md border border-gray-100 bg-gray-50 p-1.5 dark:border-gray-400 dark:bg-gray-400"
                                    :href="'{{ route('admin.workflow-leads.view', 'replaceId') }}'.replace('replaceId', lead.id)"
                                    style="min-height:unset;"
                                >
                                    {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.body.card.header.before') !!}

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

                                    {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.body.card.header.after') !!}

                                    {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.body.card.title.before') !!}

                                    <!-- Workflow Lead Title -->
                                    <p class="text-[12px] font-medium leading-tight mb-0.5">
                                        @{{ lead.description }}
                                    </p>

                                    {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.body.card.title.after') !!}

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
                                    </div>
                                </a>

                                {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.body.card.after') !!}
                            </template>
                        </draggable>

                        {!! view_render_event('admin.workflow-leads.index.kanban.content.stage.body.after') !!}
                    </div>
                </div>

                {!! view_render_event('admin.workflow-leads.index.kanban.content.after') !!}
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-workflow-leads-kanban', {
            template: '#v-workflow-leads-kanban-template',

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
                };
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
                 * Initialization
                 */
                boot() {
                    this.get();
                },

                /**
                 * Fetches the workflow leads
                 */
                get() {
                    this.$axios
                        .get("{{ route('admin.workflow-leads.get') }}", {
                            params: {
                                view_type: 'kanban',
                                pipeline_id: '{{ request('pipeline_id') }}'
                            }
                        })
                        .then(response => {
                            this.isLoading = false;

                            // Update stages with leads data
                            const data = response.data || {};

                            this.stages.forEach(stage => {
                                if (data[stage.sort_order]) {
                                    stage.leads.data = data[stage.sort_order].leads.data;
                                    stage.leads.meta = data[stage.sort_order].leads.meta;
                                } else {
                                    stage.leads.data = [];
                                    stage.leads.meta.total = 0;
                                }
                            });
                        })
                        .catch(error => {
                            console.log(error);
                            this.isLoading = false;
                        });
                },

                /**
                 * Updates the stage with the latest workflow lead data.
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
                        stage.leads.meta.total = stage.leads.meta.total - 1;
                        return;
                    }

                    if (event.added) {
                        stage.leads.meta.total = stage.leads.meta.total + 1;

                        this.$axios
                            .put("{{ route('admin.workflow-leads.update', 'replace') }}".replace('replace', event.added.element.id), {
                                'pipeline_stage_id': stage.id
                            })
                            .then(response => {
                                this.$emitter.emit('add-flash', { type: 'success', message: 'Workflow lead stage updated successfully.' });
                            })
                            .catch(error => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Failed to update workflow lead stage.' });
                            });
                    }
                },
            }
        });
    </script>
@endPushOnce
