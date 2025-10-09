<x-admin::layouts>
    <x-slot:title>
        Planning - Orderregel #{{ $orderItem->id }}
    </x-slot>

    @push('meta')
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endpush

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
        <style>
            .calendar-container { display: grid; grid-template-columns: 60px repeat(7, 1fr); }
            .calendar-header { display: contents; }
            .calendar-day-header { padding: 6px 8px; border-bottom: 1px solid var(--tw-prose-td-borders, #e5e7eb); font-weight: 600; font-size: 12px; }
            .calendar-body { display: contents; }
            .time-gutter { position: relative; }
            .time-gutter .hour { font-size: 10px; color: #6b7280; padding-right: 6px; text-align: right; }
            .day-column { position: relative; border-left: 1px solid var(--tw-prose-td-borders, #e5e7eb); }
            .day-column .hour-slot { border-bottom: 1px dashed #e5e7eb; }
            .calendar-block { position: absolute; left: 6px; right: 6px; border-radius: 6px; padding: 2px 6px; font-size: 11px; overflow: hidden; transition: all 0.2s ease; }
            .calendar-block-available { cursor: pointer; }
            .calendar-block-available:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .calendar-block-occupied { pointer-events: none; min-height: 20px; }
            .filters-bar { position: relative; z-index: 10; }
            .loading-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 20; }
            .loading-spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top: 3px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .error-message { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 8px 12px; border-radius: 6px; margin: 8px 0; }
            /* Responsive viewport container for calendar - compact for 08:00-17:00 only */
            .calendar-viewport { height: auto; max-height: 600px; min-height: 360px; }
            @media (max-width: 1024px) { .calendar-viewport { max-height: 550px; min-height: 500px; } }
            @media (max-width: 768px) { .calendar-viewport { max-height: 500px; min-height: 450px; } }

            /* Month view styles */
            .month-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; }
            .month-day { min-height: 120px; border: 1px solid #e5e7eb; padding: 4px; position: relative; }
            .month-day-header { font-weight: 600; font-size: 12px; margin-bottom: 4px; }
            .month-block { margin: 1px 0; padding: 2px 4px; border-radius: 3px; font-size: 10px; cursor: pointer; }
            .month-block:hover { opacity: 0.8; }
            .month-block-available { background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); }
            .month-block-occupied { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); cursor: not-allowed; }
        </style>

        <script type="text/x-template" id="v-order-item-planning-template">
            <div class="flex flex-col gap-4">
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

                <!-- Resource summary -->
                <div v-if="resources.length" class="text-sm text-gray-700 dark:text-gray-300">
                    <span class="font-semibold">Resources:</span>
                    <span v-for="(r, idx) in resources" :key="r.id" class="inline-flex items-center gap-1 ml-2">
                        <template v-if="idx">, </template>
                        <span
                            class="inline-block w-3 h-3 rounded-sm border"
                            :style="getResourceColorStyle(r)"
                        ></span>
                        <a
                            :href="`${'{{ route('admin.settings.resources.show', ['id' => 'REPLACE']) }}'.replace('REPLACE', r.id)}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-blue-600 hover:text-blue-800"
                        >@{{ r.name }}</a>
                        <span v-if="r.clinic">(@{{ r.clinic }})</span>
                    </span>
                </div>

                <!-- Error message -->
                <div v-if="errorMessage" class="error-message">
                    @{{ errorMessage }}
                </div>

                <!-- Week Calendar View -->
                <div v-if="viewType === 'week'" class="calendar-viewport overflow-x-auto relative">
                    <div class="calendar-container" style="height: 100%;">
                    <!-- Loading overlay -->
                    <div v-if="loading" class="loading-overlay">
                        <div class="loading-spinner"></div>
                    </div>

                    <!-- Header Row -->
                    <div class="calendar-header">
                        <div></div>
                        <div v-for="d in 7" :key="d" class="calendar-day-header">@{{ dayLabel(d-1) }}</div>
                    </div>

                    <!-- Body Rows as columns -->
                    <div class="calendar-body">
                        <!-- Time gutter -->
                        <div class="time-gutter">
                            <div v-for="h in hours" :key="h" class="hour" :style="{ height: slotHeightPx(h) + 'px' }">@{{ hourLabel(h) }}</div>
                        </div>

                        <!-- Day columns -->
                        <div v-for="d in 7" :key="'col-'+d" class="day-column">
                            <!-- Hour slots -->
                            <div v-for="h in hours" :key="'slot-'+d+'-'+h" class="hour-slot pointer-events-none" :style="{ height: slotHeightPx(h) + 'px' }"></div>

                            <!-- Rendered blocks from server -->
                            <div v-for="block in getBlocksForDay(d-1)" :key="block.key"
                                 :class="['calendar-block', block.clickable ? 'calendar-block-available' : 'calendar-block-occupied']"
                                 :style="blockStyle(block)"
                                 :title="getBlockTooltip(block)"
                                 @click="block.clickable ? openBook(block.resource_id, block.from, block.to) : null">
                                <div v-if="block.type === 'occupied'" class="font-semibold text-xs">@{{ block.lead_name || 'Onbekend' }}</div>
                                <div class="text-xs" :class="block.type === 'occupied' ? 'opacity-75' : ''">@{{ timeRange(block.from, block.to) }}</div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Month Calendar View -->
                <div v-if="viewType === 'month'" class="month-calendar overflow-x-auto relative">
                    <!-- Loading overlay -->
                    <div v-if="loading" class="loading-overlay">
                        <div class="loading-spinner"></div>
                    </div>

                    <!-- Month header -->
                    <div class="month-day-header">Ma</div>
                    <div class="month-day-header">Di</div>
                    <div class="month-day-header">Wo</div>
                    <div class="month-day-header">Do</div>
                    <div class="month-day-header">Vr</div>
                    <div class="month-day-header">Za</div>
                    <div class="month-day-header">Zo</div>

                    <!-- Month days -->
                    <div v-for="day in monthDays" :key="day.date" class="month-day">
                        <div class="month-day-header">@{{ day.dayNumber }}</div>

                        <!-- Blocks for this day -->
                        <div v-for="block in getBlocksForMonthDay(day.date)" :key="block.key"
                             :class="['month-block', block.clickable ? 'calendar-block-available' : 'calendar-block-occupied']"
                             :title="getBlockTooltip(block)"
                             @click="block.clickable ? openBook(block.resource_id, block.from, block.to) : null">
                            <div v-if="block.type === 'occupied'" class="font-semibold">@{{ block.lead_name || 'Onbekend' }}</div>
                            <div class="text-xs">@{{ timeRange(block.from, block.to) }}</div>
                        </div>
                    </div>

                </div>

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
            </div>
        </script>

        <script type="module">
            app.component('v-order-item-planning', {
                template: '#v-order-item-planning-template',
                props: ['orderItemId', 'defaultResourceTypeId', 'defaultClinicId'],
                data() {
                    const start = this.startOfWeek(new Date());
                    const end = new Date(start);
                    end.setDate(start.getDate() + 6);

                    return {
                        viewType: 'week', // 'week' or 'month'
                        filters: {
                            resource_type_id: this.defaultResourceTypeId || null,
                            clinic_id: this.defaultClinicId || null,
                        },
                        window: { start, end },
                        resources: [],
                        blocks: {}, // Server-rendered blocks: { [resourceId]: { [date]: [blocks] } }
                        form: { resource_id: null, from: '', to: '', replace_existing: true },
                        hours: Array.from({ length: 10 }, (_, i) => i + 8), // 08:00 - 17:00
                        loading: false,
                        errorMessage: '',
                        pixelsPerHour: 50, // pixels per hour (compacter voor 08:00-17:00)
                        resourceColors: [
                            '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
                            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
                        ],
                    };
                },
                computed: {
                    periodLabel() {
                        if (this.viewType === 'week') {
                            return this.window.start.toLocaleDateString() + ' - ' + this.window.end.toLocaleDateString();
                        } else {
                            return this.window.start.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' });
                        }
                    },
                    monthDays() {
                        if (this.viewType !== 'month') return [];

                        const days = [];
                        const start = new Date(this.window.start);
                        const end = new Date(this.window.end);

                        // Add empty days for the first week if month doesn't start on Monday
                        const firstDay = new Date(start);
                        const firstMonday = new Date(firstDay);
                        firstMonday.setDate(firstDay.getDate() - (firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1));

                        const current = new Date(firstMonday);
                        while (current <= end) {
                            days.push({
                                date: current.toISOString().split('T')[0],
                                dayNumber: current.getDate(),
                                isCurrentMonth: current.getMonth() === start.getMonth()
                            });
                            current.setDate(current.getDate() + 1);
                        }

                        return days;
                    }
                },
                mounted() {
                    this.calculateDynamicScaling();
                    this.loadAvailability();
                    this.scrollToCurrentTime();
                },
                methods: {
                    // Height per hour (08:00-17:00 only)
                    slotHeightPx(hour) {
                        return this.pixelsPerHour;
                    },
                    // Compute top offset in pixels (08:00-17:00 only)
                    topOffsetPx(date) {
                        const h = date.getHours();
                        const m = date.getMinutes();
                        // Calculate offset from 08:00
                        const hoursSince8 = Math.max(0, h - 8);
                        let sum = hoursSince8 * this.pixelsPerHour;
                        // Add minutes portion within the current hour
                        sum += (m / 60) * this.pixelsPerHour;
                        return sum;
                    },
                    setViewType(type) {
                        this.viewType = type;
                        if (type === 'month') {
                            this.window.start = this.startOfMonth(new Date());
                            this.window.end = this.endOfMonth(new Date());
                        } else {
                            this.window.start = this.startOfWeek(new Date());
                            const end = new Date(this.window.start);
                            end.setDate(this.window.start.getDate() + 6);
                            this.window.end = end;
                        }
                        this.loadAvailability();
                    },
                    startOfWeek(date) {
                        const d = new Date(date);
                        const day = d.getDay();
                        const diff = (day === 0 ? -6 : 1) - day;
                        d.setDate(d.getDate() + diff);
                        d.setHours(0, 0, 0, 0);
                        return d;
                    },
                    startOfMonth(date) {
                        const d = new Date(date);
                        d.setDate(1);
                        d.setHours(0, 0, 0, 0);
                        return d;
                    },
                    endOfMonth(date) {
                        const d = new Date(date);
                        d.setMonth(d.getMonth() + 1, 0);
                        d.setHours(23, 59, 59, 999);
                        return d;
                    },
                    nextPeriod() {
                        if (this.viewType === 'week') {
                            const s = new Date(this.window.start);
                            s.setDate(s.getDate() + 7);
                            this.window.start = this.startOfWeek(s);
                            const e = new Date(this.window.start);
                            e.setDate(e.getDate() + 6);
                            this.window.end = e;
                        } else {
                            const s = new Date(this.window.start);
                            s.setMonth(s.getMonth() + 1);
                            this.window.start = this.startOfMonth(s);
                            this.window.end = this.endOfMonth(s);
                        }
                        this.loadAvailability();
                    },
                    prevPeriod() {
                        if (this.viewType === 'week') {
                            const s = new Date(this.window.start);
                            s.setDate(s.getDate() - 7);
                            this.window.start = this.startOfWeek(s);
                            const e = new Date(this.window.start);
                            e.setDate(e.getDate() + 6);
                            this.window.end = e;
                        } else {
                            const s = new Date(this.window.start);
                            s.setMonth(s.getMonth() - 1);
                            this.window.start = this.startOfMonth(s);
                            this.window.end = this.endOfMonth(s);
                        }
                        this.loadAvailability();
                    },
                    dayDate(offset) {
                        const d = new Date(this.window.start);
                        d.setDate(d.getDate() + offset);
                        return d;
                    },
                    dayLabel(offset) {
                        const d = this.dayDate(offset);
                        return d.toLocaleDateString(undefined, { weekday: 'short', day: '2-digit', month: '2-digit' });
                    },
                    hourLabel(hour) {
                        return `${hour}:00`;
                    },
                    timeRange(from, to) {
                        const f = new Date(from);
                        const t = new Date(to);
                        return f.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '–' +
                               t.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    },
                    async loadAvailability() {
                        if (this.loading) return;

                        this.loading = true;
                        this.errorMessage = '';

                        try {
                            const url = `${"{{ route('admin.planning.order_item.availability', ['orderItemId' => $orderItem->id]) }}"}?view=${this.viewType}&start=${this.window.start.toISOString()}&end=${this.window.end.toISOString()}&resource_type_id=${this.filters.resource_type_id || ''}&clinic_id=${this.filters.clinic_id || ''}`;

                            const controller = new AbortController();
                            const timeoutId = setTimeout(() => controller.abort(), 10000);

                            const res = await fetch(url, {
                                headers: { 'Accept': 'application/json' },
                                signal: controller.signal
                            });

                            clearTimeout(timeoutId);

                            if (!res.ok) {
                                const errorData = await res.json().catch(() => ({}));
                                throw new Error(errorData.message || `HTTP ${res.status}: ${res.statusText}`);
                            }

                            const data = await res.json();
                            this.resources = Array.isArray(data.resources) ? data.resources : [];

                            // Server now returns pre-rendered blocks
                            this.blocks = data.blocks || {};
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

                        } catch (error) {
                            if (error.name === 'AbortError') {
                                this.errorMessage = 'Timeout bij laden van beschikbaarheid. Probeer opnieuw.';
                            } else {
                                this.errorMessage = `Fout bij laden van beschikbaarheid: ${error.message}`;
                            }
                            console.error('[planning] loadAvailability error:', error);
                        } finally {
                            this.loading = false;
                        }
                    },
                    getBlocksForDay(weekdayOffset) {
                        const day = this.dayDate(weekdayOffset);
                        const dayKey = day.toISOString().split('T')[0];
                        const blocks = [];

                        for (const resource of this.resources) {
                            const resourceBlocks = this.blocks[resource.id] || {};
                            const dayBlocks = resourceBlocks[dayKey] || [];

                            dayBlocks.forEach((block, idx) => {
                                blocks.push({
                                    ...block,
                                    key: `${resource.id}-${dayKey}-${idx}`
                                });
                            });
                        }

                        return blocks.sort((a, b) => new Date(a.from) - new Date(b.from));
                    },
                    getBlocksForMonthDay(date) {
                        const blocks = [];

                        for (const resource of this.resources) {
                            const resourceBlocks = this.blocks[resource.id] || {};
                            const dayBlocks = resourceBlocks[date] || [];

                            dayBlocks.forEach((block, idx) => {
                                blocks.push({
                                    ...block,
                                    key: `${resource.id}-${date}-${idx}`
                                });
                            });
                        }

                        return blocks.sort((a, b) => new Date(a.from) - new Date(b.from));
                    },
                    blockStyle(block) {
                        if (this.viewType === 'month') {
                            return {}; // Month blocks use CSS classes
                        }

                        // Week view positioning
                        const from = new Date(block.from);
                        const to = new Date(block.to);
                        const top = this.topOffsetPx(from);
                        // Height is sum of variable hour heights across interval
                        const height = Math.max(18, this.topOffsetPx(to) - this.topOffsetPx(from));

                        if (block.type === 'occupied') {
                            return {
                                top: top + 'px',
                                height: height + 'px',
                                backgroundColor: 'rgba(239, 68, 68, 0.6)',
                                borderColor: 'rgba(239, 68, 68, 0.8)',
                                color: '#ffffff',
                                border: '2px solid rgba(239, 68, 68, 0.8)',
                                zIndex: '10'
                            };
                        }

                        // Available block styling
                        const resource = this.resources.find(r => r.id === block.resource_id);
                        const colorIndex = this.resources.indexOf(resource) % this.resourceColors.length;
                        const color = this.resourceColors[colorIndex];

                        return {
                            top: top + 'px',
                            height: height + 'px',
                            backgroundColor: `rgba(${this.hexToRgb(color)}, 0.15)`,
                            borderColor: `rgba(${this.hexToRgb(color)}, 0.3)`,
                            color: this.getContrastColor(color)
                        };
                    },
                    minuteOfDay(date) {
                        return date.getHours() * 60 + date.getMinutes();
                    },
                    getBlockTooltip(block) {
                        const resource = this.resources.find(r => r.id === block.resource_id);
                        const from = new Date(block.from);
                        const to = new Date(block.to);
                        return `${resource?.name || 'Onbekend'} - ${from.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} tot ${to.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
                    },
                    openBook(resourceId, from, to) {
                        this.form.resource_id = resourceId;
                        const pad = (n) => String(n).padStart(2, '0');
                        const toLocal = (dt) => `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                        this.form.from = toLocal(new Date(from));
                        this.form.to = toLocal(new Date(to));
                        this.$refs.bookModal.toggle();
                    },
                    async submitBooking() {
                        if (this.loading) return;

                        this.loading = true;
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
                                    this.loading = false;
                                    this.loadAvailability().catch(error => {
                                        console.error('Error reloading availability:', error);
                                        this.loading = false;
                                    });
                                }, 100);
                            } else {
                                const data = await res.json().catch(() => ({}));
                                this.$emitter.emit('add-flash', { type: 'error', message: data.message || `HTTP ${res.status}: ${res.statusText}` });
                            }
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: `Fout bij inboeken: ${error.message}` });
                        } finally {
                            this.loading = false;
                        }
                    },
                    // Helper methods
                    hexToRgb(hex) {
                        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                        return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '0, 0, 0';
                    },
                    getContrastColor(hex) {
                        const rgb = this.hexToRgb(hex).split(', ').map(x => parseInt(x));
                        const brightness = (rgb[0] * 299 + rgb[1] * 587 + rgb[2] * 114) / 1000;
                        return brightness > 128 ? '#000000' : '#ffffff';
                    },
                    getResourceColorStyle(resource) {
                        const colorIndex = this.resources.indexOf(resource) % this.resourceColors.length;
                        const color = this.resourceColors[colorIndex];
                        return {
                            backgroundColor: `rgba(${this.hexToRgb(color)}, 0.15)`,
                            borderColor: `rgba(${this.hexToRgb(color)}, 0.3)`,
                        };
                    },
                    calculateDynamicScaling() {
                        this.$nextTick(() => {
                            const container = this.$el.querySelector('.calendar-viewport');
                            if (container) {
                                const containerHeight = container.clientHeight;
                                // Allocate a bit more space to business hours (9 hours) vs non-business (15 hours at half height)
                                // Effective total height units = 9 * 1 + 15 * 0.5 = 16.5 units
                                const effectiveUnits = 16.5;
                                const unit = Math.max(22, Math.floor(containerHeight / effectiveUnits));
                                this.pixelsPerHour = unit;            // for 08:00-17:00
                                this.halfPixelsPerHour = Math.max(14, Math.floor(unit / 2)); // others
                            }
                        });
                        // Recalculate on resize for responsiveness
                        window.addEventListener('resize', this.calculateDynamicScaling, { passive: true });
                    },
                    scrollToCurrentTime() {
                        this.$nextTick(() => {
                            const now = new Date();
                            const y = this.topOffsetPx(now);
                            const container = this.$el.querySelector('.calendar-viewport');
                            if (container) {
                                container.scrollTop = Math.max(0, y - 200);
                            }
                        });
                    }
                }
            });
        </script>
    @endpushOnce
</x-admin::layouts>
