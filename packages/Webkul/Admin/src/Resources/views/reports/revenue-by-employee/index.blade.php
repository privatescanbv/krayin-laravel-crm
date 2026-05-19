<x-admin::layouts>
    <x-slot:title>
        Omzet per medewerker
    </x-slot>

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                Omzet per medewerker
            </p>
        </div>
    </div>

    <v-revenue-report>
        <div class="light-shimmer-bg dark:shimmer h-96 w-full rounded-lg"></div>
    </v-revenue-report>

    @pushOnce('scripts')
        <script
            type="module"
            src="{{ vite()->asset('js/chart.js') }}"
        >
        </script>

        <script
            type="text/x-template"
            id="v-revenue-report-template"
        >
            <div>
                <template v-if="isInitialLoad && isLoading">
                    <div class="light-shimmer-bg dark:shimmer h-96 w-full rounded-lg"></div>
                </template>

                <div
                    v-show="!(isInitialLoad && isLoading)"
                    class="flex flex-col gap-4"
                >
                    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-3">
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

                            <div class="flex flex-wrap items-center gap-2">
                                <div
                                    class="relative"
                                    v-click-outside="() => stageDropdownOpen = false"
                                >
                                    <button
                                        type="button"
                                        class="flex min-h-9 min-w-[220px] items-center justify-between gap-2 rounded-md border px-3 py-2 text-left text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        @click="stageDropdownOpen = ! stageDropdownOpen"
                                    >
                                        <span>@{{ selectedStageText }}</span>
                                        <span class="icon-down-arrow text-xs"></span>
                                    </button>

                                    <div
                                        v-if="stageDropdownOpen"
                                        class="absolute right-0 z-20 mt-1 max-h-72 w-80 overflow-auto rounded-md border bg-white p-2 shadow dark:border-gray-800 dark:bg-gray-900"
                                    >
                                        <label
                                            v-for="stage in stages"
                                            :key="stage.id"
                                            class="flex cursor-pointer items-start gap-2 rounded px-2 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
                                        >
                                            <input
                                                type="checkbox"
                                                class="mt-0.5 h-4 w-4 shrink-0 accent-[#8979FF]"
                                                :value="stage.id"
                                                v-model="selectedStages"
                                                @change="loadData"
                                            />

                                            <span class="flex flex-1 items-center justify-between gap-2 text-gray-700 dark:text-gray-300">
                                                <span>@{{ stage.label }}</span>
                                                <span class="rounded border px-1.5 py-0.5 text-[11px] uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                                    @{{ departmentLabel(stage.department) }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

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
                                        class="absolute right-0 z-20 mt-1 w-60 rounded-md border bg-white p-2 shadow dark:border-gray-800 dark:bg-gray-900"
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

                    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="relative min-h-[400px]">
                            <canvas
                                :id="'revenue-chart-' + $.uid"
                                class="max-h-[400px] w-full"
                            ></canvas>

                            <div
                                v-if="! isLoading && ! employees.length"
                                class="absolute inset-0 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                Geen omzet gevonden voor deze selectie.
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b px-4 py-3 dark:border-gray-800">
                            <p class="text-base font-semibold text-gray-800 dark:text-gray-300">
                                Medewerkers
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b text-xs font-medium uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        <th class="px-4 py-3">Medewerker</th>
                                        <th class="px-4 py-3 text-right">Inkoop</th>
                                        <th class="px-4 py-3 text-right">Weekomzet</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <template
                                        v-for="employee in employees"
                                        :key="employee.user_id"
                                    >
                                        <tr
                                            class="cursor-pointer border-b dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                            @click="toggleEmployee(employee.user_id)"
                                        >
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        class="h-3 w-3 flex-shrink-0 rounded-sm"
                                                        :style="{ backgroundColor: employee.color }"
                                                    ></span>
                                                    <span class="font-medium">@{{ employee.name }}</span>
                                                    <span class="text-xs text-gray-400">(@{{ employee.orders.length }})</span>
                                                    <span
                                                        class="ml-auto text-xs text-gray-400"
                                                        :class="isExpanded(employee.user_id) ? 'icon-up-arrow' : 'icon-down-arrow'"
                                                    ></span>
                                                </div>
                                            </td>

                                            <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                                @{{ formatCurrency(employee.week_inkoop) }}
                                            </td>

                                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-800 dark:text-white">
                                                @{{ formatCurrency(employee.week_total) }}
                                            </td>
                                        </tr>

                                        <template v-if="isExpanded(employee.user_id)">
                                            <tr
                                                v-for="order in employee.orders"
                                                :key="order.id"
                                                class="border-b bg-gray-50 dark:border-gray-800 dark:bg-gray-800/30"
                                            >
                                                <td class="py-2 pl-10 pr-4 text-sm">
                                                    <div class="flex flex-col gap-0.5">
                                                        <a
                                                            :href="order.url"
                                                            class="font-medium text-brandColor hover:underline"
                                                            @click.stop
                                                        >@{{ order.label }}</a>

                                                        <div class="flex items-center gap-2 text-xs text-gray-400">
                                                            <span>@{{ order.created_at }}</span>
                                                            <span>·</span>
                                                            <span>@{{ order.stage }}</span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="py-2 pl-4 pr-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                                    @{{ formatCurrency(order.inkoop_price) }}
                                                </td>

                                                <td class="py-2 pl-4 pr-4 text-right text-sm text-gray-500 dark:text-gray-400">
                                                    @{{ formatCurrency(order.total_price) }}
                                                </td>
                                            </tr>
                                        </template>
                                    </template>

                                    <tr v-if="! employees.length">
                                        <td
                                            colspan="3"
                                            class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            Geen medewerkers gevonden.
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
            app.component('v-revenue-report', {
                template: '#v-revenue-report-template',

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
                        week: {{ $initialWeek }},
                        year: {{ $initialYear }},
                        weekLabel: '',
                        stages: [],
                        departments: [],
                        selectedStages: [],
                        selectedDepartments: ['privatescan', 'hernia'],
                        days: [],
                        datasets: [],
                        employees: [],
                        expandedEmployees: [],
                        isInitialLoad: true,
                        isLoading: true,
                        stageDropdownOpen: false,
                        departmentDropdownOpen: false,
                    }
                },

                computed: {
                    selectedStageText() {
                        if (! this.selectedStages.length) {
                            return 'Geen statussen';
                        }

                        if (this.selectedStages.length === this.stages.length) {
                            return 'Alle statussen';
                        }

                        return `${this.selectedStages.length} statussen`;
                    },

                    selectedDepartmentText() {
                        if (! this.selectedDepartments.length) {
                            return 'Geen afdelingen';
                        }

                        if (this.selectedDepartments.length === this.departments.length) {
                            return 'Alle afdelingen';
                        }

                        return this.departments
                            .filter(department => this.selectedDepartments.includes(department.id))
                            .map(department => department.label)
                            .join(', ');
                    },
                },

                mounted() {
                    this._chart = null; // kept outside data() so Vue never wraps the Chart instance in a Proxy
                    this.loadFilterOptions();
                },

                beforeUnmount() {
                    if (this._chart) {
                        this._chart.destroy();
                        this._chart = null;
                    }
                },

                methods: {
                    loadFilterOptions() {
                        this.$axios.get("{{ route('admin.reports.revenue-by-employee.filter-options') }}")
                            .then(response => {
                                this.stages = response.data.stages || [];
                                this.departments = response.data.departments || [];
                                this.selectedStages = this.stages
                                    .filter(stage => ! stage.is_lost)
                                    .map(stage => stage.id);

                                this.loadData();
                            });
                    },

                    async loadData() {
                        this.isLoading = true;
                        try {
                            const response = await this.$axios.get("{{ route('admin.reports.revenue-by-employee.data') }}", {
                                params: {
                                    week: this.week,
                                    year: this.year,
                                    stages: this.selectedStages,
                                    departments: this.selectedDepartments,
                                }
                            });
                            const data = response.data;
                            this.weekLabel = data.week_label;
                            this.days = data.days;
                            this.datasets = data.datasets;
                            this.employees = data.employees;
                            this.$nextTick(() => this.renderChart());
                        } finally {
                            this.isLoading = false;
                            this.isInitialLoad = false;
                        }
                    },

                    renderChart() {
                        const canvasId = 'revenue-chart-' + this.$.uid;
                        const canvas = document.getElementById(canvasId);
                        if (!canvas) return;

                        if (this._chart) {
                            // JSON round-trip strips Vue reactivity before handing data to Chart.js.
                            this._chart.data.labels = this.days.map(d => d.label);
                            this._chart.data.datasets = JSON.parse(JSON.stringify(this.datasets));
                            this._chart.update('none');
                            return;
                        }

                        const weekendIndices = [5, 6]; // Mon-anchored week: Sat=5, Sun=6
                        const self = this;

                        this._chart = new Chart(canvas, {
                            type: 'bar',
                            data: {
                                labels: this.days.map(d => d.label),
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
                                            label: (ctx) => ' ' + ctx.dataset.label + ': ' + self.formatCurrency(ctx.parsed.y),
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
                                        ticks: {
                                            color: (ctx) => weekendIndices.includes(ctx.index) ? '#9CA3AF' : '#374151',
                                            font: (ctx) => ({ size: weekendIndices.includes(ctx.index) ? 10 : 12 }),
                                        },
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
                        const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
                        const dayNumber = target.getUTCDay() || 7;

                        target.setUTCDate(target.getUTCDate() + 4 - dayNumber);

                        const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));
                        const week = Math.ceil((((target - yearStart) / 86400000) + 1) / 7);

                        return {
                            week,
                            year: target.getUTCFullYear(),
                        };
                    },

                    isoWeekDate(year, week) {
                        const simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
                        const dayOfWeek = simple.getUTCDay();
                        const isoWeekStart = simple;

                        if (dayOfWeek <= 4) {
                            isoWeekStart.setUTCDate(simple.getUTCDate() - simple.getUTCDay() + 1);
                        } else {
                            isoWeekStart.setUTCDate(simple.getUTCDate() + 8 - simple.getUTCDay());
                        }

                        return new Date(isoWeekStart.getUTCFullYear(), isoWeekStart.getUTCMonth(), isoWeekStart.getUTCDate());
                    },

                    toggleEmployee(userId) {
                        const idx = this.expandedEmployees.indexOf(userId);

                        if (idx === -1) {
                            this.expandedEmployees.push(userId);
                        } else {
                            this.expandedEmployees.splice(idx, 1);
                        }
                    },

                    isExpanded(userId) {
                        return this.expandedEmployees.includes(userId);
                    },

                    formatCurrency(value) {
                        return '€ ' + Number(value || 0).toLocaleString('nl-NL', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    },

                    departmentLabel(departmentId) {
                        return this.departments.find(department => department.id === departmentId)?.label || departmentId;
                    },
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
