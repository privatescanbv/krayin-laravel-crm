<x-admin::layouts>
    <x-slot:title>
        Resource Planning - Order #{{ $order->id }}
    </x-slot>

    @include('admin::planning.components.planning-calendar')
    @include('admin::planning.components.multiselect-filter')

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <div class="text-xl font-bold">Resource Planning - Order #{{ $order->id }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $order->title }}</div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.orders.edit', ['id' => $order->id]) }}" class="secondary-button">Terug naar order</a>
                <a href="{{ route('admin.planning.monitor.index') }}" class="secondary-button">Alle resources</a>
            </div>
        </div>

        <!-- Order Items Panel -->
        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4">
            <h3 class="text-lg font-semibold mb-4">Orderregels</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($order->orderRegels as $item)
                    @php
                        $statusValue = is_string($item->status) ? $item->status : ($item->status?->value ?? 'nieuw');
                        $statusLabel = is_object($item->status) && method_exists($item->status, 'label')
                            ? $item->status->label()
                            : ucfirst(str_replace('_', ' ', $statusValue));
                        $canPlan = $item->product && $item->product->partnerProducts()->exists();
                    @endphp
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 {{ $canPlan ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-medium text-sm">{{ $item->product?->name ?? 'Onbekend product' }}</h4>
                            <span class="text-xs px-2 py-1 rounded-full {{ $statusValue === 'ingepland' ? 'bg-green-100 text-green-800' : ($statusValue === 'moet_worden_ingepland' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                            Aantal: {{ $item->quantity }}
                        </div>
                        @if($item->resourceOrderItems && $item->resourceOrderItems->count() > 0)
                            <div class="text-xs text-gray-700 dark:text-gray-300">
                                <div class="font-medium mb-1">Ingepland:</div>
                                @foreach($item->resourceOrderItems as $booking)
                                    <div class="mb-1">
                                        <strong>{{ $booking->resource?->name ?? 'Onbekend' }}</strong><br>
                                        {{ \Carbon\Carbon::parse($booking->from)->format('d-m-Y H:i') }} - {{ \Carbon\Carbon::parse($booking->to)->format('H:i') }}
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Niet ingepland
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Resource Planning Calendar -->
        @php
            $orderItems = $order->orderRegels->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product?->name ?? 'Onbekend product',
                    'quantity' => $item->quantity,
                    'status' => (is_string($item->status) ? $item->status : ($item->status?->value ?? 'nieuw')),
                    'can_plan' => $item->product && method_exists($item->product, 'partnerProducts') && $item->product->partnerProducts()->exists(),
                    'bookings' => $item->resourceOrderItems->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'resource_id' => $booking->resource_id,
                            'resource_name' => $booking->resource?->name ?? 'Onbekend',
                            'from' => \Carbon\Carbon::parse($booking->from)->toIso8601String(),
                            'to' => \Carbon\Carbon::parse($booking->to)->toIso8601String(),
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray();
        @endphp
        <div id="order-resource-planning" class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4">
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
                                    <div class="w-full md:w-56">
                                        <v-multiselect-filter
                                            v-model="filters.order_item_ids"
                                            :options="orderItemOptions"
                                            label="Orderregel"
                                            placeholder="Alle orderregels"
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

                    <template #modals>
                        <!-- Book modal -->
                        <x-admin::modal ref="bookModal">
                            <x-slot:header>
                                Inboeken
                            </x-slot:header>
                            <x-slot:content>
                                <div class="space-y-6" style="pointer-events: auto; z-index: 1000; position: relative;">
                                    <!-- Order item selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Orderregel</label>
                                        <select
                                            v-model.number="form.order_item_id"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                                            style="pointer-events: auto; z-index: 10; position: relative;"
                                            @click.stop
                                        >
                                            <option value="">Selecteer orderregel</option>
                                            <option v-for="item in orderItems" :key="item.id" :value="item.id" :disabled="!item.can_plan">
                                                @{{ item.product_name }} (Aantal: @{{ item.quantity }}) @{{ !item.can_plan ? '- Niet planbaar' : '' }}
                                            </option>
                                        </select>
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
            app.component('v-order-resource-planning', {
                template: '#v-order-resource-planning-template',
                props: ['orderId', 'orderItems'],
                data() {
                    return {
                        viewType: 'week',
                        filters: {
                            resource_type_ids: [],
                            clinic_ids: [],
                            resource_ids: [],
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
                        resourceTypesUrl: "{{ route('admin.planning.monitor.order.resource_types', ['orderId' => $order->id]) }}",
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
                        return this.orderItems.map(item => ({
                            value: item.id,
                            label: item.product_name + ' (Aantal: ' + item.quantity + ')' + (!item.can_plan ? ' - Niet planbaar' : '')
                        }));
                    },
                    periodLabel() {
                        return this.$refs.calendar?.periodLabel || '';
                    }
                },
                mounted() {
                    this.loadOrderResourceTypes();
                },
                methods: {
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
                        if (this.filters.order_item_ids.length > 0) {
                            params.order_item_ids = this.filters.order_item_ids.join(',');
                        }
                        if (this.filters.show_available_only) {
                            params.show_available_only = '1';
                        }

                        await this.$refs.calendar.loadAvailability(params);
                    },
                    onCalendarLoaded(data) {
                        this.resources = data.resources || [];
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

                        if (!this.form.order_item_id) {
                            this.$emitter.emit('add-flash', { type: 'error', message: 'Selecteer een orderregel' });
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
                                this.$emitter.emit('add-flash', { type: 'success', message: 'Ingeboekt' });
                                this.form = { order_item_id: null, resource_id: null, from: '', to: '', replace_existing: true };
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
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>