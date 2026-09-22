{!! view_render_event('admin.dashboard.index.revenue_by_types.before') !!}

<!-- Total Leads Vue Component -->
<v-dashboard-revenue-by-types>
    <!-- Shimmer -->
    <x-admin::shimmer.dashboard.index.revenue-by-types />
</v-dashboard-revenue-by-types>

{!! view_render_event('admin.dashboard.index.revenue_by_types.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-dashboard-revenue-by-types-template"
    >
        <!-- Shimmer -->
        <template v-if="isLoading">
            <x-admin::shimmer.dashboard.index.revenue-by-types />
        </template>

        <!-- Total Sales Section -->
        <template v-else>
            <div class="grid gap-4 rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col justify-between gap-1">
                    <p class="text-base font-semibold dark:text-gray-300">
                        @lang('admin::app.dashboard.index.revenue-by-types.title')
                    </p>
                </div>

                <!-- Doughnut Chart -->
                <div
                    class="flex w-full flex-col gap-4"
                    v-if="report.statistics.length"
                >
                    <div class="mx-auto h-52 w-full max-w-[220px]">
                        <x-admin::charts.doughnut
                            ::labels="chartLabels"
                            ::datasets="chartDatasets"
                        />
                    </div>

                    <ul class="flex flex-col gap-2">
                        <li
                            class="flex items-start justify-between gap-3 text-sm text-gray-800 dark:text-gray-200"
                            v-for="(stat, index) in report.statistics"
                        >
                            <span class="flex min-w-0 items-start gap-2">
                                <span
                                    class="mt-1 h-3 w-3 shrink-0 rounded-sm"
                                    :style="{ backgroundColor: colors[index] }"
                                ></span>
                                <span class="min-w-0 flex-1 leading-5">
                                    @{{ stat.name || 'Onbekend' }}
                                </span>
                            </span>
                            <span class="shrink-0 font-semibold tabular-nums">
                                @{{ stat.total }}
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Empty Product Design -->
                <div
                    class="flex flex-col gap-8 p-4"
                    v-else
                >
                    <div class="grid justify-center justify-items-center gap-3.5 py-2.5">
                        <!-- Placeholder Image -->
                        <img
                            src="{{ vite()->asset('images/empty-placeholders/default.svg') }}"
                            class="dark:mix-blend-exclusion dark:invert"
                        >

                        <!-- Add Variants Information -->
                        <div class="flex flex-col items-center">
                            <p class="text-base font-semibold text-gray-400">
                                @lang('admin::app.dashboard.index.revenue-by-sources.empty-title')
                            </p>

                            <p class="text-gray-400">
                                @lang('admin::app.dashboard.index.revenue-by-sources.empty-info')
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-dashboard-revenue-by-types', {
            template: '#v-dashboard-revenue-by-types-template',

            data() {
                return {
                    report: [],

                    colors: [
                        '#8979FF',
                        '#FF928A',
                        '#3CC3DF',
                        '#FFAE4C',
                        '#54C392',
                        '#F4B942',
                        '#5B8DEF',
                        '#E879F9',
                        '#FB7185',
                        '#34D399',
                        '#A78BFA',
                        '#F97316',
                    ],

                    isLoading: true,
                }
            },

            computed: {
                chartLabels() {
                    return this.report.statistics.map(({ name }) => name || 'Onbekend');
                },

                chartDatasets() {
                    return [{
                        data: this.report.statistics.map(({ total }) => total),
                        barThickness: 24,
                        backgroundColor: this.colors,
                    }];
                }
            },

            mounted() {
                this.getStats({});

                this.$emitter.on('reporting-filter-updated', this.getStats);
            },

            methods: {
                getStats(filters) {
                    this.isLoading = true;

                    var filters = Object.assign({}, filters);

                    filters.type = 'revenue-by-types';

                    this.$axios.get("{{ route('admin.dashboard.stats') }}", {
                            params: filters
                        })
                        .then(response => {
                            this.report = response.data;

                            this.extendColors(this.report.statistics.length);

                            this.isLoading = false;
                        })
                        .catch(error => {});
                },

                extendColors(length) {
                    while (this.colors.length < length) {
                        const hue = Math.floor(Math.random() * 360);
                        const newColor = `hsl(${hue}, 70%, 60%)`;
                        this.colors.push(newColor);
                    }
                },
            }
        });
    </script>
@endPushOnce
