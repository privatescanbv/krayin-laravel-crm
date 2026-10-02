<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.dashboard.index.title')
    </x-slot>

    <!-- Head Details Section -->
    {!! view_render_event('admin.dashboard.index.header.before') !!}

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        {!! view_render_event('admin.dashboard.index.header.left.before') !!}

        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                @lang('admin::app.dashboard.index.title')
            </p>
        </div>

        {!! view_render_event('admin.dashboard.index.header.left.after') !!}

        <!-- Actions -->
        {!! view_render_event('admin.dashboard.index.header.right.before') !!}

        <v-dashboard-filters>
            <!-- Shimmer -->
            <div class="flex gap-1.5">
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
            </div>
        </v-dashboard-filters>

        {!! view_render_event('admin.dashboard.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.header.after') !!}

    <div class="rounded-lg border bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900">
        <p class="mb-3 text-base font-semibold dark:text-gray-300">Rapportages</p>

        <!-- Report groups: only the tiles at first; picking one slides its reports open -->
        <v-report-groups :groups="{{ json_encode($reportGroups ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ($reportGroups ?? [] as $group)
                    <div class="light-shimmer-bg dark:shimmer h-[76px] rounded-lg"></div>
                @endforeach
            </div>
        </v-report-groups>
    </div>

    <!-- Body Component -->
    {!! view_render_event('admin.dashboard.index.content.before') !!}

    <div class="mt-3.5 flex gap-4 max-xl:flex-wrap">
        <!-- Left Section -->
        {!! view_render_event('admin.dashboard.index.content.left.before') !!}

        <div class="flex flex-1 flex-col gap-4 max-xl:flex-auto">
            <!-- Total Leads Stats -->
            @include('admin::dashboard.index.total-leads')

            <div class="flex gap-4 max-lg:flex-wrap">
                <!-- Total Products -->
                @include('admin::dashboard.index.top-selling-products')

                <!-- Total Persons -->
                @include('admin::dashboard.index.top-persons')
            </div>
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}

        <!-- Right Section -->
        {!! view_render_event('admin.dashboard.index.content.right.before') !!}

        <div class="flex w-[378px] max-w-full flex-col gap-4 max-sm:w-full">
            <!-- Patient Portal Active Sessions -->
            @include('admin::dashboard.index.patient-portal-sessions')

            <!-- Revenue by Types -->
            @include('admin::dashboard.index.open-leads-by-states')

            <!-- Revenue by Sources -->
            @include('admin::dashboard.index.revenue-by-sources')

            <!-- Revenue by Types -->
            @include('admin::dashboard.index.revenue-by-types')
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.content.after') !!}

    @pushOnce('scripts')

        <script
            type="module"
            src="{{ vite()->asset('js/chart.js') }}"
        >
        </script>

        <script
            type="module"
            src="https://cdn.jsdelivr.net/npm/chartjs-chart-funnel@4.2.1/build/index.umd.min.js"
        >
        </script>

        <script
            type="text/x-template"
            id="v-dashboard-filters-template"
        >
            {!! view_render_event('admin.dashboard.index.date_filters.before') !!}

            <div class="flex gap-1.5">
                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.start"
                        placeholder="@lang('admin::app.dashboard.index.start-date')"
                    />
                </x-admin::flat-picker.date>

                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.end"
                        placeholder="@lang('admin::app.dashboard.index.end-date')"
                    />
                </x-admin::flat-picker.date>
            </div>

            {!! view_render_event('admin.dashboard.index.date_filters.after') !!}
        </script>

        <script
            type="text/x-template"
            id="v-report-groups-template"
        >
            <div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <button
                        v-for="group in groups"
                        :key="group.name"
                        type="button"
                        class="group flex items-center gap-3 rounded-lg border p-3 text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800"
                        :class="active === group.name
                            ? 'border-brandColor bg-brandColor/5 shadow-md dark:border-gray-400 dark:bg-gray-800'
                            : 'border-gray-200 bg-white hover:border-gray-300 dark:bg-gray-900 dark:hover:border-gray-600'"
                        :aria-expanded="active === group.name"
                        aria-controls="report-group-panel"
                        @click="toggle(group.name)"
                    >
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xl transition-colors duration-200"
                            :class="[group.icon, active === group.name
                                ? 'bg-brandColor text-white'
                                : 'bg-gray-100 text-brandColor group-hover:bg-brandColor/10 dark:bg-gray-800 dark:text-gray-300']"
                        ></span>

                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate font-semibold text-gray-800 dark:text-white">@{{ group.name }}</span>

                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                @{{ group.reports.length }} @{{ group.reports.length === 1 ? 'rapport' : 'rapporten' }}
                            </span>
                        </span>

                        <span
                            class="icon-down-arrow text-xs text-gray-400 transition-transform duration-300"
                            :class="{ 'rotate-180 text-brandColor dark:text-gray-200': active === group.name }"
                        ></span>
                    </button>
                </div>

                <!-- Panel: grid-rows 0fr -> 1fr animates to the content height without measuring it -->
                <div
                    id="report-group-panel"
                    class="grid transition-all duration-300 ease-out"
                    :class="activeGroup ? 'mt-3 grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                >
                    <div class="overflow-hidden">
                        <transition
                            mode="out-in"
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="translate-y-1 opacity-0"
                            leave-active-class="transition duration-150 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <ul
                                v-if="activeGroup"
                                :key="activeGroup.name"
                                class="grid gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-2 lg:grid-cols-3 dark:bg-gray-800/50"
                            >
                                <li v-for="report in activeGroup.reports" :key="report.url">
                                    <a
                                        :href="report.url"
                                        class="group/report flex items-center gap-2 rounded-md border border-transparent bg-white px-3 py-2.5 text-sm text-gray-700 transition-all duration-150 hover:border-brandColor/30 hover:text-brandColor hover:shadow-sm dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:text-white"
                                    >
                                        <span class="icon-stats text-base text-brandColor dark:text-gray-400"></span>

                                        <span class="flex-1">@{{ report.name }}</span>

                                        <span class="icon-right-arrow text-xs opacity-0 transition-all duration-150 group-hover/report:translate-x-0.5 group-hover/report:opacity-100"></span>
                                    </a>
                                </li>
                            </ul>
                        </transition>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-report-groups', {
                template: '#v-report-groups-template',

                props: {
                    groups: { type: Array, default: () => [] },
                },

                data() {
                    return {
                        active: null,
                    };
                },

                computed: {
                    activeGroup() {
                        return this.groups.find(group => group.name === this.active) ?? null;
                    },
                },

                methods: {
                    toggle(name) {
                        this.active = this.active === name ? null : name;
                    },
                },
            });
        </script>

        <script type="module">
            app.component('v-dashboard-filters', {
                template: '#v-dashboard-filters-template',

                data() {
                    return {
                        filters: {
                            channel: '',

                            start: "{{ $startDate->format('Y-m-d') }}",

                            end: "{{ $endDate->format('Y-m-d') }}",
                        }
                    }
                },

                mounted() {
                    // Component initialized
                },

                watch: {
                    filters: {
                        handler() {
                            this.$emitter.emit('reporting-filter-updated', this.filters);
                        },

                        deep: true
                    }
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
