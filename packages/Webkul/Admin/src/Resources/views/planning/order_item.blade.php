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
                <div class="text-xl font-bold">Planning</div>
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
            .block { position: absolute; left: 6px; right: 6px; border-radius: 6px; padding: 2px 6px; font-size: 11px; overflow: hidden; transition: all 0.2s ease; }
            .block-available { cursor: pointer; }
            .block-available:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .block-occupied { 
                background: repeating-linear-gradient(
                    45deg,
                    rgba(107, 114, 128, 0.3),
                    rgba(107, 114, 128, 0.3) 4px,
                    rgba(107, 114, 128, 0.1) 4px,
                    rgba(107, 114, 128, 0.1) 8px
                );
                color: #374151; 
                border: 1px solid rgba(107,114,128,0.6); 
                pointer-events: none; 
                position: relative;
                z-index: 2;
                min-height: 20px;
            }
            /* Fallback for occupied blocks if gradient doesn't work */
            .block-occupied:not([style*="background"]) {
                background-color: rgba(107, 114, 128, 0.4) !important;
            }
            .filters-bar { position: relative; z-index: 10; }
            .calendar-container { position: relative; z-index: 0; }
            .loading-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 20; }
            .loading-spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top: 3px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .error-message { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 8px 12px; border-radius: 6px; margin: 8px 0; }
        </style>

        <script type="text/x-template" id="v-order-item-planning-template">


            <div class="flex flex-col gap-4">
                <!-- Filters -->
                <div class="flex flex-wrap items-end justify-between gap-4 filters-bar">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="flex flex-col min-w-[150px]">
                            <label class="block text-xs mb-1 text-gray-600">Resource type</label>
                            <input type="number" v-model.number="filters.resource_type_id" class="control"/>
                        </div>
                        <div class="flex flex-col min-w-[150px]">
                            <label class="block text-xs mb-1 text-gray-600">Kliniek</label>
                            <input type="number" v-model.number="filters.clinic_id" class="control"/>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <button class="secondary-button" @click="prevWeek">Vorige week</button>
                        <div class="text-sm text-gray-700">@{{ weekLabel }}</div>
                        <button class="secondary-button" @click="nextWeek">Volgende week</button>
                        <button class="primary-button" @click="loadAvailability">Zoeken</button>
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
                        @{{ r.name }} (@{{ r.clinic }})
                    </span>
                </div>

                <!-- Error message -->
                <div v-if="errorMessage" class="error-message">
                    @{{ errorMessage }}
                </div>

                <!-- Week calendar grid -->
                <div class="calendar-container overflow-x-auto relative">
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
                            <div v-for="h in hours" :key="h" class="hour" :style="{ height: pixelsPerHour + 'px' }">@{{ hourLabel(h) }}</div>
                        </div>
                        <!-- Day columns -->
                        <div v-for="d in 7" :key="'col-'+d" class="day-column">
                            <!-- Hour slots -->
                            <div v-for="h in hours" :key="'slot-'+d+'-'+h" class="hour-slot pointer-events-none" :style="{ height: pixelsPerHour + 'px' }"></div>
                            <!-- Occupied blocks (readonly) -->
                            <div v-for="occ in occupiedBlocksByDay(d-1)" :key="occ.key" class="block block-occupied" :style="blockStyle(occ)">
                                <div class="font-semibold text-xs">@{{ occ.lead_name || 'Onbekend' }}</div>
                                <div class="text-xs opacity-75">@{{ timeRange(occ.from, occ.to) }}</div>
                            </div>
                            <!-- Available blocks (clickable) -->
                            <div v-for="blk in availableBlocksByDay(d-1)" :key="blk.key" class="block block-available" :style="blockStyle(blk)" :title="getResourceTooltip(blk)" @click.stop="openBook(blk.resourceId, blk.from, blk.to)">
                                @{{ timeRange(blk.from, blk.to) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Debug panel -->
                <div v-if="debugEnabled" class="mt-4 rounded border border-yellow-300 bg-yellow-50 p-3 text-xs text-yellow-900">
                    <div class="font-semibold mb-2">Debug</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <div class="font-semibold">Resources (@{{ resources.length }})</div>
                            <pre class="whitespace-pre-wrap">@{{ safeStringify(resources).slice(0, 1000) }}</pre>
                        </div>
                        <div>
                            <div class="font-semibold">Occupancy (@{{ Object.keys(rawOccupancy||{}).length }})</div>
                            <pre class="whitespace-pre-wrap">@{{ safeStringify(rawOccupancy).slice(0, 1000) }}</pre>
                        </div>
                        <div>
                            <div class="font-semibold">Availability (@{{ Object.keys(availabilityByResource||{}).length }})</div>
                            <pre class="whitespace-pre-wrap">@{{ safeStringify(availabilityByResource).slice(0, 1000) }}</pre>
                        </div>
                    </div>
                    <div class="mt-2">Dag @{{ debugState.dayOffset }} — available: @{{ debugState.availableCount }}, occupied: @{{ debugState.occupiedCount }}</div>
                    <div class="mt-2">
                        <strong>Occupied blocks per day:</strong>
                        <div v-for="d in 7" :key="'debug-'+d" class="text-xs">
                            Dag @{{ d-1 }}: @{{ occupiedBlocksByDay(d-1).length }} blokken
                            <div v-for="occ in occupiedBlocksByDay(d-1)" :key="'debug-occ-'+occ.key" class="ml-2">
                                - @{{ occ.lead_name }} (@{{ timeRange(occ.from, occ.to) }})
                            </div>
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
                    const end = new Date(start); end.setDate(start.getDate() + 6);
                    return {
                        filters: {
                            resource_type_id: this.defaultResourceTypeId || null,
                            clinic_id: this.defaultClinicId || null,
                        },
                        window: { start, end },
                        resources: [],
                        availabilityByResource: {},
                        rawOccupancy: {},
                        form: { resource_id: null, from: '', to: '' },
                        hours: Array.from({ length: 24 }, (_, i) => i),
                        debugEnabled: true,
                        debugState: { dayOffset: 0, availableCount: 0, occupiedCount: 0 },
                        loading: false,
                        errorMessage: '',
                        pixelsPerHour: 60, // Will be calculated dynamically
                        resourceColors: [
                            '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
                            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
                        ],
                    };
                },
                computed: {
                    weekLabel() {
                        return this.window.start.toLocaleDateString() + ' - ' + this.window.end.toLocaleDateString();
                    },
                },
                mounted() {
                    this.calculateDynamicScaling();
                    this.loadAvailability();
                    this.scrollToCurrentTime();
                },
                methods: {
                    startOfWeek(date) {
                        const d = new Date(date); const day = d.getDay(); const diff = (day === 0 ? -6 : 1) - day; d.setDate(d.getDate() + diff); d.setHours(0,0,0,0); return d;
                    },
                    nextWeek() { const s = new Date(this.window.start); s.setDate(s.getDate()+7); this.window.start = this.startOfWeek(s); const e = new Date(this.window.start); e.setDate(e.getDate()+6); this.window.end = e; this.loadAvailability(); },
                    prevWeek() { const s = new Date(this.window.start); s.setDate(s.getDate()-7); this.window.start = this.startOfWeek(s); const e = new Date(this.window.start); e.setDate(e.getDate()+6); this.window.end = e; this.loadAvailability(); },
                    dayDate(offset) { const d = new Date(this.window.start); d.setDate(d.getDate()+offset); return d; },
                    dayLabel(offset) { const d = this.dayDate(offset); return d.toLocaleDateString(undefined, { weekday: 'short', day:'2-digit', month:'2-digit' }); },
                    minuteOfDay(date) { return date.getHours()*60 + date.getMinutes(); },
                    hourLabel(hour) { return `${hour}:00`; },
                    blockStyle(block) {
                        // position within day-column: top by minutes since 00:00, height by duration
                        const from = new Date(block.from), to = new Date(block.to);
                        const top = this.minuteOfDay(from) * (this.pixelsPerHour/60);
                        const height = Math.max(18, (to - from) / (1000*60) * (this.pixelsPerHour/60));

                        // Check if this is an occupied block
                        if (block.lead_name) {
                            return {
                                top: top + 'px',
                                height: height + 'px',
                                backgroundColor: 'rgba(107, 114, 128, 0.4)',
                                borderColor: 'rgba(107, 114, 128, 0.6)',
                                color: '#374151'
                            };
                        }

                        // Get resource color for available blocks
                        const resource = this.resources.find(r => r.id === block.resourceId);
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
                    timeRange(from, to) { const f = new Date(from); const t = new Date(to); return f.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + '–' + t.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); },
                    async loadAvailability() {
                        this.loading = true;
                        this.errorMessage = '';

                        try {
                            const url = `${"{{ route('admin.planning.order_item.availability', ['orderItemId' => $orderItem->id]) }}"}?start=${this.window.start.toISOString()}&end=${this.window.end.toISOString()}&resource_type_id=${this.filters.resource_type_id||''}&clinic_id=${this.filters.clinic_id||''}`;
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });

                            if (!res.ok) {
                                const errorData = await res.json().catch(() => ({}));
                                throw new Error(errorData.message || `HTTP ${res.status}: ${res.statusText}`);
                            }

                            const data = await res.json();
                            this.resources = Array.isArray(data.resources) ? data.resources : [];

                            // Server returns final availability already split: { [resourceId]: [{from,to}] }
                            const availability = data.availability || {};
                            const normAvail = {};
                            Object.keys(availability).forEach((rid) => {
                                const arr = Array.isArray(availability[rid]) ? availability[rid] : [];
                                normAvail[rid] = arr.map(a => ({ from: a.from, to: a.to }));
                            });
                            this.availabilityByResource = normAvail;

                            // Normalize occupancy list per resource
                            const occ = data.occupancy || {};
                            const normOcc = {};
                            Object.keys(occ || {}).forEach((rid) => {
                                const arr = Array.isArray(occ[rid]) ? occ[rid] : [];
                                normOcc[rid] = arr.map(o => ({ ...o }));
                            });
                            this.rawOccupancy = normOcc;

                            if (this.debugEnabled) {
                                try { console.log('[planning] availability', { resources: this.resources, availability: this.availabilityByResource, occupancy: this.rawOccupancy }); } catch (e) {}
                            }
                        } catch (error) {
                            this.errorMessage = `Fout bij laden van beschikbaarheid: ${error.message}`;
                            console.error('[planning] loadAvailability error:', error);
                        } finally {
                            this.loading = false;
                        }
                    },
                    availableBlocksByDay(weekdayOffset) {
                        const day = this.dayDate(weekdayOffset);
                        const res = [];
                        for (const r of this.resources) {
                            const avail = (this.availabilityByResource[r.id]||[]);
                            const byDay = avail.filter(a => {
                                const f = new Date(a.from), t = new Date(a.to);
                                return f.toDateString() === day.toDateString() || t.toDateString() === day.toDateString();
                            }).map((a, idx) => ({ key: `${r.id}-a-${weekdayOffset}-${idx}`, resourceId: r.id, from: a.from, to: a.to }));
                            res.push(...byDay);
                        }
                        if (this.debugEnabled) { this.debugState.dayOffset = weekdayOffset; this.debugState.availableCount = res.length; }
                        return res;
                    },
                    occupiedBlocksByDay(weekdayOffset) {
                        const day = this.dayDate(weekdayOffset);
                        const res = [];
                        for (const r of this.resources) {
                            const occ = (this.rawOccupancy[r.id]||[]);
                            const occDay = occ.filter(o => {
                                const of = new Date(o.from), ot = new Date(o.to);
                                const dayStr = day.toDateString();
                                const fromStr = of.toDateString();
                                const toStr = ot.toDateString();
                                
                                // Check if the occupied period overlaps with this day
                                return fromStr === dayStr || toStr === dayStr || 
                                       (of <= day && ot >= new Date(day.getTime() + 24*60*60*1000));
                            }).map((o, idx) => ({ 
                                key: `${r.id}-o-${weekdayOffset}-${idx}`, 
                                resourceId: r.id, 
                                from: o.from,
                                to: o.to,
                                lead_name: o.lead_name || 'Onbekend'
                            }));
                            res.push(...occDay);
                        }
                        if (this.debugEnabled) { this.debugState.occupiedCount = res.length; }
                        return res;
                    },
                    safeStringify(obj) { try { return JSON.stringify(obj, null, 2); } catch (e) { return String(obj); } },
                    openBook(resourceId, from, to) {
                        this.form.resource_id = resourceId;
                        const pad = (n) => String(n).padStart(2,'0');
                        const toLocal = (dt) => `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                        this.form.from = toLocal(new Date(from));
                        this.form.to = toLocal(new Date(to));
                        
                        // Debug logging
                        console.log('openBook debug:', {
                            resourceId: resourceId,
                            from: from,
                            to: to,
                            formFrom: this.form.from,
                            formTo: this.form.to,
                            formResourceId: this.form.resource_id
                        });
                        
                        this.$refs.bookModal.toggle();
                    },
                    async submitBooking() {
                        this.loading = true;
                        try {
                            const url = "{{ route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]) }}";
                            
                            // Get CSRF token from meta tag or cookie
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                             this.getCookie('XSRF-TOKEN') || 
                                             document.querySelector('input[name="_token"]')?.value;
                            
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
                                body: JSON.stringify({ resource_id: this.form.resource_id, from: this.form.from, to: this.form.to })
                            });
                            
                            if (res.ok) {
                                this.$refs.bookModal.toggle();
                                await this.loadAvailability();
                                this.$emitter.emit('add-flash', { type: 'success', message: 'Ingeboekt' });
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
                    // Helper methods for colors and tooltips
                    hexToRgb(hex) {
                        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                        return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '0, 0, 0';
                    },
                    getContrastColor(hex) {
                        const rgb = this.hexToRgb(hex).split(', ').map(x => parseInt(x));
                        const brightness = (rgb[0] * 299 + rgb[1] * 587 + rgb[2] * 114) / 1000;
                        return brightness > 128 ? '#000000' : '#ffffff';
                    },
                    getResourceTooltip(block) {
                        const resource = this.resources.find(r => r.id === block.resourceId);
                        const from = new Date(block.from);
                        const to = new Date(block.to);
                        return `${resource?.name || 'Onbekend'} - ${from.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})} tot ${to.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}`;
                    },
                    getResourceColorStyle(resource) {
                        const colorIndex = this.resources.indexOf(resource) % this.resourceColors.length;
                        const color = this.resourceColors[colorIndex];
                        return {
                            backgroundColor: `rgba(${this.hexToRgb(color)}, 0.15)`,
                            borderColor: `rgba(${this.hexToRgb(color)}, 0.3)`,
                        };
                    },
                    getCookie(name) {
                        const value = `; ${document.cookie}`;
                        const parts = value.split(`; ${name}=`);
                        if (parts.length === 2) return parts.pop().split(';').shift();
                        return null;
                    },
                    // Dynamic scaling based on container height
                    calculateDynamicScaling() {
                        this.$nextTick(() => {
                            const container = this.$el.querySelector('.calendar-container');
                            if (container) {
                                const containerHeight = container.clientHeight;
                                const visibleHoursCount = 24; // Full day view
                                this.pixelsPerHour = Math.max(30, Math.floor(containerHeight / visibleHoursCount));
                            }
                        });
                    },
                    // Scroll to current time
                    scrollToCurrentTime() {
                        this.$nextTick(() => {
                            const now = new Date();
                            const y = this.minuteOfDay(now) * (this.pixelsPerHour/60);
                            const container = this.$el.querySelector('.calendar-container');
                            if (container) {
                                container.scrollTop = Math.max(0, y - 200);
                            }
                        });
                    },
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>

