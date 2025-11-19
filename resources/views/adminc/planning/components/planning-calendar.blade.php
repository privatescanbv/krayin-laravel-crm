@pushOnce('meta')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endPushOnce

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
        .calendar-block-occupied { cursor: default; min-height: 20px; }
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

    <script type="text/x-template" id="v-planning-calendar-template">
        <div class="flex flex-col gap-4">
            <slot name="filters"></slot>

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
                        class="text-blue-600 hover:text-activity-task-text"
                        :title="r.notes || ''"
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
                             @click="block.clickable ? handleBlockClick(block) : null">
                            <div v-if="block.type === 'occupied'" class="font-semibold text-xs">@{{ block.lead_name || 'Onbekend' }}</div>
                            <div v-else-if="block.type === 'available'" class="text-xs font-medium">Beschikbaar</div>
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
                         @click="block.clickable ? handleBlockClick(block) : null">
                        <div v-if="block.type === 'occupied'" class="font-semibold">@{{ block.lead_name || 'Onbekend' }}</div>
                        <div v-else-if="block.type === 'available'" class="font-medium">Beschikbaar</div>
                        <div class="text-xs">@{{ timeRange(block.from, block.to) }}</div>
                    </div>
                </div>
            </div>

            <slot name="modals"></slot>
        </div>
    </script>

    <script type="module">
        app.component('v-planning-calendar', {
            template: '#v-planning-calendar-template',
            props: {
                viewType: { type: String, default: 'week' },
                availabilityUrl: { type: String, required: true },
                autoLoad: { type: Boolean, default: true },
            },
            data() {
                return {
                    window: this.getInitialWindow(),
                    resources: [],
                    blocks: {},
                    hours: Array.from({ length: 10 }, (_, i) => i + 8), // 08:00 - 17:00
                    loading: false,
                    errorMessage: '',
                    pixelsPerHour: 50,
                    resourceColors: [
                        '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
                    ],
                };
            },
            watch: {
                viewType: {
                    handler(newViewType) {
                        this.window = this.getInitialWindow();
                        if (this.autoLoad) {
                            this.loadAvailability();
                        }
                    },
                    immediate: false
                }
            },
            computed: {
                periodLabel() {
                    if (this.viewType === 'week') {
                        const weekNumber = this.getWeekNumber(this.window.start);
                        return `Week ${weekNumber}`;
                    } else {
                        return this.window.start.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' });
                    }
                },
                monthDays() {
                    if (this.viewType !== 'month') return [];

                    const days = [];
                    const start = new Date(this.window.start);
                    const end = new Date(this.window.end);

                    const firstDay = new Date(start);
                    const firstMonday = new Date(firstDay);
                    firstMonday.setDate(firstDay.getDate() - (firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1));

                    const current = new Date(firstMonday);
                    while (current <= end) {
                        days.push({
                            date: this.getDateKey(current),
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
                if (this.autoLoad) {
                    this.loadAvailability();
                }
                this.scrollToCurrentTime();
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
                getInitialWindow() {
                    const now = new Date();
                    if (this.viewType === 'month') {
                        const start = this.startOfMonth(now);
                        const end = this.endOfMonth(now);
                        return { start, end };
                    } else {
                        const start = this.startOfWeek(now);
                        const end = new Date(start);
                        end.setDate(start.getDate() + 6);
                        return { start, end };
                    }
                },
                /**
                 * Generate a timezone-safe date key in YYYY-MM-DD format
                 * This avoids timezone conversion issues with toISOString()
                 */
                getDateKey(date) {
                    return date.getFullYear() + '-' +
                           String(date.getMonth() + 1).padStart(2, '0') + '-' +
                           String(date.getDate()).padStart(2, '0');
                },
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
                dayDate(offset) {
                    const d = new Date(this.window.start);
                    d.setDate(d.getDate() + offset);
                    // Ensure we're working with local time to avoid timezone issues
                    d.setHours(12, 0, 0, 0); // Set to noon to avoid DST issues
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
                async loadAvailability(params = {}) {
                    if (this.loading) return;

                    this.loading = true;
                    this.errorMessage = '';

                    try {
                        const urlParams = new URLSearchParams({
                            view: this.viewType,
                            start: this.window.start.toISOString(),
                            end: this.window.end.toISOString(),
                            ...params
                        });

                        const url = `${this.availabilityUrl}?${urlParams}`;

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
                        this.blocks = data.blocks || {};

                        this.$emit('loaded', data);
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
                    const dayKey = this.getDateKey(day);
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

                    const sortedBlocks = blocks.sort((a, b) => new Date(a.from) - new Date(b.from));

                    // Group overlapping available blocks and calculate layout
                    return this.calculateOverlappingBlocks(sortedBlocks);
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
                        return {};
                    }

                    const from = new Date(block.from);
                    const to = new Date(block.to);
                    const top = this.topOffsetPx(from);
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

                    const resource = this.resources.find(r => r.id === block.resource_id);
                    const colorIndex = this.resources.indexOf(resource) % this.resourceColors.length;
                    const color = this.resourceColors[colorIndex];

                    const style = {
                        top: top + 'px',
                        height: height + 'px',
                        backgroundColor: `rgba(${this.hexToRgb(color)}, 0.15)`,
                        borderColor: `rgba(${this.hexToRgb(color)}, 0.3)`,
                        color: this.getContrastColor(color)
                    };

                    // If block has overlapping layout info, apply width and left position
                    if (block.overlapIndex !== undefined && block.overlapCount !== undefined) {
                        const widthPercent = 100 / block.overlapCount;
                        style.width = `calc(${widthPercent}% - 12px)`;
                        style.left = `calc(${(block.overlapIndex * widthPercent)}% + 6px)`;
                        style.right = 'auto'; // Override the default right: 6px from CSS
                        style.position = 'absolute';
                    }

                    return style;
                },
                calculateOverlappingBlocks(blocks) {
                    const avail = blocks
                        .filter(b => b.type === 'available')
                        .map(b => ({
                            ref: b,
                            start: new Date(b.from).getTime(),
                            end: new Date(b.to).getTime(),
                            col: -1,
                            over: 1,
                        }))
                        .sort((a, b) => a.start - b.start || a.end - b.end);

                    const occupied = blocks.filter(b => b.type === 'occupied');
                    const active = [];

                    for (const b of avail) {
                        // Drop finished
                        for (let i = active.length - 1; i >= 0; i--) {
                            if (active[i].end <= b.start) active.splice(i, 1);
                        }

                        // Smallest free column
                        let c = 0;
                        while (active.some(a => a.col === c)) c++;
                        b.col = c;

                        active.push(b);
                        active.sort((x, y) => x.end - y.end);

                        const k = active.length;
                        for (const a of active) a.over = Math.max(a.over, k);
                    }

                    // Apply layout
                    for (const b of avail) {
                        b.ref.overlapIndex = b.col;
                        b.ref.overlapCount = b.over;
                    }

                    return [...avail.map(x => x.ref), ...occupied].sort((a, b) => new Date(a.from) - new Date(b.from));
                },
                getBlockTooltip(block) {
                    const resource = this.resources.find(r => r.id === block.resource_id);
                    const from = new Date(block.from);
                    const to = new Date(block.to);
                    const name = resource?.name || 'Onbekend';
                    const notes = resource?.notes ? ` — ${resource.notes}` : '';
                    const timeStr = `${from.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} tot ${to.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;

                    if (block.type === 'occupied') {
                        const leadName = block.lead_name || 'Onbekend';
                        return `${name}${notes}\nGeboekt: ${leadName}\nTijd: ${timeStr}`;
                    }

                    return `${name}${notes} - ${timeStr}`;
                },
                handleBlockClick(block) {
                    this.$emit('block-click', block);
                },
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
                            const effectiveUnits = 16.5;
                            const unit = Math.max(22, Math.floor(containerHeight / effectiveUnits));
                            this.pixelsPerHour = unit;
                            this.halfPixelsPerHour = Math.max(14, Math.floor(unit / 2));
                        }
                    });
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
@endPushOnce
