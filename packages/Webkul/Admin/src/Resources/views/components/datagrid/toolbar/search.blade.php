<v-datagrid-search
    :is-loading="isLoading"
    :available="available"
    :applied="applied"
    :src="src"
    @search="search"
    @filter="filter"
    @applySavedFilter="applySavedFilter"
    @resetAll="resetAll"
>
    {{ $slot }}
</v-datagrid-search>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-datagrid-search-template"
    >
        <slot
            name="search"
            :available="available"
            :applied="applied"
            :search="search"
            :get-searched-values="getSearchedValues"
        >
            <template v-if="isLoading">
                <x-admin::shimmer.datagrid.toolbar.search />
            </template>

            <template v-else>
                <div class="flex w-full items-center gap-x-1.5">
                    <!-- Search Panel -->
                    <div class="flex max-w-[445px] items-center max-sm:w-full max-sm:max-w-full">
                        <div class="relative w-full">
                            <div class="icon-search absolute top-1.5 flex items-center text-2xl ltr:left-3 rtl:right-3"></div>

                            <input
                                type="text"
                                name="search"
                                :value="getSearchedValues('all')"
                                class="block w-full rounded-lg border bg-white py-1.5 leading-6 text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400 ltr:pl-10 ltr:pr-3 rtl:pl-3 rtl:pr-10"
                                placeholder="@lang('admin::app.components.datagrid.toolbar.search.title')"
                                autocomplete="off"
                                @keyup.enter="search"
                            >
                        </div>
                    </div>

                    <!-- Filter Panel -->
                    <x-admin::datagrid.toolbar.filter>
                        <template #filter="{
                            available,
                            applied,
                            filters,
                            applyFilter,
                            applyColumnValues,
                            findAppliedColumn,
                            hasAnyAppliedColumnValues,
                            getAppliedColumnValues,
                            removeAppliedColumnValue,
                            removeAppliedColumnAllValues
                        }">
                            <slot
                                name="filter"
                                :available="available"
                                :applied="applied"
                                :filters="filters"
                                :apply-filter="applyFilter"
                                :apply-column-values="applyColumnValues"
                                :find-applied-column="findAppliedColumn"
                                :has-any-applied-column-values="hasAnyAppliedColumnValues"
                                :get-applied-column-values="getAppliedColumnValues"
                                :remove-applied-column-value="removeAppliedColumnValue"
                                :remove-applied-column-all-values="removeAppliedColumnAllValues"
                            >
                            </slot>
                        </template>
                    </x-admin::datagrid.toolbar.filter>

                    <!-- Reset Datagrid Preferences -->
                    <button
                        type="button"
                        class="relative flex cursor-pointer items-center rounded-md bg-neutral-bg px-4 py-[9px] font-semibold text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        @click="resetDatagridState"
                        :title="'Reset filters & preferences'"
                        aria-label="Reset filters & preferences"
                    >
                        <span class="icon-cross-large text-xl"></span>
                    </button>
                </div>
            </template>
        </slot>
    </script>

    <script type="module">
        app.component('v-datagrid-search', {
            template: '#v-datagrid-search-template',

            props: ['isLoading', 'available', 'applied', 'src'],

            emits: ['search', 'filter', 'applySavedFilter', 'resetAll'],

            data() {
                return {
                    filters: {
                        columns: [],
                    },
                };
            },

            mounted() {
                this.filters.columns = this.applied.filters.columns.filter((column) => column.index === 'all');
            },

            methods: {
                /**
                 * Perform a search operation based on the input value.
                 *
                 * @param {Event} $event
                 * @returns {void}
                 */
                search($event) {
                    let requestedValue = $event.target.value;

                    let appliedColumn = this.filters.columns.find(column => column.index === 'all');

                    if (! requestedValue) {
                        appliedColumn.value = [];

                        this.$emit('search', this.filters);

                        return;
                    }

                    if (appliedColumn) {
                        appliedColumn.value = [requestedValue];
                    } else {
                        this.filters.columns.push({
                            index: 'all',
                            value: [requestedValue]
                        });
                    }

                    this.$emit('search', this.filters);
                },

                filter(filter) {
                    this.$emit('filter', filter);
                },

                applySavedFilter(filter) {
                    this.$emit('applySavedFilter', filter);
                },

                /**
                 * Get the searched values for a specific column.
                 *
                 * @param {string} columnIndex
                 * @returns {Array}
                 */
                getSearchedValues(columnIndex) {
                    let appliedColumn = this.filters.columns.find(column => column.index === 'all');

                    return appliedColumn?.value ?? [];
                },

                /**
                 * Clear stored datagrid preferences for the current grid and reset filters/search.
                 *
                 * @returns {void}
                 */
                resetDatagridState() {
                    try {
                        const storageKey = 'datagrids';
                        const raw = localStorage.getItem(storageKey);
                        let datagrids = [];

                        try {
                            datagrids = JSON.parse(raw) ?? [];
                        } catch (e) {
                            datagrids = [];
                        }

                        // Remove only the current datagrid entry by src
                        const remaining = datagrids.filter(dg => dg?.src !== this.src);

                        if (remaining.length) {
                            localStorage.setItem(storageKey, JSON.stringify(remaining));
                        } else {
                            localStorage.removeItem(storageKey);
                        }
                    } catch (e) {
                        // no-op
                    }

                    // Clear local search input state
                    const searchColumn = this.filters.columns.find(column => column.index === 'all');
                    if (searchColumn) {
                        searchColumn.value = [];
                    }

                    // Emit empty filters to parent to refresh immediately
                    this.$emit('filter', { columns: [] });

                    // Also clean filter-related URL params for a consistent reset
                    const url = new URL(window.location.href);
                    // Remove search param
                    url.searchParams.delete('search');
                    // Remove any filters[...] params
                    [...url.searchParams.keys()].forEach((key) => {
                        if (/^filters\[[^\]]+\](?:\[\d+\])?$/.test(key)) {
                            url.searchParams.delete(key);
                        }
                    });
                    // Soft reload without adding a new history entry
                    window.history.replaceState({}, '', url.toString());

                    // Notify parent to reset sort, pagination, and applied state
                    this.$emit('resetAll');
                },
            },
        });
    </script>
@endPushOnce
