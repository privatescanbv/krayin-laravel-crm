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
        <script type="text/x-template" id="v-order-item-planning-template">
            <div class="flex flex-col gap-4">
                <!-- Filters -->
                <div class="flex flex-wrap items-end gap-3">
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

                <!-- Week grid -->
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr>
                                <th class="p-2 text-left">Resource</th>
                                <th v-for="d in 7" :key="d" class="p-2 text-left">@{{ dayLabel(d-1) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in resources" :key="r.id" class="align-top">
                                <td class="p-2 whitespace-nowrap text-sm">@{{ r.name }}<div class="text-gray-500">@{{ r.clinic }}</div></td>
                                <td v-for="d in 7" :key="r.id + '-' + d" class="p-1">
                                    <div class="flex flex-col gap-1">
                                        <!-- Available blocks -->
                                        <div v-for="block in availableBlocks(r.id, d-1)" :key="block.key" class="bg-green-100 dark:bg-green-900 text-green-900 dark:text-green-100 rounded px-2 py-1 cursor-pointer"
                                             @click="openBook(r.id, block.from, block.to)">
                                            @{{ timeRange(block.from, block.to) }}
                                        </div>
                                        <!-- Occupied blocks -->
                                        <div v-for="occ in occupiedBlocks(r.id, d-1)" :key="occ.key" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded px-2 py-1 opacity-70">
                                            @{{ timeRange(occ.from, occ.to) }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                    dayLabel(offset) {
                        const d = new Date(this.window.start); d.setDate(d.getDate()+offset); return d.toLocaleDateString(undefined, { weekday: 'short', day:'2-digit', month:'2-digit' });
                    },
                    timeRange(from, to) {
                        const f = new Date(from); const t = new Date(to); return f.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + '–' + t.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                    },
                    async loadAvailability() {
                        const url = `${"{{ route('admin.planning.order_item.availability', ['orderItemId' => $orderItem->id]) }}"}?start=${this.window.start.toISOString()}&end=${this.window.end.toISOString()}&resource_type_id=${this.filters.resource_type_id||''}&clinic_id=${this.filters.clinic_id||''}`;
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        this.resources = data.resources || [];
                        this.rawShifts = data.shifts || {};
                        this.rawOccupancy = data.occupancy || {};
                    },
                    blocksFromShift(shift) {
                        // weekday_time_blocks: [{ weekday: 0..6, from: "09:00", to: "17:00" }, ...]
                        const blocks = [];
                        const start = new Date(this.window.start);
                        for (let i=0;i<7;i++) {
                            const day = new Date(start); day.setDate(day.getDate()+i);
                            const weekday = (i+1)%7; // Mon=1..Sun=0 mapping
                            const tbs = (shift.weekday_time_blocks||[]).filter(b => b.weekday === weekday && shift.available !== false);
                            for (const tb of tbs) {
                                const from = new Date(day); const [fh,fm] = (tb.from||'09:00').split(':'); from.setHours(+fh, +fm, 0, 0);
                                const to = new Date(day); const [th,tm] = (tb.to||'17:00').split(':'); to.setHours(+th, +tm, 0, 0);
                                blocks.push({ from, to });
                            }
                        }
                        return blocks;
                    },
                    splitByOccupancy(blocks, occupancy) {
                        // subtract occupancy intervals from blocks
                        const result = [];
                        for (const block of blocks) {
                            let parts = [ { from: new Date(block.from), to: new Date(block.to) } ];
                            for (const occ of occupancy) {
                                const of = new Date(occ.from), ot = new Date(occ.to);
                                parts = parts.flatMap(p => {
                                    // no overlap
                                    if (ot <= p.from || of >= p.to) return [p];
                                    const segs = [];
                                    if (of > p.from) segs.push({ from: p.from, to: new Date(of) });
                                    if (ot < p.to) segs.push({ from: new Date(ot), to: p.to });
                                    return segs;
                                });
                            }
                            result.push(...parts.filter(p => p.to > p.from));
                        }
                        return result;
                    },
                    availableBlocks(resourceId, weekdayOffset) {
                        const shifts = (this.rawShifts[resourceId]||[]);
                        const occ = (this.rawOccupancy[resourceId]||[]);
                        const blocks = shifts.flatMap(s => this.blocksFromShift(s));
                        const byDay = blocks.filter(b => {
                            const d = new Date(this.window.start); d.setDate(d.getDate()+weekdayOffset);
                            return b.from.toDateString() === d.toDateString();
                        });
                        const occDay = occ.filter(o => {
                            const d = new Date(this.window.start); d.setDate(d.getDate()+weekdayOffset);
                            const of = new Date(o.from), ot = new Date(o.to);
                            return of.toDateString() === d.toDateString() || ot.toDateString() === d.toDateString();
                        });
                        const free = this.splitByOccupancy(byDay, occDay).map((p, idx) => ({ key: `${resourceId}-a-${weekdayOffset}-${idx}`, ...p }));
                        return free;
                    },
                    occupiedBlocks(resourceId, weekdayOffset) {
                        const occ = (this.rawOccupancy[resourceId]||[]);
                        const d = new Date(this.window.start); d.setDate(d.getDate()+weekdayOffset);
                        return occ.filter(o => {
                            const of = new Date(o.from), ot = new Date(o.to);
                            return of.toDateString() === d.toDateString() || ot.toDateString() === d.toDateString();
                        }).map((o, idx) => ({ key: `${resourceId}-o-${weekdayOffset}-${idx}`, ...o }));
                    },
                    openBook(resourceId, from, to) {
                        this.form.resource_id = resourceId;
                        // preset within clicked block
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

