{!! view_render_event('admin.sales-leads.index.kanban.filter.before') !!}

<v-sales-leads-kanban-filter
    :is-loading="isLoading"
    :available="available"
    :applied="applied"
    @applyFilters="filter"
>
</v-sales-leads-kanban-filter>

{!! view_render_event('admin.sales-leads.index.kanban.filter.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-sales-leads-kanban-filter-template"
    >
        {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.before') !!}

        <x-admin::drawer
            width="350px"
            ref="kanbanFilterDrawer"
        >
            <!-- Drawer Toggler -->
            <x-slot:toggle>
                {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.toggle_button.before') !!}

                <div class="relative flex cursor-pointer items-center rounded-md bg-sky-100 px-4 py-[9px] font-semibold text-sky-600 dark:bg-brandColor dark:text-white">
                    Filters

                    <span
                        class="absolute right-2 top-2 h-1.5 w-1.5 rounded-full bg-sky-600 dark:bg-white"
                        v-if="hasAnyAppliedColumn()"
                    >
                    </span>
                </div>

                {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.toggle_button.after') !!}
            </x-slot>

            <!-- Drawer Header -->
            <x-slot:header class="p-3.5">
                {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.header.title.before') !!}

                <div class="grid gap-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xl font-semibold dark:text-white">
                            Filters
                        </p>
                    </div>
                </div>

                {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.header.title.after') !!}
            </x-slot>

            <!-- Drawer Content -->
            <x-slot:content>
                {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.content.before') !!}

                <div>
                    <div v-for="column in available.columns">
                        <div v-if="column.filterable">
                            <!-- Order Status Filter -->
                            <div v-if="column.index === 'order_status'">
                                <div class="flex items-center justify-between">
                                    <p
                                        class="text-xs font-medium text-gray-800 dark:text-white"
                                        v-text="column.label"
                                    >
                                    </p>

                                    <div
                                        class="flex items-center gap-x-1.5"
                                        @click="removeAppliedColumnAllValues(column.index)"
                                    >
                                        <p
                                            class="cursor-pointer text-xs font-medium leading-6 text-brandColor"
                                            v-if="hasAnyAppliedColumnValues(column.index)"
                                        >
                                            Wis alles
                                        </p>
                                    </div>
                                </div>

                                <div class="mb-2 mt-1.5">
                                    <x-admin::dropdown>
                                        <x-slot:toggle>
                                            <button
                                                type="button"
                                                class="inline-flex w-full cursor-pointer appearance-none items-center justify-between gap-x-2 rounded-md border bg-white px-2.5 py-1.5 text-center leading-6 text-gray-600 transition-all marker:shadow hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                            >
                                                <span
                                                    class="text-sm text-gray-400 dark:text-gray-400"
                                                    v-text="getAppliedColumnValues(column.index) || 'Selecteer status...'"
                                                >
                                                </span>

                                                <span class="icon-down-arrow text-2xl"></span>
                                            </button>
                                        </x-slot>

                                        <x-slot:menu>
                                            <x-admin::dropdown.menu.item
                                                v-for="status in ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled']"
                                                v-text="status"
                                                @click="addFilter(status, column)"
                                            >
                                            </x-admin::dropdown.menu.item>
                                        </x-slot>
                                    </x-admin::dropdown>
                                </div>

                                <div class="mb-4 flex flex-wrap gap-2">
                                    <p
                                        class="flex items-center rounded bg-gray-600 px-2 py-1 font-semibold text-white"
                                        v-if="getAppliedColumnValues(column.index)"
                                    >
                                        <span v-text="getAppliedColumnValues(column.index)"></span>

                                        <span
                                            class="icon-cross-large cursor-pointer text-lg text-white ltr:ml-1.5 rtl:mr-1.5"
                                            @click="removeAppliedColumnValue(column.index)"
                                        >
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <!-- Other filters can be added here -->
                        </div>
                    </div>
                </div>

                {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.content.after') !!}
            </x-slot>

            <!-- Drawer Footer -->
            <x-slot:footer class="!pb-3">
                {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.footer.before') !!}

                <div class="flex justify-end gap-2 px-2 pt-3">
                    <!-- Apply Filter Button -->
                    <button
                        type="button"
                        class="primary-button"
                        @click="applyFilters"
                    >
                        Filters toepassen
                    </button>
                </div>

                {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.footer.after') !!}
            </x-slot>
        </x-admin::drawer>

        {!! view_render_event('admin.sales-leads.index.kanban.filter.drawer.after') !!}
    </script>

    <script type="module">
        app.component('v-sales-leads-kanban-filter', {
            template: '#v-sales-leads-kanban-filter-template',

            props: ['isLoading', 'available', 'applied'],

            emits: ['applyFilters'],

            data() {
                return {
                    filters: {
                        columns: [],
                    },
                };
            },

            mounted() {
                this.filters.columns = this.getAppliedColumns();
            },

            methods: {
                /**
                 * Get applied columns.
                 *
                 * @returns {object}
                 */
                getAppliedColumns() {
                    return this.applied.filters.columns.filter((column) => column.index !== 'all');
                },

                /**
                 * Has any applied column.
                 *
                 * @returns {boolean}
                 */
                hasAnyAppliedColumn() {
                    return this.getAppliedColumns().length > 0;
                },

                /**
                 * Apply all added filters.
                 *
                 * @returns {void}
                 */
                applyFilters() {
                    this.$emit('applyFilters', this.filters);

                    this.$refs.kanbanFilterDrawer.close();
                },

                /**
                 * Add filter.
                 *
                 * @param {string} value
                 * @param {object} column
                 * @returns {void}
                 */
                addFilter(value, column) {
                    let appliedColumn = this.findAppliedColumn(column?.index);

                    if (appliedColumn) {
                        appliedColumn.value = value;
                    } else {
                        this.filters.columns.push({
                            ...column,
                            value: value,
                        });
                    }
                },

                /**
                 * Find applied column.
                 *
                 * @param {string} columnIndex
                 * @returns {object}
                 */
                findAppliedColumn(columnIndex) {
                    return this.filters.columns.find(column => column.index === columnIndex);
                },

                /**
                 * Check if any values are applied for the specified column.
                 *
                 * @param {string} columnIndex
                 * @returns {boolean}
                 */
                hasAnyAppliedColumnValues(columnIndex) {
                    let appliedColumn = this.findAppliedColumn(columnIndex);

                    if (! appliedColumn) {
                        return false;
                    }

                    return appliedColumn.value !== '';
                },

                /**
                 * Get applied values for the specified column.
                 *
                 * @param {string} columnIndex
                 * @returns {string}
                 */
                getAppliedColumnValues(columnIndex) {
                    const appliedColumn = this.findAppliedColumn(columnIndex);

                    return appliedColumn?.value ?? '';
                },

                /**
                 * Remove a specific value from the applied values of the specified column.
                 *
                 * @param {string} columnIndex
                 * @returns {void}
                 */
                removeAppliedColumnValue(columnIndex) {
                    this.filters.columns = this.filters.columns.filter(column => column.index !== columnIndex);
                },

                /**
                 * Remove all values from the applied values of the specified column.
                 *
                 * @param {string} columnIndex
                 * @returns {void}
                 */
                removeAppliedColumnAllValues(columnIndex) {
                    this.filters.columns = this.filters.columns.filter(column => column.index !== columnIndex);
                },
            },
        });
    </script>
@endPushOnce