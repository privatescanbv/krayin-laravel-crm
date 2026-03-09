<x-admin::layouts>
    <x-slot:title>
        Dashboard / Werkbakken
    </x-slot>

    <v-operational-dashboard
        :initial-queues='@json($queues)'
        initial-queue-key="{{ $defaultQueueKey }}"
    >
        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
            >
                <div class="flex flex-col gap-1.5">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Dashboard / Werkbakken
                    </p>

                    <p class="text-xl font-bold dark:text-white">
                        Werkbakken
                    </p>
                </div>
            </div>

            <x-admin::shimmer.datagrid :is-multi-row="true"/>
        </div>
    </v-operational-dashboard>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-operational-dashboard-template"
        >
            <div class="flex flex-col gap-4">
                <!-- Header card -->
                <div
                    class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                >
                    <div class="flex flex-col gap-1.5">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Dashboard / Werkbakken
                        </p>

                        <p class="text-xl font-bold dark:text-white">
                            Werkbakken
                        </p>
                    </div>
                </div>

                <!-- Queue tabs -->
                <div class="rounded-lg border bg-white px-2 pt-2 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="queue in queues"
                            :key="queue.key"
                            type="button"
                            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm transition"
                            :class="queue.key === activeQueueKey
                                ? 'bg-brandColor text-white'
                                : 'text-gray-700 hover:bg-neutral-bg dark:text-gray-200 dark:hover:bg-gray-800'"
                            @click="setActiveQueue(queue.key)"
                        >
                            <span class="font-medium">
                                @{{ queue.label }}
                            </span>

                            <span class="flex items-center gap-1">
                                <!-- Overdue (red) -->
                                <span
                                    class="inline-flex min-w-[1.5rem] justify-center rounded-full bg-red-600 px-1 py-0.5 text-xs font-semibold text-white"
                                    v-if="queue.overdue > 0"
                                >
                                    @{{ queue.overdue }}
                                </span>

                                <!-- Open - overdue (neutral) -->
                                <span
                                    class="inline-flex min-w-[1.5rem] justify-center rounded-full bg-gray-100 px-1 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                >
                                    @{{ queue.open - queue.overdue }}
                                </span>
                            </span>
                        </button>
                    </div>

                    <!-- Active queue panel -->
                    <div class="mt-4 rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                        <!-- Title & toolbar -->
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    @{{ activeQueue?.label || '' }}
                                </h2>

                                <span
                                    v-if="activeQueue"
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                >
                                    @{{ activeQueue.open }} activiteiten
                                </span>
                            </div>

{{--                            <div class="flex items-center gap-2">--}}
{{--                                <!-- Location / department select placeholder (Hermapoli in Figma) -->--}}
{{--                                <select--}}
{{--                                    class="h-9 rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-brandColor focus:outline-none focus:ring-1 focus:ring-brandColor dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"--}}
{{--                                >--}}
{{--                                    <option>Hermapoli</option>--}}
{{--                                </select>--}}

{{--                                <button--}}
{{--                                    type="button"--}}
{{--                                    class="inline-flex h-9 items-center rounded-md border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"--}}
{{--                                >--}}
{{--                                    Filter--}}
{{--                                </button>--}}

