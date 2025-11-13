@php use Carbon\Carbon; @endphp
<x-admin::layouts>
    <x-slot:title>
        Resource Planning - Order #{{ $order->id }}
    </x-slot>

    <x-adminc::planning.components.planning-calendar/>
    <x-adminc::planning.components.multiselect-filter/>

    <div class="flex flex-col gap-4">
        <x-adminc::planning.components.page-header
            title="Resource Planning - Order #{{ $order->id }}"
            :subtitle="$order->title"
            :actions="[
                ['url' => route('admin.orders.edit', ['id' => $order->id]), 'label' => 'Terug naar order'],
                ['url' => route('admin.planning.monitor.index'), 'label' => 'Alle resources']
            ]"
        />

        <!-- Order Items Panel -->
        <x-adminc::planning.components.order-items-panel :order-items="$order->orderItems" />

        <!-- Resource Planning Calendar -->
        @php
            $orderItems = $order->orderItems->map(function ($item) {
                // Get duration from the first partner product (if any)
                $duration = null;
                if ($item->product && method_exists($item->product, 'partnerProducts')) {
                    $partnerProduct = $item->product->partnerProducts()->first();
                    $duration = $partnerProduct?->duration;
                }

                return [
                    'id' => $item->id,
                    'product_name' => $item->product?->name ?? 'Onbekend product',
                    'quantity' => $item->quantity,
                    'duration' => $duration, // Duration in minutes
                    'status' => (is_string($item->status) ? $item->status : ($item->status?->value ?? 'new')),
                    'can_plan' => $item->product && method_exists($item->product, 'partnerProducts') && $item->product->partnerProducts()->exists(),
                    'bookings' => $item->resourceOrderItems->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'resource_id' => $booking->resource_id,
                            'resource_name' => $booking->resource?->name ?? 'Onbekend',
                            'from' => Carbon::parse($booking->from)->toIso8601String(),
                            'to' => Carbon::parse($booking->to)->toIso8601String(),
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray();
        @endphp
        <div id="order-resource-planning"
             class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4">
            <v-order-resource-planning
                :order-id="{{ $order->id }}"
                :order-items='@json($orderItems)'
            ></v-order-resource-planning>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-order-resource-planning-template">
            <div class="flex flex-col gap-4">
                <v-planning-calendar
                    ref="calendar"
                    :view-type="viewType"
                    :availability-url="availabilityUrl"
                    :auto-load="false"
                    @loaded="onCalendarLoaded"
                    @block-click="openBook"
                >
                    <template #filters>
                        <x-adminc::planning.components.filters-bar :show-order-item-filter="true" />
                    </template>

                    <template #modals>
                        <x-adminc::planning.components.booking-modal />
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

            app.component('v-order-resource-planning', {
                template: '#v-order-resource-planning-template',
                props: ['orderId', 'orderItems'],
                mixins: [planningCalendarMixin],
                data() {
                    return {
                        ...planningCalendarMixin.data(),
                        filters: {
                            ...planningCalendarMixin.data().filters,
                            order_item_ids: [],
                            show_available_only: false,
                        },
                        form: {
                            order_item_id: null,
                            resource_id: null,
                            from: '',
                            to: '',
                            replace_existing: true
                        },
                        resources: [],
                        resourceTypes: @json($resourceTypes),
                        clinics: @json($clinics),
                        availabilityUrl: "{{ route('admin.planning.monitor.order.availability', ['orderId' => $order->id]) }}",
                        resourceTypesUrl: "{{ route('admin.planning.monitor.order.resource_types', ['orderId' => $order->id]) }}"
                    };
                },
                computed: {
                    ...planningCalendarMixin.computed,
                    orderItemOptions() {
                        return this.orderItems.map(item => ({
                            value: item.id,
                            label: item.product_name + ' (Aantal: ' + item.quantity + ')' + (!item.can_plan ? ' - Niet planbaar' : '')
                        }));
                    },
                    // Sorted order items: unplanned first, then planned
                    sortedOrderItems() {
                        const unplanned = this.orderItems.filter(item =>
                            item.can_plan && (!item.bookings || item.bookings.length === 0)
                        );
                        const planned = this.orderItems.filter(item =>
                            item.can_plan && item.bookings && item.bookings.length > 0
                        );
                        const notPlanable = this.orderItems.filter(item => !item.can_plan);
                        return [...unplanned, ...planned, ...notPlanable];
                    },
                    selectedOrderItem() {
                        if (!this.form.order_item_id) return null;
                        return this.orderItems.find(item => item.id === this.form.order_item_id);
                    }
                },
                mounted() {
                    // Initialize currentWeekStart with calendar's initial window
                    this.$nextTick(() => {
                        if (this.$refs.calendar?.window?.start) {
                            this.currentWeekStart = new Date(this.$refs.calendar.window.start);
                        }
                    });
                    this.loadOrderResourceTypes();
                },
                watch: {
                    'form.order_item_id'() {
                        // Recalculate end time when order item changes
                        if (this.form.from) {
                            this.calculateEndTime();
                        }
                    }
                },
                methods: {
                    ...planningCalendarMixin.methods,
                    calculateEndTime() {
                        if (!this.form.from || !this.selectedOrderItem || !this.selectedOrderItem.duration) {
                            return;
                        }

                        const startTime = new Date(this.form.from);
                        const durationMinutes = this.selectedOrderItem.duration;
                        const endTime = new Date(startTime.getTime() + (durationMinutes * 60 * 1000));

                        // Format to datetime-local format (YYYY-MM-DDTHH:MM)
                        const pad = (n) => String(n).padStart(2, '0');
                        this.form.to = `${endTime.getFullYear()}-${pad(endTime.getMonth() + 1)}-${pad(endTime.getDate())}T${pad(endTime.getHours())}:${pad(endTime.getMinutes())}`;
                    },
                    async loadOrderResourceTypes() {
                        try {
                            const response = await fetch(this.resourceTypesUrl, {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (response.ok) {
                                const data = await response.json();
                                // Pre-select the resource types for this order
                                this.filters.resource_type_ids = data.resource_types.map(rt => rt.id);
                                // Load availability with the pre-selected resource types
                                this.loadAvailability();
                            }
                        } catch (error) {
                            console.error('Error loading order resource types:', error);
                            // Still load availability even if resource types fail
                            this.loadAvailability();
                        }
                    },
                    formatBookingDate(dateString) {
                        const date = new Date(dateString);
                        const day = String(date.getDate()).padStart(2, '0');
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const year = date.getFullYear();
                        return `${day}-${month}-${year}`;
                    },
                    formatBookingTime(dateString) {
                        const date = new Date(dateString);
                        const hours = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');
                        return `${hours}:${minutes}`;
                    },
                    openBook(block) {
                        this.form.resource_id = block.resource_id;
                        const pad = (n) => String(n).padStart(2, '0');
                        const toLocal = (dt) => `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                        this.form.from = toLocal(new Date(block.from));
                        this.form.to = toLocal(new Date(block.to));
                        // Preselect the first order item that is planable and not yet planned
                        if (!this.form.order_item_id) {
                            const candidate = this.sortedOrderItems.find(item => item.can_plan && (!item.bookings || item.bookings.length === 0));
                            if (candidate) {
                                this.form.order_item_id = candidate.id;
                            }
                        }
                        this.$refs.bookModal.toggle();
                    },
                    async submitBooking() {
                        if (this.$refs.calendar.loading) return;

                        if (!this.form.order_item_id) {
                            this.$emitter.emit('add-flash', {type: 'error', message: 'Selecteer een orderitem'});
                            return;
                        }

                        this.$refs.calendar.loading = true;
                        try {
                            const url = "{{ route('admin.planning.monitor.order_item.book', ['orderItemId' => '___ID___']) }}".replace('___ID___', this.form.order_item_id);
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                            if (!csrfToken) {
                                throw new Error('CSRF token niet gevonden');
                            }

                            const res = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({
                                    resource_id: this.form.resource_id,
                                    from: this.form.from,
                                    to: this.form.to,
                                    replace_existing: !!this.form.replace_existing
                                })
                            });

                            if (res.ok) {
                                this.$refs.bookModal.toggle();
                                this.$emitter.emit('add-flash', {type: 'success', message: 'Ingeboekt'});
                                this.form = {
                                    order_item_id: null,
                                    resource_id: null,
                                    from: '',
                                    to: '',
                                    replace_existing: true
                                };
                                setTimeout(() => {
                                    this.$refs.calendar.loading = false;
                                    this.loadAvailability().catch(error => {
                                        console.error('Error reloading availability:', error);
                                        this.$refs.calendar.loading = false;
                                    });
                                }, 100);
                            } else {
                                const data = await res.json().catch(() => ({}));
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: data.message || `HTTP ${res.status}: ${res.statusText}`
                                });
                            }
                        } catch (error) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: `Fout bij inboeken: ${error.message}`
                            });
                        } finally {
                            this.$refs.calendar.loading = false;
                        }
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
