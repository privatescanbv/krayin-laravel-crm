<x-admin::layouts>
    <x-slot:title>
        Monitor Resource Planning
    </x-slot>

    <x-adminc::planning.components.planning-calendar/>
    <x-adminc::planning.components.multiselect-filter/>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <div class="text-xl font-bold">Monitor Resource Planning</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Bekijk alle resources en hun bezetting</div>
            </div>
        </div>

        <div id="resource-planning-monitor" class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4">
            <v-resource-planning-monitor></v-resource-planning-monitor>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-resource-planning-monitor-template">
            <div class="flex flex-col gap-4">
                <v-planning-calendar
                    ref="calendar"
                    :view-type="viewType"
                    :availability-url="availabilityUrl"
                    :auto-load="false"
                    @loaded="onCalendarLoaded"
                >
                    <template #filters>
                        <!-- Filters and View Controls -->
                        <div class="filters-bar rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/60 p-3 md:p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Left: Filters -->
                                <div class="flex flex-wrap items-start gap-3">
                                    <div class="w-full md:w-56">
                                        <v-multiselect-filter
                                            v-model="filters.resource_type_ids"
                                            :options="resourceTypeOptions"
                                            label="Resource type"
                                            placeholder="Alle types"
                                        ></v-multiselect-filter>
                                    </div>
                                    <div class="w-full md:w-56">
                                        <v-multiselect-filter
                                            v-model="filters.clinic_ids"
                                            :options="clinicOptions"
                                            label="Kliniek"
                                            placeholder="Alle klinieken"
                                        ></v-multiselect-filter>
                                    </div>
                                    <div class="w-full md:w-56">
                                        <v-multiselect-filter
                                            v-model="filters.resource_ids"
                                            :options="filteredResourceOptions"
                                            label="Resource"
                                            placeholder="Alle resources"
                                        ></v-multiselect-filter>
                                    </div>
                                    <div class="w-full md:w-56 flex items-end">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="filters.show_available_only"
                                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                            />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Toon alleen beschikbaar</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Right: View controls -->
                                <div class="flex flex-col gap-3">
                                    <!-- View toggle -->
                                    <div class="flex items-center justify-end gap-3">
                                        <div class="flex border border-gray-300 dark:border-gray-700 rounded-md overflow-hidden">
                                            <button
                                                @click="setViewType('week')"
                                                :class="['px-3 py-1 text-sm', viewType === 'week' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800']"
                                            >
                                                Week
                                            </button>
                                            <button
                                                @click="setViewType('month')"
                                                :class="['px-3 py-1 text-sm', viewType === 'month' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800']"
                                            >
                                                Maand
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Calendar controls -->
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="secondary-button" @click="prevPeriod">Vorige</button>
                                        <div class="text-sm font-medium text-gray-800 dark:text-gray-200">@{{ periodLabel }}</div>
                                        <button class="secondary-button" @click="nextPeriod">Volgende</button>
                                        <button class="primary-button" @click="loadAvailability">Zoeken</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </v-planning-calendar>
            </div>
        </script>

        <script type="module">
            app.component('v-resource-planning-monitor', {
                template: '#v-resource-planning-monitor-template',
                data() {
                    return {
                        viewType: 'week',
                        filters: {
                            resource_type_ids: [],
                            clinic_ids: [],
                            resource_ids: [],
                            show_available_only: true,
                        },
                        resourceTypes: @json($resourceTypes),
                        resources: @json($resources),
                        clinics: @json($clinics),
                        availabilityUrl: "{{ route('admin.planning.monitor.availability') }}",
                    };
                },
                computed: {
                    resourceTypeOptions() {
                        return this.resourceTypes.map(rt => ({ value: rt.id, label: rt.name }));
                    },
                    clinicOptions() {
                        return this.clinics.map(c => ({ value: c.id, label: c.name }));
                    },
                    filteredResourceOptions() {
                        let filtered = this.resources;

                        // Filter by resource type if selected
                        if (this.filters.resource_type_ids.length > 0) {
                            filtered = filtered.filter(r => this.filters.resource_type_ids.includes(r.resource_type_id));
                        }

                        // Filter by clinic if selected
                        if (this.filters.clinic_ids.length > 0) {
                            filtered = filtered.filter(r => this.filters.clinic_ids.includes(r.clinic_id));
                        }

                        return filtered.map(r => {
                            const clinic = this.clinics.find(c => c.id === r.clinic_id);
                            return {
                                value: r.id,
                                label: r.name + (clinic ? ' (' + clinic.name + ')' : '')
                            };
                        });
                    },
                    periodLabel() {
                        return this.$refs.calendar?.periodLabel || '';
                    }
                },
                mounted() {
                    this.loadAvailability();
                },
                methods: {
                    setViewType(type) {
                        this.viewType = type;
                        if (type === 'month') {
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfMonth(new Date());
                            this.$refs.calendar.window.end = this.$refs.calendar.endOfMonth(new Date());
                        } else {
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfWeek(new Date());
                            const end = new Date(this.$refs.calendar.window.start);
                            end.setDate(this.$refs.calendar.window.start.getDate() + 6);
                            this.$refs.calendar.window.end = end;
                        }
                        this.loadAvailability();
                    },
                    prevPeriod() {
                        if (this.viewType === 'week') {
                            const s = new Date(this.$refs.calendar.window.start);
                            s.setDate(s.getDate() - 7);
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfWeek(s);
                            const e = new Date(this.$refs.calendar.window.start);
                            e.setDate(e.getDate() + 6);
                            this.$refs.calendar.window.end = e;
                        } else {
                            const s = new Date(this.$refs.calendar.window.start);
                            s.setMonth(s.getMonth() - 1);
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfMonth(s);
                            this.$refs.calendar.window.end = this.$refs.calendar.endOfMonth(s);
                        }
                        this.loadAvailability();
                    },
                    nextPeriod() {
                        if (this.viewType === 'week') {
                            const s = new Date(this.$refs.calendar.window.start);
                            s.setDate(s.getDate() + 7);
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfWeek(s);
                            const e = new Date(this.$refs.calendar.window.start);
                            e.setDate(e.getDate() + 6);
                            this.$refs.calendar.window.end = e;
                        } else {
                            const s = new Date(this.$refs.calendar.window.start);
                            s.setMonth(s.getMonth() + 1);
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfMonth(s);
                            this.$refs.calendar.window.end = this.$refs.calendar.endOfMonth(s);
                        }
                        this.loadAvailability();
                    },
                    async loadAvailability() {
                        const params = {};

                        if (this.filters.resource_type_ids.length > 0) {
                            params.resource_type_ids = this.filters.resource_type_ids.join(',');
                        }
                        if (this.filters.clinic_ids.length > 0) {
                            params.clinic_ids = this.filters.clinic_ids.join(',');
                        }
                        if (this.filters.resource_ids.length > 0) {
                            params.resource_ids = this.filters.resource_ids.join(',');
                        }
                        if (this.filters.show_available_only) {
                            params.show_available_only = '1';
                        }

                        await this.$refs.calendar.loadAvailability(params);
                    },
                    onCalendarLoaded(data) {
                        // Handle any post-load logic if needed
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