{{--                                <button--}}
{{--                                    type="button"--}}
{{--                                    class="inline-flex h-9 items-center rounded-md bg-brandColor px-3 text-sm font-medium text-white shadow-sm hover:opacity-90"--}}
{{--                                >--}}
{{--                                    Toekennen--}}
{{--                                </button>--}}
{{--                            </div>--}}
                        </div>

                        <!-- Datagrid -->
                        <x-admin::datagrid
                            src="/admin/operational-dashboard/queues"
                            :isMultiRow="true"
                            ref="datagrid"
                        >
                            <template #body="{
                                isLoading,
                                available,
                                applied,
                                selectAll,
                                sort,
                                performAction
                            }">
                                <template v-if="isLoading">
                                    <x-admin::shimmer.datagrid.table.body :isMultiRow="true"/>
                                </template>

                                <template v-else>
                                    <!-- Desktop rows (reuse Activities layout) -->
                                    <div
                                        v-for="record in available.records"
                                        :key="record.id"
                                        class="row grid items-center gap-2.5 border-b px-4 py-4 text-gray-600 transition-all hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950 max-lg:hidden"
                                        :style="`grid-template-columns: repeat(${$parent.gridsCount || available.columns.filter(c => c.visibility).length + (available.massActions.length ? 1 : 0) + (available.actions.length ? 1 : 0)}, minmax(0, 1fr))`"
                                    >
                                        <!-- Mass actions -->
                                        <div
                                            class="flex select-none items-center gap-2.5"
                                            v-if="available.massActions.length"
                                        >
                                            <input
                                                type="checkbox"
                                                :name="`mass_action_select_record_${record.id}`"
                                                :id="`mass_action_select_record_${record.id}`"
                                                :value="record.id"
                                                class="peer hidden"
                                                v-model="applied.massActions.indices"
                                            >

                                            <label
                                                class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl text-gray-600 peer-checked:text-brandColor dark:text-gray-300"
                                                :for="`mass_action_select_record_${record.id}`"
                                            ></label>
                                        </div>

                                        <!-- Columns -->
                                        <template
                                            v-for="column in available.columns"
                                            :key="column.index"
                                        >
                                            <div v-if="column.visibility" class="flex flex-col gap-1.5">
                                                <template v-if="column.index === 'title'">
                                                    <p class="font-medium text-gray-900 dark:text-white">
                                                        @{{ record.title }}
                                                    </p>
                                                </template>
                                                <template v-else-if="column.index === 'assigned_user_id'">
                                                    <div v-html="record.assigned_user_id"></div>
                                                </template>
                                                <template v-else-if="column.index === 'created_at'">
                                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                                        @{{ record.created_at }}
                                                    </p>
                                                </template>
                                                <template v-else>
                                                    <div
                                                        class="text-sm"
                                                        v-html="record[column.index]"
                                                    ></div>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- Actions (shared with activities index) -->
                                        @include('admin::activities.partials.datagrid-actions-desktop')
                                    </div>

                                    <!-- Mobile card view -->
                                    <div
                                        class="hidden border-b px-4 py-4 text-black dark:border-gray-800 dark:text-gray-300 max-lg:block"
                                        v-for="record in available.records"
                                        :key="record.id"
                                    >
                                        <div class="mb-2 flex items-center justify-between">
                                            <div class="flex w-full items-center justify-between gap-2">
                                                <p v-if="available.massActions.length">
                                                    <label
                                                        :for="`mass_action_select_record_mobile_${record.id}`"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            :name="`mass_action_select_record_mobile_${record.id}`"
                                                            :value="record.id"
                                                            :id="`mass_action_select_record_mobile_${record.id}`"
                                                            class="peer hidden"
                                                            v-model="applied.massActions.indices"
                                                        >

                                                        <span
                                                            class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl text-gray-500 peer-checked:text-brandColor"
                                                        ></span>
                                                    </label>
                                                </p>

                                                @include('admin::activities.partials.datagrid-actions-mobile')
                                            </div>
                                        </div>

                                        <div class="grid gap-2">
                                            <template v-for="column in available.columns">
                                                <div
                                                    v-if="record[column.index] && column.visibility"
                                                    class="flex flex-wrap items-baseline gap-x-2"
                                                >
                                                    <span
                                                        class="font-medium text-slate-600 dark:text-gray-300"
                                                        v-html="column.label + ':'"
                                                    ></span>
                                                    <span
                                                        class="break-words text-slate-900 dark:text-white"
                                                        v-html="record[column.index]"
                                                    ></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </template>
                        </x-admin::datagrid>
                    </div>
                </div>
            </div>

            <!-- Assignment Conflict Modal (identical to activities index) -->
            <div v-if="assignmentModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                <div class="bg-white rounded-lg p-6 max-w-md mx-4 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold mb-4 dark:text-white">
                        Activiteit al toegekend
                    </h3>

                    <p class="mb-4 text-gray-600 dark:text-gray-300">
                        @{{ assignmentModal.conflictData?.message }}
                    </p>

                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                        Wil je deze activiteit overnemen?
                    </p>

                    <div class="flex gap-3 justify-end">
                        <button
                            @click="closeAssignmentModal()"
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            Annuleren
                        </button>

                        <button
                            @click="takeoverActivity(assignmentModal.activity, true)"
                            class="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600"
                        >
                            Overnemen
                        </button>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-operational-dashboard', {
                template: '#v-operational-dashboard-template',

                props: {
                    initialQueues: {
                        type: Array,
                        required: true,
                    },
                    initialQueueKey: {
                        type: String,
                        required: true,
                    },
                },

                data() {
                    return {
                        queues: this.initialQueues,
                        activeQueueKey: this.initialQueueKey,
                        currentUserId: {{ auth()->guard('user')->id() ?? 'null' }},
                        canTakeover: (function() {
                            const user = {{ auth()->guard('user')->user()->id ?? 'null' }};
                            const canTakeover = {{ (function() {
                                $user = auth()->guard('user')->user();
                                return $user && $user->hasPermission('activities.takeover') ? 'true' : 'false';
                            })() }};
                            console.log('Operational dashboard takeover debug - User ID:', user, 'Can takeover:', canTakeover);
                            return canTakeover;
                        })(),
                        assignmentModal: {
                            show: false,
                            activity: null,
                            conflictData: null,
                        },
                    };
                },

                computed: {
                    activeQueue() {
                        return this.queues.find((q) => q.key === this.activeQueueKey) || null;
                    },
                },

                methods: {
                    setActiveQueue(key) {
                        if (this.activeQueueKey === key) {
                            return;
                        }

                        this.activeQueueKey = key;

                        // Refresh datagrid when switching queues.
                        if (this.$refs.datagrid && typeof this.$refs.datagrid.get === 'function') {
                            this.$refs.datagrid.get({ queue: this.activeQueueKey });
                        }
                    },

                    /**
                     * Assign activity to current user with conflict handling.
                     */
                    async assignToMe(record) {
                        if (!record.id) return;

                        try {
                            const response = await this.$axios.post(`/admin/activities/${record.id}/assign`);

                            record.user_id = response.data.data.user_id;
                            record.user = response.data.data.user;
                            record.assigned_at = response.data.data.assigned_at;

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: response.data.message
                            });

                        } catch (error) {
                            if (error.response?.status === 409) {
                                this.handleAssignmentConflict(record, error.response.data);
                            } else {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: 'Kon niet toekennen: ' + (error.response?.data?.message || error.message)
                                });
                            }
                        }
                    },

                    /**
                     * Handle assignment conflict (activity already assigned to someone else).
                     */
                    handleAssignmentConflict(record, conflictData) {
                        if (conflictData.can_takeover) {
                            this.assignmentModal = {
                                show: true,
                                activity: record,
                                conflictData: conflictData,
                            };
                        } else {
                            this.$emitter.emit('add-flash', {
                                type: 'warning',
                                message: conflictData.message
                            });
                        }
                    },

                    /**
                     * Takeover activity from another user.
                     */
                    async takeoverActivity(record, fromModal = false) {
                        if (!record.id) return;

                        try {
                            const response = await this.$axios.post(`/admin/activities/${record.id}/takeover`);

                            record.user_id = response.data.data.user_id;
                            record.user = response.data.data.user;
                            record.assigned_at = response.data.data.assigned_at;

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: response.data.message
                            });

                            if (fromModal) {
                                this.assignmentModal.show = false;
                            }

                        } catch (error) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Kon niet overnemen: ' + (error.response?.data?.message || error.message)
                            });
                        }
                    },

                    /**
                     * Unassign activity.
                     */
                    async unassignActivity(record) {
                        if (!record.id) return;

                        try {
                            const response = await this.$axios.post(`/admin/activities/${record.id}/unassign`);

                            record.user_id = null;
                            record.user = null;
                            record.assigned_at = null;

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: response.data.message
                            });

                        } catch (error) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Kon niet ontkoppelen: ' + (error.response?.data?.message || error.message)
                            });
                        }
                    },

                    /**
                     * Close assignment modal.
                     */
                    closeAssignmentModal() {
                        this.assignmentModal.show = false;
                    },
                },

                mounted() {
                    // Ensure the initial queue is applied to the datagrid on first load.
                    if (this.$refs.datagrid && typeof this.$refs.datagrid.get === 'function') {
                        this.$refs.datagrid.get({ queue: this.activeQueueKey });
                    }
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>

