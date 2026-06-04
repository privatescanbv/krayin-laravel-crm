@php use Carbon\Carbon; @endphp
<x-admin::layouts>
    <x-slot:title>
        Resource Planning - Order #{{ $order->order_number }}
    </x-slot>

    <x-adminc::planning.components.planning-calendar/>
    <x-adminc::planning.components.multiselect-filter/>

    <div class="flex flex-col gap-4">
        <x-adminc::planning.components.page-header
            title="Resource Planning - Order #{{ $order->order_number }}"
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
                    'product_id' => $item->product_id,
                    'product_name' => $item->getProductName() ?: 'Onbekend product',
                    'person_name' => $item->person?->name ?? null,
                    'required_resource_type' => $item->resolvedResourceTypeName(),
                    'quantity' => $item->quantity,
                    'duration' => $duration, // Duration in minutes
                    'status' => (is_string($item->status) ? $item->status : ($item->status?->value ?? 'new')),
                    'can_plan' => $item->isPlannable(),
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
                :initial-order-items='@json($orderItems)'
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
                    :current-order-id="orderId"
                    @loaded="onCalendarLoaded"
                    @block-click="openBook"
                    @occupied-block-click="openEditBooking"
                    @column-click="openBookAtTime"
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
            /**
             * Returns true when the resource has an active partner product for this product.
             * Uses clinic_bookable_product_ids supplied by the server (mapResource).
             */
            function resourceCanBookProduct(resource, productId) {
                if (!productId) {
                    return true;
                }
                const bookable = resource.clinic_bookable_product_ids ?? [];
                return bookable.includes(productId);
            }

            function filterResourcesForProduct(resources, productId, requiredResourceType = null) {
                let candidates = resources;

                if (requiredResourceType) {
                    const byType = candidates.filter((r) => r.resource_type === requiredResourceType);
                    if (byType.length > 0) {
                        candidates = byType;
                    }
                }

                if (productId) {
                    candidates = candidates.filter((r) => resourceCanBookProduct(r, productId));
                }

                return candidates;
            }

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

                        // Note: order_item_ids narrowing is intentionally NOT applied here.
                        // Removing resources from this list when order items are selected causes
                        // already-selected resources to lose their label (showing raw ID instead).
                        // Order-item/product matching is only applied in bookingResourceOptions (modal).

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
                        this.resources = data.resources || [];
                        if (data.order_items) {
                            this.orderItems = data.order_items;
                        }
                        // Remove selected resource IDs that are no longer in the returned list
                        // to prevent the multiselect from showing raw IDs instead of names.
                        if (this.filters.resource_ids.length > 0) {
                            const available = new Set(this.resources.map(r => Number(r.id)));
                            this.filters.resource_ids = this.filters.resource_ids.filter(id => available.has(Number(id)));
                        }
                    }
                }
            };

            app.component('v-order-resource-planning', {
                template: '#v-order-resource-planning-template',
                props: ['orderId', 'initialOrderItems'],
                mixins: [planningCalendarMixin],
                data() {
                    return {
                        ...planningCalendarMixin.data(),
                        orderItems: JSON.parse(JSON.stringify(this.initialOrderItems)),
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
                        },
                        isEditing: false,
                        editingBookingId: null,
                        resources: [],
                        resourceTypes: @json($resourceTypes),
                        clinics: @json($clinics),
                        availabilityUrl: "{{ route('admin.planning.monitor.order.availability', ['orderId' => $order->id]) }}",
                        resourceTypesUrl: "{{ route('admin.planning.monitor.order.resource_types', ['orderId' => $order->id]) }}",
                        updateBookingUrlTemplate: "{{ route('admin.planning.monitor.booking.update', ['bookingId' => '___ID___']) }}",
                        deleteBookingUrlTemplate: "{{ route('admin.planning.monitor.booking.delete', ['bookingId' => '___ID___']) }}"
                    };
                },
                computed: {
                    ...planningCalendarMixin.computed,
                    orderItemOptions() {
                        return this.orderItems.map(item => ({
                            value: item.id,
                            label: this.orderItemLabel(item)
                                + (!item.can_plan ? ' - Niet planbaar' : '')
                        }));
                    },
                    // Sorted order items: unplanned first, then planned
                    unplannedItems() {
                        return this.orderItems.filter(item =>
                            item.can_plan && (!item.bookings || item.bookings.length === 0)
                        );
                    },
                    plannedItems() {
                        return this.orderItems.filter(item =>
                            item.can_plan && item.bookings && item.bookings.length > 0
                        );
                    },
                    notPlanableItems() {
                        return this.orderItems.filter(item => !item.can_plan);
                    },
                    sortedOrderItems() {
                        return [...this.unplannedItems, ...this.plannedItems, ...this.notPlanableItems];
                    },
                    selectedOrderItem() {
                        if (!this.form.order_item_id) return null;
                        return this.orderItems.find(item => item.id === this.form.order_item_id);
                    },
                    isOutsideAvailability() {
                        if (!this.form.from || !this.form.resource_id) return false;
                        // Waarschuwing alleen tonen als de resource buiten beschikbaarheid toestaat
                        const from = new Date(this.form.from);
                        const pad = n => String(n).padStart(2, '0');
                        const dayKey = `${from.getFullYear()}-${pad(from.getMonth()+1)}-${pad(from.getDate())}`;
                        const calendar = this.$refs.calendar;
                        if (!calendar) return false;
                        const dayBlocks = (calendar.blocks[this.form.resource_id] || {})[dayKey] || [];
                        const fromMs = from.getTime();
                        return !dayBlocks.some(b =>
                            b.type === 'available' &&
                            new Date(b.from).getTime() <= fromMs &&
                            new Date(b.to).getTime() > fromMs
                        );
                    },
                    isOutsideAvailabilityAllowedForResource() {
                        if (!this.form.from || !this.form.resource_id) return false;
                        // Waarschuwing alleen tonen als de resource buiten beschikbaarheid toestaat
                        const resource = this.resources.find(r => r.id === this.form.resource_id);
                        if (!resource?.allow_outside_availability) return false;
                        return true;
                    },
                    /**
                     * Resources passend bij het geselecteerde orderregel (zelfde regels als PartnerProductBookingValidator).
                     */
                    bookingResourceOptions() {
                        return filterResourcesForProduct(
                            this.resources,
                            this.selectedOrderItem?.product_id ?? null,
                            this.selectedOrderItem?.required_resource_type ?? null
                        );
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
                        this.syncBookingResourceId();
                    },
                    'filters.order_item_ids': {
                        handler() {
                            this.loadAvailability();
                        },
                        deep: true,
                    },
                },
                methods: {
                    ...planningCalendarMixin.methods,
                    /**
                     * Central label for order items: product name + person name.
                     * Single source of truth for how order items are displayed in dropdowns, modals, etc.
                     */
                    orderItemLabel(item) {
                        const parts = [item.product_name || 'Onbekend product'];
                        if (item.person_name) {
                            parts.push(item.person_name);
                        }
                        return parts.join(' — ') + ' (Aantal: ' + item.quantity + ')';
                    },
                    /**
                     * Als het orderregel wisselt: resource laten matchen met vereist type indien mogelijk.
                     */
                    syncBookingResourceId() {
                        const options = this.bookingResourceOptions;
                        if (this.form.resource_id == null || this.form.resource_id === '') {
                            if (options.length === 1) {
                                this.form.resource_id = options[0].id;
                            }
                            return;
                        }
                        const stillValid = options.some(
                            (r) => Number(r.id) === Number(this.form.resource_id)
                        );
                        if (!stillValid) {
                            this.form.resource_id = options.length > 0 ? options[0].id : null;
                        }
                    },
                    calculateEndTime() {
                        if (!this.form.from) {
                            return;
                        }

                        const pad = (n) => String(n).padStart(2, '0');
                        const startTime = new Date(this.form.from);
                        const durationMinutes = (this.selectedOrderItem && this.selectedOrderItem.duration)
                            ? this.selectedOrderItem.duration
                            : 30;
                        const endTime = new Date(startTime.getTime() + (durationMinutes * 60 * 1000));

                        // Format to datetime-local format (YYYY-MM-DDTHH:MM)
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
                    resetEditState() {
                        this.isEditing = false;
                        this.editingBookingId = null;
                    },
                    openBookAtTime({ from }) {
                        this.resetEditState();
                        const pad = (n) => String(n).padStart(2, '0');
                        const toLocal = (dt) => `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                        this.form.from = toLocal(from);
                        this.form.resource_id = null;
                        if (!this.form.order_item_id) {
                            const candidate = this.unplannedItems[0] || null;
                            if (candidate) this.form.order_item_id = candidate.id;
                        }
                        if (this.form.order_item_id) this.calculateEndTime();
                        this.$refs.bookModal.toggle();
                        this.$nextTick(() => this.syncBookingResourceId());
                    },
                    openBook(block) {
                        this.resetEditState();
                        const pad = (n) => String(n).padStart(2, '0');
                        const toLocal = (dt) => `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                        this.form.from = toLocal(new Date(block.from));
                        this.form.to = toLocal(new Date(block.to));

                        const clickedResource = this.resources.find((r) => r.id === block.resource_id);

                        if (!this.form.order_item_id) {
                            const slotType = clickedResource?.resource_type ?? null;
                            const candidate = this.firstUnplannedOrderItemForResource(clickedResource, slotType);
                            if (candidate) {
                                this.form.order_item_id = candidate.id;
                            }
                        }

                        this.$nextTick(() => {
                            const options = this.bookingResourceOptions;
                            const preferred = options.find((r) => r.id === block.resource_id);
                            this.form.resource_id = preferred
                                ? preferred.id
                                : (options.length > 0 ? options[0].id : null);
                            if (!this.form.to && this.form.from) {
                                this.calculateEndTime();
                            }
                        });

                        this.$refs.bookModal.toggle();
                    },
                    /**
                     * First unplanned item that can be booked on this resource (type + partner product at clinic).
                     */
                    firstUnplannedOrderItemForResource(resource, resourceTypeName) {
                        const candidates = this.unplannedItems.filter((item) => {
                            if (resourceTypeName && item.required_resource_type !== resourceTypeName) {
                                return false;
                            }
                            if (!resource || !item.product_id) {
                                return !resourceTypeName;
                            }
                            return resourceCanBookProduct(resource, item.product_id);
                        });

                        return candidates[0] ?? null;
                    },
                    openEditBooking(block) {
                        this.isEditing = true;
                        this.editingBookingId = block.booking_id;
                        const pad = (n) => String(n).padStart(2, '0');
                        const toLocal = (dt) => `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                        this.form.order_item_id = block.order_item_id;
                        this.form.resource_id = block.resource_id;
                        this.form.from = toLocal(new Date(block.from));
                        this.form.to = toLocal(new Date(block.to));
                        this.$refs.bookModal.toggle();
                    },
                    async submitBooking() {
                        if (this.$refs.calendar.loading) return;

                        if (!this.form.order_item_id) {
                            this.$emitter.emit('add-flash', {type: 'error', message: 'Selecteer een orderitem'});
                            return;
                        }

                        if (!this.form.resource_id) {
                            this.$emitter.emit('add-flash', {type: 'error', message: 'Selecteer een resource'});
                            return;
                        }

                        this.$refs.calendar.loading = true;
                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                            if (!csrfToken) {
                                throw new Error('CSRF token niet gevonden');
                            }

                            let url, method, body;

                            if (this.isEditing && this.editingBookingId) {
                                url = this.updateBookingUrlTemplate.replace('___ID___', this.editingBookingId);
                                method = 'PUT';
                                body = JSON.stringify({
                                    resource_id: this.form.resource_id,
                                    from: this.form.from,
                                    to: this.form.to,
                                });
                            } else {
                                url = "{{ route('admin.planning.monitor.order_item.book', ['orderItemId' => '___ID___']) }}".replace('___ID___', this.form.order_item_id);
                                method = 'POST';
                                body = JSON.stringify({
                                    resource_id: this.form.resource_id,
                                    from: this.form.from,
                                    to: this.form.to,
                                    replace_existing: true
                                });
                            }

                            const res = await fetch(url, {
                                method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body
                            });

                            if (res.ok) {
                                this.$refs.bookModal.toggle();
                                this.$emitter.emit('add-flash', {
                                    type: 'success',
                                    message: this.isEditing ? 'Boeking bijgewerkt' : 'Ingeboekt'
                                });
                                this.resetFormAndReload();
                            } else {
                                const data = await res.json().catch(() => ({}));
                                let errMsg = data.message;
                                if (!errMsg && (data.required_type !== undefined || data.resource_type !== undefined)) {
                                    errMsg = `Dit orderregel vereist '${data.required_type ?? 'Onbekend'}', maar de gekozen resource is van het type '${data.resource_type ?? 'Onbekend'}'. Kies een passende resource of een ander orderregel.`;
                                }
                                if (!errMsg && data.errors) {
                                    const firstField = Object.values(data.errors)[0];
                                    errMsg = Array.isArray(firstField) ? firstField[0] : firstField;
                                }
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: errMsg || `HTTP ${res.status}: ${res.statusText}`
                                });
                            }
                        } catch (error) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: `Fout bij ${this.isEditing ? 'bijwerken' : 'inboeken'}: ${error.message}`
                            });
                        } finally {
                            this.$refs.calendar.loading = false;
                        }
                    },
                    async deleteBooking() {
                        if (!this.editingBookingId) return;
                        if (!confirm('Weet je zeker dat je deze boeking wilt verwijderen?')) return;

                        if (this.$refs.calendar.loading) return;
                        this.$refs.calendar.loading = true;

                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                            if (!csrfToken) {
                                throw new Error('CSRF token niet gevonden');
                            }

                            const url = this.deleteBookingUrlTemplate.replace('___ID___', this.editingBookingId);
                            const res = await fetch(url, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (res.ok) {
                                this.$refs.bookModal.toggle();
                                this.$emitter.emit('add-flash', {type: 'success', message: 'Boeking verwijderd'});
                                this.resetFormAndReload();
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
                                message: `Fout bij verwijderen: ${error.message}`
                            });
                        } finally {
                            this.$refs.calendar.loading = false;
                        }
                    },
                    resetFormAndReload() {
                        this.form = {
                            order_item_id: null,
                            resource_id: null,
                            from: '',
                            to: '',
                        };
                        this.resetEditState();
                        setTimeout(() => {
                            this.$refs.calendar.loading = false;
                            this.loadAvailability().catch(error => {
                                console.error('Error reloading availability:', error);
                                this.$refs.calendar.loading = false;
                            });
                        }, 100);
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
