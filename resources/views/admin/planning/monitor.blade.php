<x-admin::layouts>
    <x-slot:title>
        Monitor Resource Planning
    </x-slot>

    <x-adminc::planning.components.planning-calendar/>
    <x-adminc::planning.components.multiselect-filter/>

    <div class="flex flex-col gap-4">
        <x-adminc::planning.components.page-header
            title="Monitor Resource Planning"
            subtitle="Bekijk alle resources en hun bezetting"
        />

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
                        <x-adminc::planning.components.filters-bar />
                    </template>
                </v-planning-calendar>
            </div>
        </script>

        <script type="module">
            // Common planning calendar mixin
            const planningCalendarMixin = {
                data() {
                    return {
                        viewType: 'week',
                        currentWeekStart: new Date(),
                        filters: {
                            resource_type_ids: [],
                            clinic_ids: [],
                            resource_ids: [],
                            show_available_only: false,
                        },
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
                    orderItemOptions() {
                        // Default empty array - can be overridden in components that have order items
                        return [];
                    },
                    periodLabel() {
                        if (this.viewType === 'week') {
                            const weekNumber = this.getWeekNumber(this.currentWeekStart);
                            return `Week ${weekNumber}`;
                        } else {
                            return this.currentWeekStart.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' });
                        }
                    }
                },
                methods: {
                    getWeekNumber(date) {
                        // ISO week number calculation
                        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
                        const dayNum = d.getUTCDay() || 7;
                        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
                        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
                        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
                    },
                    setViewType(type) {
                        this.viewType = type;
                        // Let the child component handle the window update through its watcher
                        this.$nextTick(() => {
                            this.loadAvailability();
                        });
                    },
                    prevPeriod() {
                        if (this.viewType === 'week') {
                            const s = new Date(this.$refs.calendar.window.start);
                            s.setDate(s.getDate() - 7);
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfWeek(s);
                            this.currentWeekStart = new Date(this.$refs.calendar.window.start);
                            const e = new Date(this.$refs.calendar.window.start);
                            e.setDate(e.getDate() + 6);
                            this.$refs.calendar.window.end = e;
                        } else {
                            const s = new Date(this.$refs.calendar.window.start);
                            s.setMonth(s.getMonth() - 1);
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfMonth(s);
                            this.currentWeekStart = new Date(this.$refs.calendar.window.start);
                            this.$refs.calendar.window.end = this.$refs.calendar.endOfMonth(s);
                        }
                        this.loadAvailability();
                    },
                    nextPeriod() {
                        if (this.viewType === 'week') {
                            const s = new Date(this.$refs.calendar.window.start);
                            s.setDate(s.getDate() + 7);
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfWeek(s);
                            this.currentWeekStart = new Date(this.$refs.calendar.window.start);
                            const e = new Date(this.$refs.calendar.window.start);
                            e.setDate(e.getDate() + 6);
                            this.$refs.calendar.window.end = e;
                        } else {
                            const s = new Date(this.$refs.calendar.window.start);
                            s.setMonth(s.getMonth() + 1);
                            this.$refs.calendar.window.start = this.$refs.calendar.startOfMonth(s);
                            this.currentWeekStart = new Date(this.$refs.calendar.window.start);
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

                        // Add order_item_ids if available (for order monitor)
                        if (this.filters.order_item_ids && this.filters.order_item_ids.length > 0) {
                            params.order_item_ids = this.filters.order_item_ids.join(',');
                        }

                        await this.$refs.calendar.loadAvailability(params);
                    },
                    onCalendarLoaded(data) {
                        // Handle any post-load logic if needed
                        this.resources = data.resources || [];
                    }
                }
            };

            app.component('v-resource-planning-monitor', {
                template: '#v-resource-planning-monitor-template',
                mixins: [planningCalendarMixin],
                data() {
                    return {
                        ...planningCalendarMixin.data(),
                        filters: {
                            ...planningCalendarMixin.data().filters,
                            show_available_only: true,
                        },
                        resourceTypes: @json($resourceTypes),
                        resources: @json($resources),
                        clinics: @json($clinics),
                        availabilityUrl: "{{ route('admin.planning.monitor.availability') }}",
                    };
                },
                mounted() {
                    // Initialize currentWeekStart with calendar's initial window
                    this.$nextTick(() => {
                        if (this.$refs.calendar?.window?.start) {
                            this.currentWeekStart = new Date(this.$refs.calendar.window.start);
                        }
                    });
                    this.loadAvailability();
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
