<x-admin::layouts>
    <x-slot:title>
        Verkooporders op onderzoeksdatum
    </x-slot>

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                Verkooporders op onderzoeksdatum
            </p>
        </div>
    </div>

    <v-orders-investigation-report>
        <div class="light-shimmer-bg dark:shimmer h-96 w-full rounded-lg"></div>
    </v-orders-investigation-report>

    @pushOnce('scripts')
        <script
            type="module"
            src="{{ vite()->asset('js/chart.js') }}"
        >
        </script>

        <script
            type="text/x-template"
            id="v-orders-investigation-report-template"
        >
            <div>
                <template v-if="isInitialLoad && isLoading">
                    <div class="light-shimmer-bg dark:shimmer h-96 w-full rounded-lg"></div>
                </template>

                <div
                    v-show="!(isInitialLoad && isLoading)"
                    class="flex flex-col gap-4"
                >
                    <!-- Week navigation -->
                    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-md border text-xl text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-400"
                                @click="prevWeek"
                            >
                                <span class="icon-left-arrow"></span>
                            </button>

                            <p class="min-w-[260px] text-center text-base font-semibold text-gray-800 dark:text-white max-sm:min-w-0">
                                @{{ weekLabel }}
                            </p>

                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-md border text-xl text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-400"
                                @click="nextWeek"
                            >
                                <span class="icon-right-arrow"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="relative min-h-[300px]">
                            <canvas
                                :id="'investigation-chart-' + $.uid"
                                class="max-h-[300px] w-full"
                            ></canvas>

                            <div
                                v-if="! isLoading && ! rows.length"
                                class="absolute inset-0 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                Geen orders gevonden voor deze week.
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-hidden rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b px-4 py-3 dark:border-gray-800">
                            <p class="text-base font-semibold text-gray-800 dark:text-gray-300">
                                Orders (@{{ rows.length }})
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b text-xs font-medium uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        <th class="px-4 py-3">Onderzoeksdatum</th>
                                        <th class="px-4 py-3">Naam order + klant</th>
                                        <th class="px-4 py-3">Datum 1e onderzoek</th>
                                        <th class="px-4 py-3">WF status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="row in rows"
                                        :key="row.id"
                                        class="border-b dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    >
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            @{{ row.onderzoeksdatum }}
                                        </td>

                                        <td class="px-4 py-3 text-sm">
                                            <a
                                                :href="row.url"
                                                class="font-medium text-brandColor hover:underline"
                                            >@{{ row.naam }}</a>
                                        </td>

                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            @{{ row.datum_1e_onderzoek }}
                                        </td>

                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            @{{ row.wf_status }}
                                        </td>
                                    </tr>

                                    <tr v-if="! rows.length">
                                        <td
                                            colspan="4"
                                            class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            Geen orders gevonden.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-orders-investigation-report', {
                template: '#v-orders-investigation-report-template',

                data() {
                    return {
                        week: {{ $initialWeek }},
                        year: {{ $initialYear }},
                        weekLabel: '',
                        days: [],
                        chartData: [],
                        rows: [],
                        isInitialLoad: true,
                        isLoading: true,
                    }
                },

                mounted() {
                    this._chart = null;
                    this.loadData();
                },

                beforeUnmount() {
                    if (this._chart) {
                        this._chart.destroy();
                        this._chart = null;
                    }
                },

                methods: {
                    async loadData() {
                        this.isLoading = true;
                        try {
                            const response = await this.$axios.get("{{ route('admin.reports.orders-by-investigation-date.data') }}", {
                                params: { week: this.week, year: this.year }
                            });
                            const data = response.data;
                            this.weekLabel  = data.week_label;
                            this.days       = data.days;
                            this.chartData  = data.chart_data;
                            this.rows       = data.rows;
                            this.$nextTick(() => this.renderChart());
                        } finally {
                            this.isLoading      = false;
                            this.isInitialLoad  = false;
                        }
                    },

                    renderChart() {
                        const canvasId = 'investigation-chart-' + this.$.uid;
                        const canvas   = document.getElementById(canvasId);
                        if (! canvas) return;

                        const labels   = this.days.map(d => d.label);
                        const dataset  = {
                            label:           'Aantal orders',
                            data:            JSON.parse(JSON.stringify(this.chartData)),
                            backgroundColor: '#8979FF',
                            borderRadius:    4,
                        };

                        if (this._chart) {
                            this._chart.data.labels           = labels;
                            this._chart.data.datasets[0].data = JSON.parse(JSON.stringify(this.chartData));
                            this._chart.update('none');
                            return;
                        }

                        const weekendIndices = [5, 6];

                        this._chart = new Chart(canvas, {
                            type: 'bar',
                            data: { labels, datasets: [dataset] },
                            options: {
                                responsive:          true,
                                maintainAspectRatio: false,
                                animation:           false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: (ctx) => ' ' + ctx.parsed.y + ' order(s)',
                                        },
                                    },
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        border:      { dash: [8, 4] },
                                        ticks: {
                                            color: (ctx) => weekendIndices.includes(ctx.index) ? '#9CA3AF' : '#374151',
                                            font:  (ctx) => ({ size: weekendIndices.includes(ctx.index) ? 10 : 12 }),
                                        },
                                    },
                                    y: {
                                        beginAtZero: true,
                                        border:      { dash: [8, 4] },
                                        ticks: {
                                            stepSize: 1,
                                            callback: (value) => Number.isInteger(value) ? value : null,
                                        },
                                    },
                                },
                            },
                        });
                    },

                    prevWeek() {
                        const date = this.isoWeekDate(this.year, this.week);
                        date.setDate(date.getDate() - 7);
                        const isoWeek = this.getISOWeek(date);
                        this.week = isoWeek.week;
                        this.year = isoWeek.year;
                        this.updateUrl();
                        this.loadData();
                    },

                    nextWeek() {
                        const date = this.isoWeekDate(this.year, this.week);
                        date.setDate(date.getDate() + 7);
                        const isoWeek = this.getISOWeek(date);
                        this.week = isoWeek.week;
                        this.year = isoWeek.year;
                        this.updateUrl();
                        this.loadData();
                    },

                    updateUrl() {
                        const url = new URL(window.location.href);
                        url.searchParams.set('week', this.week);
                        url.searchParams.set('year', this.year);
                        history.pushState({}, '', url.toString());
                    },

                    getISOWeek(date) {
                        const target    = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
                        const dayNumber = target.getUTCDay() || 7;
                        target.setUTCDate(target.getUTCDate() + 4 - dayNumber);
                        const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));
                        const week      = Math.ceil((((target - yearStart) / 86400000) + 1) / 7);
                        return { week, year: target.getUTCFullYear() };
                    },

                    isoWeekDate(year, week) {
                        const simple    = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
                        const dayOfWeek = simple.getUTCDay();
                        const isoWeekStart = simple;
                        if (dayOfWeek <= 4) {
                            isoWeekStart.setUTCDate(simple.getUTCDate() - simple.getUTCDay() + 1);
                        } else {
                            isoWeekStart.setUTCDate(simple.getUTCDate() + 8 - simple.getUTCDay());
                        }
                        return new Date(isoWeekStart.getUTCFullYear(), isoWeekStart.getUTCMonth(), isoWeekStart.getUTCDate());
                    },
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
