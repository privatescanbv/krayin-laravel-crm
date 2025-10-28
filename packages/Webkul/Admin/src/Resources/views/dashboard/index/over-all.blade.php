{!! view_render_event('admin.dashboard.index.over_all.before') !!}

<!-- Over Details Vue Component -->
<v-dashboard-over-all-stats>
    <!-- Shimmer -->
    <x-admin::shimmer.dashboard.index.over-all />
</v-dashboard-over-all-stats>

{!! view_render_event('admin.dashboard.index.over_all.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-dashboard-over-all-stats-template"
    >
        <!-- Shimmer -->
        <template v-if="isLoading">
            <x-admin::shimmer.dashboard.index.over-all />
        </template>

        <!-- Total Sales Section -->
        <template v-else>
            <!-- Stats Cards -->
            <div class="grid grid-cols-4 gap-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
                <!-- Total Leads Card -->
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('admin::app.dashboard.index.over-all.total-leads')
                    </p>

                    <div class="flex gap-2">
                        <p class="text-xl font-bold dark:text-gray-300">
                            @{{ report.statistics.total_leads.current }}
                        </p>

                        <div class="flex items-center gap-0.5">
                            <span
                                class="text-base !font-semibold text-green-500"
                                :class="[report.statistics.total_leads.progress < 0 ? 'icon-stats-down text-red-500 dark:!text-red-500' : 'icon-stats-up text-green-500 dark:!text-green-500']"
                            ></span>

                            <p
                                class="text-xs font-semibold text-green-500"
                                :class="[report.statistics.total_leads.progress < 0 ?  'text-red-500' : 'text-green-500']"
                            >
                                @{{ Math.abs(Number(report.statistics.total_leads.progress ?? 0).toFixed(2)) }}%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Won Leads Card -->
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        Gewonnen Leads
                    </p>

                    <div class="flex gap-2">
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">
                            @{{ report.statistics.won_leads.current }}
                        </p>

                        <div class="flex items-center gap-0.5">
                            <span
                                class="text-base !font-semibold text-green-500"
                                :class="[report.statistics.won_leads.progress < 0 ? 'icon-stats-down text-red-500 dark:!text-red-500' : 'icon-stats-up text-green-500 dark:!text-green-500']"
                            ></span>

                            <p
                                class="text-xs font-semibold text-green-500"
                                :class="[report.statistics.won_leads.progress < 0 ?  'text-red-500' : 'text-green-500']"
                            >
                                @{{ Math.abs(Number(report.statistics.won_leads.progress ?? 0).toFixed(2)) }}%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Lost Leads Card -->
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        Verloren Leads
                    </p>

                    <div class="flex gap-2">
                        <p class="text-xl font-bold text-red-600 dark:text-red-400">
                            @{{ report.statistics.lost_leads.current }}
                        </p>

                        <div class="flex items-center gap-0.5">
                            <span
                                class="text-base !font-semibold text-green-500"
                                :class="[report.statistics.lost_leads.progress < 0 ? 'icon-stats-down text-red-500 dark:!text-red-500' : 'icon-stats-up text-green-500 dark:!text-green-500']"
                            ></span>

                            <p
                                class="text-xs font-semibold text-green-500"
                                :class="[report.statistics.lost_leads.progress < 0 ?  'text-red-500' : 'text-green-500']"
                            >
                                @{{ Math.abs(Number(report.statistics.lost_leads.progress ?? 0).toFixed(2)) }}%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Average Lead Per Day -->
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('admin::app.dashboard.index.over-all.average-leads-per-day')
                    </p>

                    <div class="flex gap-2">
                        <p class="text-xl font-bold dark:text-gray-300">
                            @{{ Number(report.statistics.average_leads_per_day.current ?? 0).toFixed(2) }}
                        </p>

                        <div class="flex items-center gap-0.5">
                            <span
                                class="text-base !font-semibold text-green-500"
                                :class="[report.statistics.average_leads_per_day.progress < 0 ? 'icon-stats-down text-red-500 dark:!text-red-500' : 'icon-stats-up text-green-500 dark:!text-green-500']"
                            ></span>

                            <p
                                class="text-xs font-semibold text-green-500"
                                :class="[report.statistics.average_leads_per_day.progress < 0 ?  'text-red-500' : 'text-green-500']"
                            >
                                @{{ Math.abs(Number(report.statistics.average_leads_per_day.progress ?? 0).toFixed(2)) }}%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Quotes -->
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('admin::app.dashboard.index.over-all.total-quotations')
                    </p>

                    <div class="flex gap-2">
                        <p class="text-xl font-bold dark:text-gray-300">
                            @{{ report.statistics.total_quotations.current }}
                        </p>

                        <div class="flex items-center gap-0.5">
                            <span
                                class="text-base !font-semibold text-green-500"
                                :class="[report.statistics.total_quotations.progress < 0 ? 'icon-stats-down text-red-500 dark:!text-red-500' : 'icon-stats-up text-green-500 dark:!text-green-500']"
                            ></span>

                            <p
                                class="text-xs font-semibold text-green-500"
                                :class="[report.statistics.total_quotations.progress < 0 ?  'text-red-500' : 'text-green-500']"
                            >
                                @{{ Math.abs(Number(report.statistics.total_quotations.progress ?? 0).toFixed(2)) }}%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Persons -->
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('admin::app.dashboard.index.over-all.total-persons')
                    </p>

                    <div class="flex gap-2">
                        <p class="text-xl font-bold dark:text-gray-300">
                            @{{ report.statistics.total_persons.current }}
                        </p>

                        <div class="flex items-center gap-0.5">
                            <span
                                class="text-base !font-semibold text-green-500"
                                :class="[report.statistics.total_persons.progress < 0 ? 'icon-stats-down text-red-500 dark:!text-red-500' : 'icon-stats-up text-green-500 dark:!text-green-500']"
                            ></span>

                            <p
                                class="text-xs font-semibold text-green-500"
                                :class="[report.statistics.total_persons.progress < 0 ?  'text-red-500' : 'text-green-500']"
                            >
                                @{{ Math.abs(Number(report.statistics.total_persons.progress ?? 0).toFixed(2)) }}%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Organizations -->
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('admin::app.dashboard.index.over-all.total-organizations')
                    </p>

                    <div class="flex gap-2">
                        <p class="text-xl font-bold dark:text-gray-300">
                            @{{ report.statistics.total_organizations.current }}
                        </p>

                        <div class="flex items-center gap-0.5">
                            <span
                                class="text-base !font-semibold text-green-500"
                                :class="[report.statistics.total_organizations.progress < 0 ? 'icon-stats-down text-red-500 dark:!text-red-500' : 'icon-stats-up text-green-500 dark:!text-green-500']"
                            ></span>

                            <p
                                class="text-xs font-semibold text-green-500"
                                :class="[report.statistics.total_organizations.progress < 0 ?  'text-red-500' : 'text-green-500']"
                            >
                                @{{ Math.abs(Number(report.statistics.total_organizations.progress ?? 0).toFixed(2)) }}%
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Statistieken per Afdeling
                </h3>

                <div class="grid grid-cols-2 gap-6 max-lg:grid-cols-1">
                    <!-- Herniapoli Department -->
                    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-6">
                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                            <span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                            Herniapoli
                        </h4>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    @{{ report.statistics.department_stats.herniapoli.total }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Totaal</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    @{{ report.statistics.department_stats.herniapoli.won }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Gewonnen</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                                    @{{ report.statistics.department_stats.herniapoli.lost }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Verloren</div>
                            </div>
                        </div>

                        <div class="mt-4 bg-gray-100 dark:bg-gray-800 rounded-lg p-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Conversie ratio:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">
                                    @{{ report.statistics.department_stats.herniapoli.total > 0 ?
                                        Math.round((report.statistics.department_stats.herniapoli.won / report.statistics.department_stats.herniapoli.total) * 100) : 0 }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Privatescan Department -->
                    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-6">
                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                            <span class="inline-block w-3 h-3 bg-purple-500 rounded-full mr-2"></span>
                            Privatescan
                        </h4>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    @{{ report.statistics.department_stats.privatescan.total }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Totaal</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    @{{ report.statistics.department_stats.privatescan.won }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Gewonnen</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                                    @{{ report.statistics.department_stats.privatescan.lost }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Verloren</div>
                            </div>
                        </div>

                        <div class="mt-4 bg-gray-100 dark:bg-gray-800 rounded-lg p-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Conversie ratio:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">
                                    @{{ report.statistics.department_stats.privatescan.total > 0 ?
                                        Math.round((report.statistics.department_stats.privatescan.won / report.statistics.department_stats.privatescan.total) * 100) : 0 }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-dashboard-over-all-stats', {
            template: '#v-dashboard-over-all-stats-template',

            data() {
                return {
                    report: {
                        statistics: {
                            total_leads: { current: 0, progress: 0 },
                            won_leads: { current: 0, progress: 0 },
                            lost_leads: { current: 0, progress: 0 },
                            average_leads_per_day: { current: 0, progress: 0 },
                            total_quotations: { current: 0, progress: 0 },
                            total_persons: { current: 0, progress: 0 },
                            total_organizations: { current: 0, progress: 0 },
                            department_stats: {
                                herniapoli: { total: 0, won: 0, lost: 0 },
                                privatescan: { total: 0, won: 0, lost: 0 },
                            },
                        },
                        date_range: '',
                    },

                    isLoading: true,

                    chart: undefined,
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

                    filters.type = 'over-all';

                    this.$axios.get("{{ route('admin.dashboard.stats') }}", {
                            params: filters
                        })
                        .then(response => {
                            this.report = response.data;

                            this.isLoading = false;
                        })
                        .catch(error => {});
                },
            }
        });
    </script>
@endPushOnce
