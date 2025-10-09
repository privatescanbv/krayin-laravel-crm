<x-admin::layouts>
    <x-slot:title>
        Planning - Orderregel #{{ $orderItem->id }}
    </x-slot>

    @include('admin::planning.components.planning-calendar')

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <div class="text-xl font-bold">Resource Planning</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Orderregel #{{ $orderItem->id }}</div>
            </div>
            <a href="{{ route('admin.orders.edit', ['id' => $orderItem->order_id]) }}" class="secondary-button">Terug naar order</a>
        </div>

        <div id="order-item-planning" class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4">
            <v-order-item-planning
                :order-item-id="{{ $orderItem->id }}"
                :default-resource-type-id="{{ (int) ($defaultResourceTypeId ?? 0) }}"
                :default-clinic-id="{{ (int) ($defaultClinicId ?? 0) }}"
            ></v-order-item-planning>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-order-item-planning-template">
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
                        <!-- Filters and View Controls -->
                        <div class="filters-bar rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/60 p-3 md:p-4 md:grid md:grid-cols-2 md:gap-3">
                            <!-- Left: Filters (side-by-side) -->
                            <div class="flex flex-wrap items-end gap-3 md:col-span-1 md:row-span-2">
                                <div class="flex flex-col w-56 md:w-64">
                                    <label class="block text-xs mb-1 text-gray-600">Resource type</label>
                                    <div class="w-40 md:w-48 px-3 py-1.5 border border-gray-300 rounded-md text-sm bg-gray-50 text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 cursor-not-allowed select-none">
                                        @{{ resources[0]?.resource_type || 'Onbekend' }}
                                    </div>
                                </div>
                                <div class="flex flex-col w-56 md:w-64">
                                    <label class="block text-xs mb-1 text-gray-600">Kliniek</label>
                                    <select
                                        v-model.number="filters.clinic_id"
                                        class="w-40 md:w-48 px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                    >
                                        <option
                                            v-for="c in Array.from(new Map(resources.map(r => [r.clinic_id, { id: r.clinic_id, name: r.clinic }]) ).values())"
                                            :key="c.id"
                                            :value="c.id"
                                        >@{{ c.name || ('Kliniek #' + c.id) }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Right: View toggle (top-right) -->
                            <div class="md:col-span-1 md:grid md:grid-rows-2 md:gap-3">
                                <!-- Right: View toggle (top-right) -->
                                <div class="flex items-center justify-end gap-3 mt-3 md:mt-0 pb-3">
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

                                <!-- Bottom-right: Calendar controls inside filter bar -->
                                <div class="flex items-center justify-end gap-2">
                                    <button class="secondary-button" @click="prevPeriod">Vorige</button>
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">@{{ periodLabel }}</div>
                                    <button class="secondary-button" @click="nextPeriod">Volgende</button>
                                    <button class="primary-button" @click="loadAvailability">Zoeken</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #modals>
                        <!-- Book modal -->
                        <x-admin::modal ref="bookModal">
                            <x-slot:header>
                                Inboeken
                            </x-slot:header>
                            <x-slot:content>
                                <div class="space-y-6" style="pointer-events: auto; z-index: 1000; position: relative;">
                                    <!-- Existing bookings summary -->
                                    <div v-if="existingForOrderItem && existingForOrderItem.length" class="rounded border border-amber-300 bg-amber-50 text-amber-900 p-3 text-sm">
                                        <div class="font-semibold mb-1">Bestaande afspraken voor deze orderregel</div>
                                        <ul class="list-disc ml-5">
                                            <li v-for="b in existingForOrderItem" :key="b.id">
                                                @{{ b.resource_name || 'Onbekende resource' }} — @{{ timeRange(b.from, b.to) }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Resource</label>
                                        <select
                                            v-model.number="form.resource_id"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                                            style="pointer-events: auto; z-index: 10; position: relative;"
                                            @click.stop
                                        >
                                            <option v-for="r in resources" :key="r.id" :value="r.id">@{{ r.name }} (@{{ r.clinic }})</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Van</label>
                                        <input
                                            type="datetime-local"
                                            v-model="form.from"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                                            style="pointer-events: auto; z-index: 10; position: relative;"
                                            @click.stop
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tot</label>
                                        <input
                                            type="datetime-local"
                                            v-model="form.to"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                                            style="pointer-events: auto; z-index: 10; position: relative;"
                                            @click.stop
                                        />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input id="replace_existing" type="checkbox" v-model="form.replace_existing" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                                        <label for="replace_existing" class="text-sm text-gray-700 dark:text-gray-300">Vervang bestaande afspraak (verwijdert eerdere boekingen voor deze orderregel)</label>
                                    </div>
                                </div>
                            </x-slot:content>
                            <x-slot:footer>
                                <div class="flex justify-end gap-3">
                                    <button class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700" @click="$refs.bookModal.toggle()">Annuleren</button>
                                    <button class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500" @click="submitBooking">Opslaan</button>
                                </div>
                            </x-slot:footer>
                        </x-admin::modal>
                    </template>
                </v-planning-calendar>
            </div>
        </script>

        <script type="module">
            app.component('v-order-item-planning', {
                template: '#v-order-item-planning-template',
                props: ['orderItemId', 'defaultResourceTypeId', 'defaultClinicId'],
                data() {
                    return {
                        viewType: 'week',
                        filters: {
                            resource_type_id: this.defaultResourceTypeId || null,
                            clinic_id: this.defaultClinicId || null,
                        },
                        form: { resource_id: null, from: '', to: '', replace_existing: true },
                        resources: [],
                        existingForOrderItem: [],
                        availabilityUrl: "{{ route('admin.planning.order_item.availability', ['orderItemId' => $orderItem->id]) }}",
                    };
                },
                computed: {
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
                        if (this.filters.resource_type_id) {
                            params.resource_type_id = this.filters.resource_type_id;
                        }
                        if (this.filters.clinic_id) {
                            params.clinic_id = this.filters.clinic_id;
                        }

                        await this.$refs.calendar.loadAvailability(params);
                    },
                    onCalendarLoaded(data) {
                        this.resources = data.resources || [];
                        this.existingForOrderItem = data.existing_bookings_for_order_item || [];

                        // Ensure clinic default selection
                        try {
                            const clinicIds = Array.from(new Set(this.resources
                                .map(r => r.clinic_id)
                                .filter(id => id !== null && id !== undefined)));

                            const hasDefaultClinic = this.defaultClinicId && clinicIds.includes(this.defaultClinicId);
                            const hasCurrentClinic = this.filters.clinic_id !== null && this.filters.clinic_id !== '' && clinicIds.includes(this.filters.clinic_id);

                            if (!hasCurrentClinic) {
                                if (hasDefaultClinic) {
                                    this.filters.clinic_id = this.defaultClinicId;
                                } else if (clinicIds.length === 1) {
                                    this.filters.clinic_id = clinicIds[0];
                                }
                            }
                        } catch (e) {
                            // no-op
                        }
                    },
                    openBook(block) {
                        this.form.resource_id = block.resource_id;
                        const pad = (n) => String(n).padStart(2, '0');
                        const toLocal = (dt) => `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                        this.form.from = toLocal(new Date(block.from));
                        this.form.to = toLocal(new Date(block.to));
                        this.$refs.bookModal.toggle();
                    },
                    async submitBooking() {
                        if (this.$refs.calendar.loading) return;

                        this.$refs.calendar.loading = true;
                        try {
                            const url = "{{ route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]) }}";
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
                                this.$emitter.emit('add-flash', { type: 'success', message: 'Ingeboekt' });
                                this.form = { resource_id: null, from: '', to: '', replace_existing: true };
                                setTimeout(() => {
                                    this.$refs.calendar.loading = false;
                                    this.loadAvailability().catch(error => {
                                        console.error('Error reloading availability:', error);
                                        this.$refs.calendar.loading = false;
                                    });
                                }, 100);
                            } else {
                                const data = await res.json().catch(() => ({}));
                                this.$emitter.emit('add-flash', { type: 'error', message: data.message || `HTTP ${res.status}: ${res.statusText}` });
                            }
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: `Fout bij inboeken: ${error.message}` });
                        } finally {
                            this.$refs.calendar.loading = false;
                        }
                    },
                    timeRange(from, to) {
                        const f = new Date(from);
                        const t = new Date(to);
                        return f.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '–' +
                               t.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
