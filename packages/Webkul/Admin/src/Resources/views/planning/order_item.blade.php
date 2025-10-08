<x-admin::layouts>
    <x-slot:title>
        Planning - Orderregel #{{ $orderItem->id }}
    </x-slot>

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
            .time-gutter .hour { height: 60px; font-size: 10px; color: #6b7280; padding-right: 6px; text-align: right; }
            .day-column { position: relative; border-left: 1px solid var(--tw-prose-td-borders, #e5e7eb); }
            .day-column .hour-slot { height: 60px; border-bottom: 1px dashed #e5e7eb; }
            .block { position: absolute; left: 6px; right: 6px; border-radius: 6px; padding: 2px 6px; font-size: 11px; overflow: hidden; }
            .block-available { background: rgba(16, 185, 129, 0.15); color: #065f46; border: 1px solid rgba(16,185,129,0.3); cursor: pointer; }
            .block-occupied { background: rgba(107, 114, 128, 0.25); color: #374151; border: 1px solid rgba(107,114,128,0.35); pointer-events: none; }
            .filters-bar { position: relative; z-index: 10; }
            .calendar-container { position: relative; z-index: 0; }
        </style>

        <script type="text/x-template" id="v-order-item-planning-template">
            <div class="flex flex-col gap-4">
                <!-- Filters -->
                <div class="flex flex-wrap items-end gap-3 filters-bar">
                    <div>
                        <label class="block text-xs mb-1">Resource type</label>
                        <input type="number" v-model.number="filters.resource_type_id" class="control" />
                    </div>
                    <div>
                        <label class="block text-xs mb-1">Kliniek</label>
                        <input type="number" v-model.number="filters.clinic_id" class="control" />
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <button class="secondary-button" @click="prevWeek">Vorige week</button>
                        <div class="text-sm">@{{ weekLabel }}</div>
                        <button class="secondary-button" @click="nextWeek">Volgende week</button>
                        <button class="primary-button" @click="loadAvailability">Zoeken</button>
                        <button class="secondary-button" @click="debugEnabled = !debugEnabled">@{{ debugEnabled ? 'Debug uit' : 'Debug aan' }}</button>
                    </div>
                </div>

                <!-- Resource summary -->
                <div v-if="resources.length" class="text-sm text-gray-700 dark:text-gray-300">
                    <span class="font-semibold">Resources:</span>
                    <span v-for="(r, idx) in resources" :key="r.id">
                        <template v-if="idx">, </template>
                        @{{ r.name }} (@{{ r.clinic }})
                    </span>
                </div>

                <!-- Week calendar grid -->
                <div class="calendar-container overflow-x-auto">
                    <!-- Header Row -->
                    <div class="calendar-header">
                        <div></div>
                        <div v-for="d in 7" :key="d" class="calendar-day-header">@{{ dayLabel(d-1) }}</div>
                    </div>
                    <!-- Body Rows as columns -->
                    <div class="calendar-body">
                        <!-- Time gutter -->
                        <div class="time-gutter">
                            <div v-for="h in hours" :key="h" class="hour">@{{ hourLabel(h) }}</div>
                        </div>
                        <!-- Day columns -->
                        <div v-for="d in 7" :key="'col-'+d" class="day-column">
                            <!-- Hour slots -->
                            <div v-for="h in hours" :key="'slot-'+d+'-'+h" class="hour-slot pointer-events-none"></div>
                            <!-- Occupied blocks (readonly) -->
                            <div v-for="occ in occupiedBlocksByDay(d-1)" :key="occ.key" class="block block-occupied" :style="blockStyle(occ)">
                                @{{ timeRange(occ.from, occ.to) }}
                            </div>
                            <!-- Available blocks (clickable) -->
                            <div v-for="blk in availableBlocksByDay(d-1)" :key="blk.key" class="block block-available" :style="blockStyle(blk)" @click.stop="openBook(blk.resourceId, blk.from, blk.to)">
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
                            <div class="font-semibold">Shifts keys (@{{ Object.keys(rawShifts||{}).length }})</div>
                            <pre class="whitespace-pre-wrap">@{{ safeStringify(Object.fromEntries(Object.entries(rawShifts||{}).slice(0,3))).slice(0, 1000) }}</pre>
                        </div>
                        <div>
                            <div class="font-semibold">Occupancy keys (@{{ Object.keys(rawOccupancy||{}).length }})</div>
                            <pre class="whitespace-pre-wrap">@{{ safeStringify(Object.fromEntries(Object.entries(rawOccupancy||{}).slice(0,3))).slice(0, 1000) }}</pre>
                        </div>
                    </div>
                    <div class="mt-2">Dag @{{ debugState.dayOffset }} — available: @{{ debugState.availableCount }}, occupied: @{{ debugState.occupiedCount }}</div>
                </div>

                <!-- Book modal -->
                <x-admin::modal ref="bookModal">
                    <x-slot:header>
                        Inboeken
                    </x-slot:header>
                    <x-slot:content>
                        <div class="flex flex-col gap-3">
                            <div>
                                <label class="block text-xs mb-1">Resource</label>
                                <select v-model.number="form.resource_id" class="control">
                                    <option v-for="r in resources" :key="r.id" :value="r.id">@{{ r.name }} (@{{ r.clinic }})</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs mb-1">Van</label>
                                <input type="datetime-local" v-model="form.from" class="control" />
                            </div>
                            <div>
                                <label class="block text-xs mb-1">Tot</label>
                                <input type="datetime-local" v-model="form.to" class="control" />
                            </div>
                        </div>
                    </x-slot:content>
                    <x-slot:footer>
                        <button class="secondary-button" @click="$refs.bookModal.toggle()">Annuleren</button>
                        <button class="primary-button" @click="submitBooking">Opslaan</button>
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
                        rawShifts: {},
                        rawOccupancy: {},
                        form: { resource_id: null, from: '', to: '' },
                        hours: Array.from({ length: 24 }, (_, i) => i),
                        debugEnabled: false,
                        debugState: { dayOffset: 0, availableCount: 0, occupiedCount: 0 },
                    };
                },
                computed: {
                    weekLabel() {
                        return this.window.start.toLocaleDateString() + ' - ' + this.window.end.toLocaleDateString();
                    },
                },
                mounted() {
                    this.loadAvailability();
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
                        const minutesPerHourHeight = 60; // 60px per hour
                        const from = new Date(block.from), to = new Date(block.to);
                        const top = this.minuteOfDay(from) * (minutesPerHourHeight/60);
                        const height = Math.max(18, (to - from) / (1000*60) * (minutesPerHourHeight/60));
                        return { top: top + 'px', height: height + 'px' };
                    },
                    timeRange(from, to) { const f = new Date(from); const t = new Date(to); return f.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + '–' + t.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); },
                    async loadAvailability() {
                        const url = `${"{{ route('admin.planning.order_item.availability', ['orderItemId' => $orderItem->id]) }}"}?start=${this.window.start.toISOString()}&end=${this.window.end.toISOString()}&resource_type_id=${this.filters.resource_type_id||''}&clinic_id=${this.filters.clinic_id||''}`;
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        this.resources = Array.isArray(data.resources) ? data.resources : [];
                        // Normalize shifts per resource to ensure weekday_time_blocks is an array
                        const normalizeBlocks = (val) => {
                            if (!val) return [];
                            if (Array.isArray(val)) return val;
                            if (typeof val === 'string') {
                                try { const parsed = JSON.parse(val); return Array.isArray(parsed) ? parsed : []; } catch (e) { return []; }
                            }
                            if (typeof val === 'object') {
                                // Allow single block object
                                return [val];
                            }
                            return [];
                        };
                        const shifts = data.shifts || {};
                        const normShifts = {};
                        Object.keys(shifts || {}).forEach((rid) => {
                            const arr = Array.isArray(shifts[rid]) ? shifts[rid] : [];
                            normShifts[rid] = arr.map(s => ({
                                ...s,
                                weekday_time_blocks: normalizeBlocks(s.weekday_time_blocks),
                                available: s.available !== false,
                            }));
                        });
                        this.rawShifts = normShifts;
                        // Normalize occupancy list per resource
                        const occ = data.occupancy || {};
                        const normOcc = {};
                        Object.keys(occ || {}).forEach((rid) => {
                            const arr = Array.isArray(occ[rid]) ? occ[rid] : [];
                            normOcc[rid] = arr.map(o => ({ ...o }));
                        });
                        this.rawOccupancy = normOcc;
                        if (this.debugEnabled) {
                            try { console.log('[planning] availability', { resources: this.resources, shifts: this.rawShifts, occupancy: this.rawOccupancy }); } catch (e) {}
                        }
                    },
                    blocksFromShift(resourceId, shift) {
                        // weekday_time_blocks: [{ weekday: 0..6 (0=Sun), from: "09:00", to: "17:00" }]
                        const blocks = [];
                        const start = new Date(this.window.start);
                        for (let i=0;i<7;i++) {
                            const day = new Date(start); day.setDate(day.getDate()+i);
                            const weekdaySun0 = day.getDay();
                            const source = shift && shift.weekday_time_blocks;
                            const blockList = Array.isArray(source) ? source : [];
                            const tbs = blockList.filter(b => Number(b.weekday) === weekdaySun0 && shift.available !== false);
                            for (const tb of tbs) {
                                const from = new Date(day); const [fh,fm] = (tb.from||'09:00').split(':'); from.setHours(+fh, +fm, 0, 0);
                                const to = new Date(day); const [th,tm] = (tb.to||'17:00').split(':'); to.setHours(+th, +tm, 0, 0);
                                blocks.push({ resourceId, from, to });
                            }
                        }
                        return blocks;
                    },
                    splitByOccupancy(blocks, occupancy) {
                        const result = [];
                        for (const block of blocks) {
                            let parts = [ { from: new Date(block.from), to: new Date(block.to) } ];
                            for (const occ of occupancy) {
                                const of = new Date(occ.from), ot = new Date(occ.to);
                                parts = parts.flatMap(p => {
                                    if (ot <= p.from || of >= p.to) return [p];
                                    const segs = [];
                                    if (of > p.from) segs.push({ from: p.from, to: new Date(of) });
                                    if (ot < p.to) segs.push({ from: new Date(ot), to: p.to });
                                    return segs;
                                });
                            }
                            for (const p of parts) { if (p.to > p.from) result.push({ resourceId: block.resourceId, from: p.from, to: p.to }); }
                        }
                        return result;
                    },
                    availableBlocksByDay(weekdayOffset) {
                        const day = this.dayDate(weekdayOffset);
                        const res = [];
                        for (const r of this.resources) {
                            const shifts = (this.rawShifts[r.id]||[]);
                            const occ = (this.rawOccupancy[r.id]||[]);
                            const blocks = shifts.flatMap(s => this.blocksFromShift(r.id, s));
                            const byDay = blocks.filter(b => b.from.toDateString() === day.toDateString());
                            const occDay = occ.filter(o => {
                                const of = new Date(o.from), ot = new Date(o.to);
                                return of.toDateString() === day.toDateString() || ot.toDateString() === day.toDateString();
                            });
                            const free = this.splitByOccupancy(byDay, occDay).map((p, idx) => ({ key: `${r.id}-a-${weekdayOffset}-${idx}`, resourceId: r.id, ...p }));
                            res.push(...free);
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
                                return of.toDateString() === day.toDateString() || ot.toDateString() === day.toDateString();
                            }).map((o, idx) => ({ key: `${r.id}-o-${weekdayOffset}-${idx}`, resourceId: r.id, ...o }));
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
                        this.$refs.bookModal.toggle();
                    },
                    async submitBooking() {
                        const url = "{{ route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]) }}";
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '' },
                            body: JSON.stringify({ resource_id: this.form.resource_id, from: this.form.from, to: this.form.to })
                        });
                        if (res.ok) {
                            this.$refs.bookModal.toggle();
                            await this.loadAvailability();
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Ingeboekt' });
                        } else {
                            const data = await res.json().catch(() => ({}));
                            this.$emitter.emit('add-flash', { type: 'error', message: data.message || 'Mislukt' });
                        }
                    },
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>

