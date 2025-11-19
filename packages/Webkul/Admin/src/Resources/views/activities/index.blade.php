<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.activities.index.title')
    </x-slot>

    {!! view_render_event('admin.activities.index.activities.before') !!}

    <!-- Activities Datagrid -->
    <v-activities>
        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="activities"/>

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.activities.index.title')
                    </div>
                </div>

                <div class="flex gap-2">
                    <i class="icon-list cursor-pointer rounded p-1.5 text-2xl"></i>

                    <i class="icon-calendar cursor-pointe rounded p-1.5 text-2xl"></i>
                </div>
            </div>

            <!-- DataGrid Shimmer -->
            @if (
                request()->get('view-type') == 'table'
                || ! request()->has('view-type')
            )
                <x-admin::shimmer.datagrid :is-multi-row="true"/>
            @endif
        </div>
    </v-activities>

    {!! view_render_event('admin.activities.index.activities.after') !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-activities-template"
        >
            <div class="flex flex-col gap-4">
                <div
                    class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="flex flex-col gap-2">
                        <x-admin::breadcrumbs name="activities"/>

                        <div class="text-xl font-bold dark:text-white">
                            @lang('admin::app.activities.index.title')
                        </div>
                    </div>

                    {!! view_render_event('admin.activities.index.toggle_view.before') !!}

                    <div class="flex items-center gap-4">
                        <!-- Views Dropdown -->
                        <div v-show="hasViews" class="relative">
                            <select
                                class="rounded-md border bg-white px-3 py-2 text-sm dark:bg-gray-900 dark:border-gray-800 dark:text-gray-300"
                                @change="onViewChange"
                                :value="currentView"
                            >
                                <option v-for="(viewData, viewKey) in availableViews" :key="viewKey" :value="viewKey">
                                    @{{ viewData.label || viewKey }}
                                </option>
                            </select>
                        </div>

                        <!-- View Toggle Buttons -->
                        <div class="flex">
                            <i
                                class="icon-list cursor-pointer rounded-md p-1.5 text-2xl"
                                :class="{'bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-white': viewType == 'table'}"
                                @click="toggleView('table')"
                            ></i>

                            <i
                                class="icon-calendar cursor-pointer rounded-md p-1.5 text-2xl"
                                :class="{'bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-white': viewType == 'calendar'}"
                                @click="toggleView('calendar')"
                            ></i>
                        </div>
                    </div>

                    {!! view_render_event('admin.activities.index.toggle_view.after') !!}
                </div>

                <!-- Table View -->
                <template v-if="viewType == 'table'">
                    {!! view_render_event('admin.activities.index.datagrid.before') !!}

                    <x-admin::datagrid
                        src="/admin/activities/get?view={{ request()->get('view', $currentView ?? 'for_me') }}"
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
                                <!-- Desktop View -->
                                <div
                                    v-for="record in available.records"
                                    class="row grid items-center gap-2.5 border-b px-4 py-4 text-gray-600 transition-all hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950 max-lg:hidden"
                                    :style="`grid-template-columns: repeat(${$parent.gridsCount || available.columns.filter(c => c.visibility).length + (available.massActions.length ? 1 : 0) + (available.actions.length ? 1 : 0)}, minmax(0, 1fr))`"
                                    :key="record.id"
                                >
                                    <!-- Mass Actions -->
                                    <div class="flex select-none items-center gap-2.5" v-if="available.massActions.length">
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

                                    <!-- Individual columns based on available.columns -->
                                    <template v-for="column in available.columns" :key="column.index">
                                        <div v-if="column.visibility" class="flex flex-col gap-1.5">
                                            <!-- ID Column -->
                                            <template v-if="column.index === 'id'">
                                                <p class="text-gray-600 dark:text-gray-300">@{{ record.id }}</p>
                                            </template>

                                            <!-- Title Column -->
                                            <template v-else-if="column.index === 'title'">
                                                <p class="text-gray-600 dark:text-gray-300 font-medium">@{{ record.title }}</p>
                                            </template>

                                            <!-- Is Done Column -->
                                            <template v-else-if="column.index === 'is_done'">
                                                <div v-html="record.is_done"></div>
                                            </template>

                                            <!-- Assigned User Column -->
                                            <template v-else-if="column.index === 'assigned_user_id'">
                                                <div v-html="record.assigned_user_id"></div>
                                            </template>

                                            <!-- Group Column -->
                                            <template v-else-if="column.index === 'group'">
                                                <p class="text-gray-600 dark:text-gray-300" v-html="record.group"></p>
                                            </template>

                                            <!-- Comment Column -->
                                            <template v-else-if="column.index === 'comment'">
                                                <p class="text-gray-600 dark:text-gray-300 text-sm" v-if="record.comment">
                                                    @{{ record.comment && record.comment.length > 100 ?
                                                    record.comment.slice(0, 100) + '...' : record.comment }}
                                                </p>
                                            </template>

                                            <!-- Related Entity Column -->
                                            <template v-else-if="column.index === 'related_entity'">
                                                <div v-html="record.related_entity" class="text-sm"></div>
                                            </template>

                                            <!-- Type Column -->
                                            <template v-else-if="column.index === 'type'">
                                                <div v-html="record.type" class="text-sm"></div>
                                            </template>

                                            <!-- Created At Column -->
                                            <template v-else-if="column.index === 'created_at'">
                                                <p class="text-gray-600 dark:text-gray-300 text-sm">@{{ record.created_at }}</p>
                                            </template>

                                            <!-- Days Until Deadline Column -->
                                            <template v-else-if="column.index === 'days_until_deadline'">
                                                <div v-html="record.days_until_deadline" class="text-sm"></div>
                                            </template>

                                            <!-- Default Column -->
                                            <template v-else>
                                                <div v-html="record[column.index]" class="text-sm"></div>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Actions Column -->
                                    <div class="flex items-center justify-end gap-1.5" v-if="available.actions.length">
                                        <!-- Standard Actions -->
                                        <span
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                            :class="action.icon"
                                            :title="action.title"
                                            v-for="action in record.actions"
                                            @click="performAction(action)"
                                        ></span>

                                        <!-- Assign to Me Button -->
                                        <button
                                            v-if="!record.user_id"
                                            class="ml-2 px-2 py-1 rounded bg-blue-500 text-white text-xs hover:bg-blue-600 transition-colors"
                                            @click="assignToMe(record)"
                                            title="Aan mij toekennen"
                                        >
                                            Toekennen
                                        </button>

                                        <!-- Takeover Button -->
                                        <button
                                            v-if="record.user_id && record.user_id != {{ auth()->guard('user')->id() ?? 'null' }} && canTakeover"
                                            class="ml-2 px-2 py-1 rounded bg-orange-500 text-white text-xs hover:bg-orange-600 transition-colors"
                                            @click="takeoverActivity(record)"
                                            :title="'Overnemen van ' + (record.user && record.user.name ? record.user.name : 'onbekend')"
                                        >
                                            Overnemen
                                        </button>

                                        <!-- Unassign Button -->
                                        <button
                                            v-if="record.user_id == {{ auth()->guard('user')->id() ?? 'null' }}"
                                            class="ml-2 px-2 py-1 rounded bg-red-500 text-white text-xs hover:bg-red-600 transition-colors"
                                            @click="unassignActivity(record)"
                                            title="Ontkoppelen - maak beschikbaar voor anderen"
                                        >
                                            Ontkoppelen
                                        </button>
                                    </div>
                                </div>

                                <!-- Mobile Card View -->
                                <div
                                    class="hidden border-b px-4 py-4 text-black dark:border-gray-800 dark:text-gray-300 max-lg:block"
                                    v-for="record in available.records"
                                    :key="record.id"
                                >
                                    <div class="mb-2 flex items-center justify-between">
                                        <!-- Mass Actions for Mobile Cards -->
                                        <div class="flex w-full items-center justify-between gap-2">
                                            <p v-if="available.massActions.length">
                                                <label
                                                    :for="`mass_action_select_record_mobile_${record.id}`">
                                                    <input
                                                        type="checkbox"
                                                        :name="`mass_action_select_record_mobile_${record.id}`"
                                                        :value="record.id"
                                                        :id="`mass_action_select_record_mobile_${record.id}`"
                                                        class="peer hidden"
                                                        v-model="applied.massActions.indices"
                                                    >

                                                    <span
                                                        class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl text-gray-500 peer-checked:text-brandColor">
                                                    </span>
                                                </label>
                                            </p>

                                            <!-- Actions for Mobile -->
                                            <div class="flex w-full items-center justify-end gap-2">
                                                <div class="flex items-center" v-if="available.actions.length">
                                                    <span
                                                        class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                                        :class="action.icon"
                                                        :title="action.title"
                                                        v-for="action in record.actions"
                                                        @click="performAction(action)"
                                                    ></span>
                                                </div>

                                                <button
                                                    v-if="!record.user_id"
                                                    class="px-2 py-1 rounded bg-blue-500 text-white text-xs hover:bg-blue-600 transition-colors"
                                                    @click="assignToMe(record)"
                                                >
                                                    Toekennen
                                                </button>

                                                <button
                                                    v-if="record.user_id && record.user_id != {{ auth()->guard('user')->id() ?? 'null' }} && canTakeover"
                                                    class="ml-2 px-2 py-1 rounded bg-orange-500 text-white text-xs hover:bg-orange-600 transition-colors"
                                                    @click="takeoverActivity(record)"
                                                >
                                                    Overnemen
                                                </button>

                                                <button
                                                    v-if="record.user_id == {{ auth()->guard('user')->id() ?? 'null' }}"
                                                    class="ml-2 px-2 py-1 rounded bg-red-500 text-white text-xs hover:bg-red-600 transition-colors"
                                                    @click="unassignActivity(record)"
                                                >
                                                    Ontkoppelen
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card Content -->
                                    <div class="grid gap-2">
                                        <template v-for="column in available.columns">
                                            <div class="flex flex-wrap items-baseline gap-x-2" v-if="record[column.index] && column.visibility">
                                                <span class="text-slate-600 dark:text-gray-300 font-medium"
                                                      v-html="column.label + ':'"></span>
                                                <span class="break-words text-slate-900 dark:text-white"
                                                      v-html="record[column.index]"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </x-admin::datagrid>

                    {!! view_render_event('admin.activities.index.datagrid.after') !!}
                </template>

                <!-- Calendar View -->
                <template v-else>
                    {!! view_render_event('admin.activities.index.vue_calender.before') !!}

                    <v-calendar></v-calendar>

                    {!! view_render_event('admin.activities.index.vue_calender.after') !!}
                </template>
            </div>

            <!-- Assignment Conflict Modal -->
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

        <script
            type="text/x-template"
            id="v-calendar-template"
        >
            <v-vue-cal
                hide-view-selector
                :watchRealTime="true"
                :twelveHour="true"
                :disable-views="['years', 'year', 'month', 'day']"
                style="height: calc(100vh - 240px);"
                :class="{'vuecal--dark': theme === 'dark'}"
                :events="events"
                :time-format="'h:mm a'"
                :events-on-month-view="'stack'"
                :events-count-on-year-view="3"
                :overlapping-events-stacked="true"
                :min-event-width="60"
                :cell-click-hold="false"
                :sticky-events="true"
                :events-overlap="true"
                :detailed-time="true"
                @ready="getActivities"
                @view-change="getActivities"
                @event-click="goToActivity"
                locale="{{ app()->getLocale() }}"
            >
                <template #event="{ event }">
                    <div
                        class="vuecal__event-content"
                        v-tooltip="{
                            content: `
                                <div class='mb-1 font-semibold text-white'>\${event.title}</div>
                                <div class='mb-1 text-xs text-gray-300'>\${formatTime(event.start)} - \${formatTime(event.end)}</div>
                                \${event.description ? \`<div class='text-xs text-gray-200'>\${event.description}</div>\` : ''}
                            `,
                            html: true,
                            placement: 'top',
                            trigger: 'hover',
                            delay: { show: 200, hide: 100 }
                        }"
                    >
                        <div class="vuecal__event-title font-medium">
                            @{{ event.title }}
                        </div>

                        <div class="vuecal__event-time text-sm">
                            @{{ formatTime(event.start) }} - @{{ formatTime(event.end) }}
                        </div>
                    </div>
                </template>
            </v-vue-cal>
        </script>

        <script type="module">
            app.component('v-activities', {
                template: '#v-activities-template',

                data() {
                    return {
                        viewType: '{{ request('view-type') }}' || 'table',
                        currentView: 'for_me',
                        availableViews: {},
                        canTakeover: (function() {
                            const user = {{ auth()->guard('user')->user()->id }};
                            const canTakeover = {{ (function() {
                                $user = auth()->guard('user')->user();
                                return $user && $user->hasPermission('activities.takeover') ? 'true' : 'false';
                            })() }};
                            console.log('Takeover debug - User ID:', user, 'Can takeover:', canTakeover);
                            return canTakeover;
                        })(),
                        assignmentModal: {
                            show: false,
                            activity: null,
                            conflictData: null,
                        }
                    };
                },

                created() {
                    this.availableViews = {!! json_encode($views ?? []) !!};

                    // Get view from URL, cookie, or use default
                    const urlParams = new URLSearchParams(window.location.search);
                    let urlView = urlParams.get('view');

                    if (!urlView) {
                        // If no URL parameter, try to get from cookie
                        urlView = this.getCookie('selected_activity_view') || {!! json_encode($currentView ?? 'for_me') !!};

                        // If we have a cookie value and it's different from default, redirect to include it in URL
                        if (urlView && urlView !== 'for_me') {
                            const newUrl = window.location.pathname + '?view=' + urlView;
                            window.location.href = newUrl;
                            return; // Stop execution as we're redirecting
                        }
                    }

                    this.currentView = urlView;

                    // Store current view in cookie for persistence across pages
                    this.setCookie('selected_activity_view', urlView, 30); // 30 days
                },

                computed: {
                    hasViews() {
                        return this.availableViews && Object.keys(this.availableViews).length > 0;
                    }
                },

                mounted() {
                    // View handling is now done in created() method
                },

                methods: {
                    /**
                     * Toggle view type between table and calendar.
                     *
                     * @param {String} type
                     * @return {void}
                     */
                    toggleView(type) {
                        this.viewType = type;

                        let currentUrl = new URL(window.location);
                        currentUrl.searchParams.set('view-type', type);
                        window.history.pushState({}, '', currentUrl);
                    },

                    /**
                     * Handle view change from dropdown.
                     *
                     * @param {Event} event
                     * @return {void}
                     */
                    onViewChange(event) {
                        const selectedView = event.target.value;
                        this.setCookie('selected_activity_view', selectedView, 30); // 30 days

                        // Redirect to the new view
                        let currentUrl = new URL(window.location);
                        currentUrl.searchParams.set('view', selectedView);
                        window.location.href = currentUrl.toString();
                    },

                    /**
                     * Set a cookie with the given name, value, and expiration days.
                     *
                     * @param {String} name
                     * @param {String} value
                     * @param {Number} days
                     * @return {void}
                     */
                    setCookie(name, value, days) {
                        const expires = new Date();
                        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
                        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
                    },

                    /**
                     * Get a cookie value by name.
                     *
                     * @param {String} name
                     * @return {String|null}
                     */
                    getCookie(name) {
                        const nameEQ = name + "=";
                        const ca = document.cookie.split(';');
                        for(let i = 0; i < ca.length; i++) {
                            let c = ca[i];
                            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                        }
                        return null;
                    },

                    /**
                     * Assign activity to current user with conflict handling.
                     *
                     * @param {Object} record
                     * @return {void}
                     */
                    async assignToMe(record) {
                        if (!record.id) return;

                        try {
                            const response = await this.$axios.post(`/admin/activities/${record.id}/assign`);

                            // Success - update the record
                            record.user_id = response.data.data.user_id;
                            record.user = response.data.data.user;
                            record.assigned_at = response.data.data.assigned_at;

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: response.data.message
                            });

                        } catch (error) {
                            if (error.response?.status === 409) {
                                // Conflict - activity already assigned
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
                     *
                     * @param {Object} record
                     * @param {Object} conflictData
                     * @return {void}
                     */
                    handleAssignmentConflict(record, conflictData) {
                        if (conflictData.can_takeover) {
                            // Show modal with takeover option
                            this.assignmentModal = {
                                show: true,
                                activity: record,
                                conflictData: conflictData,
                            };
                        } else {
                            // Just show the conflict message
                            this.$emitter.emit('add-flash', {
                                type: 'warning',
                                message: conflictData.message
                            });
                        }
                    },

                    /**
                     * Takeover activity from another user.
                     *
                     * @param {Object} record
                     * @param {Boolean} fromModal
                     * @return {void}
                     */
                    async takeoverActivity(record, fromModal = false) {
                        if (!record.id) return;

                        try {
                            const response = await this.$axios.post(`/admin/activities/${record.id}/takeover`);

                            // Success - update the record
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
                     *
                     * @param {Object} record
                     * @return {void}
                     */
                    async unassignActivity(record) {
                        if (!record.id) return;

                        try {
                            const response = await this.$axios.post(`/admin/activities/${record.id}/unassign`);

                            // Success - update the record
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
                     *
                     * @return {void}
                     */
                    closeAssignmentModal() {
                        this.assignmentModal.show = false;
                    },
                },
            });
        </script>

        <script type="module">
            app.component('v-calendar', {
                template: '#v-calendar-template',

                data() {
                    return {
                        events: [],
                        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                    };
                },

                mounted() {
                    /**
                     * Listen for the theme change event.
                     *
                     * @return {void}
                     */
                    this.$emitter.on('change-theme', (theme) => this.theme = theme);

                    /**
                     * Listen for calendar refresh event.
                     *
                     * @return {void}
                     */
                    this.$emitter.on('refresh-calendar-view', () => {
                        // Trigger a calendar refresh by calling getActivities with current date range
                        const now = new Date();
                        const startOfWeek = new Date(now.setDate(now.getDate() - now.getDay()));
                        const endOfWeek = new Date(now.setDate(now.getDate() - now.getDay() + 6));

                        this.getActivities({
                            startDate: startOfWeek,
                            endDate: endOfWeek
                        });
                    });
                },

                methods: {
                    /**
                     * Get the activities for the calendar.
                     *
                     * @param {Object} {startDate}
                     * @param {Object} {endDate}
                     * @return {void}
                     */
                    getActivities({startDate, endDate}) {
                        this.$root.pageLoaded = false;

                        // Get current view from parent component or URL
                        const urlParams = new URLSearchParams(window.location.search);
                        const currentView = this.$parent.currentView || urlParams.get('view') || 'for_me';

                        const baseUrl = "/admin/activities/get?view_type=calendar";
                        const params = `&startDate=${new Date(startDate).toLocaleDateString("en-US")}&endDate=${new Date(endDate).toLocaleDateString("en-US")}&view=${currentView}`;

                        this.$axios.get(baseUrl + params)
                            .then(response => {
                                this.events = this.processEvents(response.data.activities);
                            })
                            .catch(error => {
                                console.error('Failed to fetch activities:', error);
                            });
                    },

                    /**
                     * Process events to improve their display
                     *
                     * @param {Array} events
                     * @return {Array}
                     */
                    processEvents(events) {
                        return events.map(event => {
                            if (
                                !event.background
                                || event.background === "#fff"
                                || event.background === "#ffffff"
                            ) {
                                const hash = this.hashString(event.id || event.title || '');

                                const colors = [
                                    '#4F46E5', '#0891B2', '#10B981', '#F59E0B', '#EC4899',
                                    '#8B5CF6', '#06B6D4', '#22C55E', '#F97316', '#D946EF'
                                ];

                                event.background = colors[Math.abs(hash) % colors.length];
                                event.textColor = '#ffffff';
                            }

                            return event;
                        });
                    },

                    /**
                     * Simple string hash function for consistent color generation
                     *
                     * @param {string} str
                     * @return {number}
                     */
                    hashString(str) {
                        let hash = 0;

                        for (let i = 0; i < str.length; i++) {
                            hash = ((hash << 5) - hash) + str.charCodeAt(i);
                            hash |= 0;
                        }

                        return hash;
                    },

                    /**
                     * Format time for display in event template
                     *
                     * @param {Date} date
                     * @return {string}
                     */
                    formatTime(date) {
                        if (!date) {
                            return '';
                        }

                        const dateObj = new Date(date);

                        let hours = dateObj.getHours();

                        const minutes = dateObj.getMinutes().toString().padStart(2, '0');

                        const ampm = hours >= 12 ? 'PM' : 'AM';

                        hours = hours % 12;

                        hours = hours ? hours : 12;

                        return `${hours}:${minutes} ${ampm}`;
                    },

                    /**
                     * Redirect to the activity edit page.
                     *
                     * @param {Object} event
                     * @return {void}
                     */
                    goToActivity(event) {
                        if (event.id) {
                            window.location.href = `{{ route('admin.activities.edit', ':id') }}`.replace(':id', event.id);
                        }
                    },
                },
            });
        </script>

        <script>
            /**
             * Update status for `is_done`.
             *
             * @param {Event} {target}
             * @return {void}
             */
            const updateStatus = ({target}, url) => {
                axios
                    .post(url, {
                        _method: 'put',
                        is_done: target.checked,
                    })
                    .then(response => {
                        window.emitter.emit('add-flash', {type: 'success', message: response.data.message});
                    })
                    .catch(error => {
                    });
            };
        </script>
    @endPushOnce

    @pushOnce('styles')
        <style>
            /* Base Event Styling */
            .vuecal__event {
                background-color: #0e90d9 !important;
                color: #fff !important;
                cursor: pointer;
                height: auto !important;
                border-radius: 4px;
                padding: 4px 6px;
                font-size: 14px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                transition: all 0.2s ease;
                overflow: hidden;
            }

            .vuecal__event:hover {
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
                transform: translateY(-1px);
            }

            .vuecal__event.done {
                background-color: #53c41a !important;
            }

            /* Event Title & Time */
            .vuecal__event-title {
                font-weight: 500;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .vuecal__event-time {
                font-size: 12px;
                opacity: 0.8;
            }

            /* More Events Indicator */
            .vuecal__cell-more-events {
                font-size: 12px;
                color: #666;
                padding: 2px 5px;
                text-align: center;
                cursor: pointer;
                border-radius: 3px;
                background-color: rgba(0, 0, 0, 0.04);
            }

            /* Events Count Badge */
            .vuecal__cell-events-count {
                background-color: rgba(66, 92, 240, 0.85);
                border-radius: 10px;
                padding: 0 4px;
                font-size: 11px;
            }

            /* Week View Stacking */
            .vuecal--week-view .vuecal__event-container {
                padding: 1px;
            }

            .vuecal__event-container--overlapped .vuecal__event {
                margin-top: 2px;
                min-height: 28px;
            }

            /* Dark Mode Styles */
            .vuecal--dark {
                background-color: #1F2937 !important;
                color: #FFFFFF !important;
                border-color: #374151 !important;
            }

            .vuecal--dark .vuecal__header,
            .vuecal--dark .vuecal__header-weekdays,
            .vuecal--dark .vuecal__header-months {
                background-color: #374151 !important;
                color: #FFFFFF !important;
            }

            .vuecal--dark .vuecal__day,
            .vuecal--dark .vuecal__month-view,
            .vuecal--dark .vuecal__week-view,
            .vuecal--dark .vuecal__day--weekend,
            .vuecal--dark .vuecal__day--selected {
                background-color: #1F2937 !important;
                color: #FFFFFF !important;
                border-color: #374151 !important;
            }

            .vuecal--dark .vuecal__event {
                background-color: #374151 !important;
                color: #FFFFFF !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            }

            .vuecal--dark .vuecal__cell-more-events {
                color: #ddd;
                background-color: rgba(255, 255, 255, 0.1);
            }
        </style>
    @endPushOnce
</x-admin::layouts>
