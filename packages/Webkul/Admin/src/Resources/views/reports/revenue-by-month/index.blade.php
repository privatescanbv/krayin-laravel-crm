<x-admin::layouts>
    <x-slot:title>
        Omzet per maand
    </x-slot>

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                Omzet per maand
            </p>
        </div>
    </div>

    <v-revenue-by-month>
        <div class="light-shimmer-bg dark:shimmer h-96 w-full rounded-lg"></div>
    </v-revenue-by-month>

    @pushOnce('scripts')
        <script
            type="module"
            src="{{ vite()->asset('js/chart.js') }}"
        >
        </script>

        <script
            type="text/x-template"
            id="v-revenue-by-month-template"
        >
            <div>
                <template v-if="isInitialLoad && isLoading">
                    <div class="light-shimmer-bg dark:shimmer h-96 w-full rounded-lg"></div>
                </template>

                <div
                    v-show="!(isInitialLoad && isLoading)"
                    class="flex flex-col gap-4"
                >
                    <!-- Controls card -->
                    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <!-- Period pickers -->
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Van</span>
                                    <input
                                        type="month"
                                        v-model="from"
                                        @change="onPeriodChange"
                                        class="rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                    />
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">T/m</span>
                                    <input
                                        type="month"
                                        v-model="to"
                                        @change="onPeriodChange"
                                        class="rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                    />
                                </div>
                            </div>

                            <!-- Filter dropdowns -->
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Group filter -->
                                <div
                                    class="relative"
                                    v-click-outside="() => groupDropdownOpen = false"
                                >
                                    <button
                                        type="button"
                                        class="flex min-h-9 min-w-[200px] items-center justify-between gap-2 rounded-md border px-3 py-2 text-left text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        @click="groupDropdownOpen = ! groupDropdownOpen"
                                    >
                                        <span>@{{ selectedGroupText }}</span>
                                        <span class="icon-down-arrow text-xs"></span>
                                    </button>

                                    <div
                                        v-if="groupDropdownOpen"
                                        class="absolute right-0 z-20 mt-1 w-56 rounded-md border bg-white p-2 shadow dark:border-gray-800 dark:bg-gray-900"
                                    >
                                        <label
                                            v-for="group in groups"
                                            :key="group.id"
                                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                        >
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 shrink-0 accent-[#8979FF]"
                                                :value="group.id"
                                                v-model="selectedGroups"
                                                @change="loadData"
                                            />
                                            <span
                                                class="h-3 w-3 flex-shrink-0 rounded-sm"
                                                :style="{ backgroundColor: group.color }"
                                            ></span>
                                            <span>@{{ group.label }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Department filter -->
                                <div
                                    class="relative"
                                    v-click-outside="() => departmentDropdownOpen = false"
                                >
                                    <button
                                        type="button"
                                        class="flex min-h-9 min-w-[180px] items-center justify-between gap-2 rounded-md border px-3 py-2 text-left text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        @click="departmentDropdownOpen = ! departmentDropdownOpen"
                                    >
                                        <span>@{{ selectedDepartmentText }}</span>
                                        <span class="icon-down-arrow text-xs"></span>
                                    </button>

                                    <div
                                        v-if="departmentDropdownOpen"
                                        class="absolute right-0 z-20 mt-1 w-56 rounded-md border bg-white p-2 shadow dark:border-gray-800 dark:bg-gray-900"
                                    >
                                        <label
                                            v-for="department in departments"
                                            :key="department.id"
                                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                        >
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 shrink-0 accent-[#8979FF]"
                                                :value="department.id"
                                                v-model="selectedDepartments"
                                                @change="loadData"
                                            />
                                            <span>@{{ department.label }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart card -->
                    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="relative min-h-[400px]">
                            <canvas
                                :id="'revenue-month-chart-' + $.uid"
                                class="max-h-[400px] w-full"
                            ></canvas>

                            <div
                                v-if="! isLoading && ! monthsData.length"
                                class="absolute inset-0 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                Geen omzet gevonden voor deze selectie.
                            </div>
                        </div>
                    </div>

                    <!-- Summary table -->
                    <div class="overflow-hidden rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b px-4 py-3 dark:border-gray-800">
                            <p class="text-base font-semibold text-gray-800 dark:text-gray-300">
                                @{{ periodLabel }}
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b text-xs font-medium uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        <th class="px-4 py-3">Maand</th>
                                        <th v-if="selectedGroups.includes('option')" class="px-4 py-3 text-right">Option</th>
                                        <th v-if="selectedGroups.includes('nearly_won')" class="px-4 py-3 text-right">Bijna gewonnen</th>
                                        <th v-if="selectedGroups.includes('won')" class="px-4 py-3 text-right">Gewonnen</th>
                                        <th v-if="selectedGroups.includes('lost')" class="px-4 py-3 text-right">Verloren</th>
                                        <th class="px-4 py-3 text-right">Inkoop</th>
                                        <th class="px-4 py-3 text-right">Totaal</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="row in monthsData"
                                        :key="row.key"
                                        class="border-b dark:border-gray-800 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    >
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">@{{ row.label }}</td>
                                        <td v-if="selectedGroups.includes('option')" class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">@{{ formatCurrency(row.option) }}</td>
                                        <td v-if="selectedGroups.includes('nearly_won')" class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">@{{ formatCurrency(row.nearly_won) }}</td>
                                        <td v-if="selectedGroups.includes('won')" class="px-4 py-3 text-right text-sm font-medium text-gray-800 dark:text-white">@{{ formatCurrency(row.won) }}</td>
                                        <td v-if="selectedGroups.includes('lost')" class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">@{{ formatCurrency(row.lost) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">@{{ formatCurrency(row.inkoop) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-medium text-gray-800 dark:text-white">@{{ formatCurrency(row.total) }}</td>
                                    </tr>

                                    <tr v-if="! monthsData.length">
                                        <td
                                            colspan="7"
                                            class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            Geen gegevens gevonden.
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
            app.component('v-revenue-by-month', {
                template: '#v-revenue-by-month-template',

                directives: {
                    clickOutside: {
                        beforeMount(element, binding) {
                            element.__clickOutsideHandler__ = event => {
                                if (! element.contains(event.target)) {
                                    binding.value(event);
                                }
                            };

                            document.addEventListener('click', element.__clickOutsideHandler__);
                        },

                        unmounted(element) {
                            document.removeEventListener('click', element.__clickOutsideHandler__);
                        },
                    },
                },

                data() {
                    return {
                        from: '{{ $initialFrom }}',
                        to: '{{ $initialTo }}',
                        periodLabel: '',
                        months: [],
                        datasets: [],
                        monthsData: [],
                        selectedGroups: ['option', 'nearly_won', 'won'],
                        selectedDepartments: ['privatescan', 'hernia'],
                        groups: [
                            { id: 'option',     label: 'Option',         color: '#3CC3DF' },
                            { id: 'nearly_won', label: 'Bijna gewonnen', color: '#FFD166' },
                            { id: 'won',        label: 'Gewonnen',        color: '#6BCB77' },
                            { id: 'lost',       label: 'Verloren',        color: '#FF928A' },
                        ],
                        departments: [
                            { id: 'privatescan', label: 'Privatescan' },
                            { id: 'hernia',      label: 'Hernia' },
                        ],
                        isInitialLoad: true,
                        isLoading: true,
                        groupDropdownOpen: false,
                        departmentDropdownOpen: false,
                    };
                },

                computed: {
                    selectedGroupText() {
                        if (! this.selectedGroups.length) return 'Geen groepen';
                        if (this.selectedGroups.length === this.groups.length) return 'Alle groepen';
                        return `${this.selectedGroups.length} groepen`;
                    },

                    selectedDepartmentText() {
                        if (! this.selectedDepartments.length) return 'Geen afdelingen';
                        if (this.selectedDepartments.length === this.departments.length) return 'Alle afdelingen';
                        return this.departments
                            .filter(d => this.selectedDepartments.includes(d.id))
                            .map(d => d.label)
                            .join(', ');
                    },
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
                            const response = await this.$axios.get('{{ route('admin.reports.revenue-by-month.data') }}', {
                                params: {
                                    from: this.from,
                                    to: this.to,
                                    groups: this.selectedGroups,
                                    departments: this.selectedDepartments,
                                },
                            });
                            const data = response.data;
                            this.periodLabel = data.period_label;
                            this.months      = data.months;
                            this.datasets    = data.datasets;
                            this.monthsData  = data.months_data;
                            this.$nextTick(() => this.renderChart());
                        } finally {
                            this.isLoading    = false;
                            this.isInitialLoad = false;
                        }
                    },

                    renderChart() {
                        const canvasId = 'revenue-month-chart-' + this.$.uid;
                        const canvas = document.getElementById(canvasId);
                        if (! canvas) return;

                        if (this._chart) {
                            this._chart.data.labels   = this.months.map(m => m.label);
                            this._chart.data.datasets = JSON.parse(JSON.stringify(this.datasets));
                            this._chart.update('none');
                            return;
                        }

                        const self = this;

                        this._chart = new Chart(canvas, {
                            type: 'bar',
                            data: {
                                labels:   this.months.map(m => m.label),
                                datasets: JSON.parse(JSON.stringify(this.datasets)),
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'bottom',
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label:  (ctx) => ' ' + ctx.dataset.label + ': ' + self.formatCurrency(ctx.parsed.y),
                                            footer: (items) => {
                                                const total = items.reduce((sum, item) => sum + item.parsed.y, 0);
                                                return 'Totaal: ' + self.formatCurrency(total);
                                            },
                                        },
                                    },
                                },
                                scales: {
                                    x: {
                                        stacked: true,
                                        beginAtZero: true,
                                        border: { dash: [8, 4] },
                                    },
                                    y: {
                                        stacked: true,
                                        beginAtZero: true,
                                        border: { dash: [8, 4] },
                                        ticks: {
                                            callback: (value) => '€' + Number(value).toLocaleString('nl-NL'),
                                        },
                                    },
                                },
                            },
                        });
                    },

                    onPeriodChange() {
                        if (this.from > this.to) {
                            this.to = this.from;
                        }
                        this.updateUrl();
                        this.loadData();
                    },

                    updateUrl() {
                        const url = new URL(window.location.href);
                        url.searchParams.set('from', this.from);
                        url.searchParams.set('to', this.to);
                        history.pushState({}, '', url.toString());
                    },

                    formatCurrency(value) {
                        return '€ ' + Number(value || 0).toLocaleString('nl-NL', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
