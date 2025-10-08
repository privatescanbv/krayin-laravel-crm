<v-product-lookup {{ $attributes }}></v-product-lookup>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-product-lookup-template"
    >
        <div class="relative" ref="lookup">
            <x-admin::form.control-group.label v-if="label">
                @{{ label }}
            </x-admin::form.control-group.label>

            <!-- Selected item displayed above (chip-style) -->
            <div class="mb-2" v-if="selectedItem && selectedItem.id">
                <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                    <span>@{{ displayLabel }}</span>
                    <i
                        class="icon-cross-large cursor-pointer text-lg text-gray-600 dark:text-gray-300"
                        @click="clearSelection"
                    ></i>
                </div>
            </div>

            <!-- Trigger/input to open popup -->
            <div class="relative inline-block w-full" @click="toggle">
                <div class="relative flex cursor-pointer items-center justify-between rounded border border-gray-200 p-2 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:text-gray-300">
                    <span class="overflow-hidden text-ellipsis text-gray-500 dark:text-gray-300">
                        @{{ placeholder || "Select product" }}
                    </span>

                    <div class="flex items-center gap-2">
                        <i class="text-2xl text-gray-600" :class="showPopup ? 'icon-up-arrow' : 'icon-down-arrow'"></i>
                    </div>
                </div>
            </div>

            <!-- Hidden input for the selected id -->
            <input type="hidden" :name="name" :value="selectedItem?.id || ''" />

            <!-- Popup -->
            <div
                v-if="showPopup"
                class="absolute top-full z-10 mt-1 flex w-full origin-top transform flex-col gap-2 rounded-lg border border-gray-200 bg-white p-2 shadow-lg transition-transform dark:border-gray-900 dark:bg-gray-800"
            >
                <!-- Search bar -->
                <div class="relative flex items-center">
                    <input
                        type="text"
                        v-model.lazy="searchTerm"
                        v-debounce="500"
                        class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                        :placeholder="placeholder"
                        ref="searchInput"
                        @keyup="search"
                    />

                    <span class="absolute flex items-center ltr:right-2 rtl:left-2">
                        <div class="relative" v-if="isSearching">
                            <x-admin::spinner />
                        </div>
                    </span>
                </div>

                <!-- Results -->
                <ul class="max-h-40 divide-y divide-gray-100 overflow-y-auto">
                    <li
                        v-if="isSearching && searchTerm.length > 2"
                        class="flex items-center gap-2 px-4 py-2 text-gray-500"
                    >
                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                        @lang('admin::app.components.lookup.searching')
                    </li>

                    <li
                        v-for="item in filteredResults"
                        :key="item.id"
                        class="cursor-pointer px-4 py-2 text-gray-800 transition-colors hover:bg-blue-100 dark:text-white dark:hover:bg-gray-900"
                        @click="selectItem(item)"
                    >
                        @{{ item.name }}
                    </li>

                    <template v-if="filteredResults.length === 0 && !isSearching && searchTerm.length > 2">
                        <li class="px-4 py-2 text-gray-500">
                            @lang('admin::app.components.lookup.no-results')
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-product-lookup', {
            template: '#v-product-lookup-template',

            props: {
                src: { type: String, required: true },
                name: { type: String, required: true },
                placeholder: { type: String, default: '' },
                value: { type: Object, default: () => ({}) },
                params: { type: Object, default: () => ({}) },
                rules: { type: String, default: '' },
                label: { type: String, default: '' },
                canAddNew: { type: Boolean, default: false },
            },

            emits: ['on-selected'],

            data() {
                return {
                    showPopup: false,
                    searchTerm: '',
                    searchedResults: [],
                    isSearching: false,
                    cancelToken: null,
                    selectedItem: {},
                };
            },

            mounted() {
                if (this.value && this.value.id) {
                    this.selectedItem = this.value;

                    // If name is missing, try to fetch by id from src using query param.
                    if (!this.selectedItem.name) {
                        this.fetchInitialById(this.value.id);
                    }
                }

                window.addEventListener('click', this.handleFocusOut);
            },

            beforeUnmount() {
                window.removeEventListener('click', this.handleFocusOut);
            },

            computed: {
                filteredResults() {
                    return this.searchedResults;
                },

                displayLabel() {
                    if (!this.selectedItem) return '';
                    return this.selectedItem.name || (this.selectedItem.id ? `#${this.selectedItem.id}` : '');
                },
            },

            methods: {
                toggle() {
                    this.showPopup = ! this.showPopup;
                    if (this.showPopup) {
                        this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
                    }
                },

                selectItem(item) {
                    this.selectedItem = item;
                    this.$emit('on-selected', item);
                    this.searchTerm = '';
                    this.searchedResults = [];
                    this.showPopup = false;
                },

                clearSelection() {
                    this.selectedItem = {};
                    this.$emit('on-selected', {});
                },

                search() {
                    if (this.searchTerm.length <= 2) {
                        this.searchedResults = [];
                        this.isSearching = false;
                        return;
                    }

                    this.isSearching = true;

                    if (this.cancelToken) {
                        this.cancelToken.cancel();
                    }

                    this.cancelToken = this.$axios.CancelToken.source();

                    const requestParams = Object.assign({}, (this.params && this.params.params) || {}, { query: this.searchTerm });

                    this.$axios.get(this.src, {
                            params: requestParams,
                            cancelToken: this.cancelToken.token,
                        })
                        .then(response => {
                            this.searchedResults = response.data.data || response.data;
                        })
                        .catch(error => {
                            if (! this.$axios.isCancel(error)) {
                                console.error('Search request failed:', error);
                            }
                            this.isSearching = false;
                        })
                        .finally(() => this.isSearching = false);
                },

                fetchInitialById(id) {
                    if (!id) return;
                    const requestParams = Object.assign({}, (this.params && this.params.params) || {}, { query: id });

                    this.$axios.get(this.src, { params: requestParams })
                        .then(response => {
                            const list = response.data?.data || response.data || [];
                            const found = Array.isArray(list) ? list.find(x => (x.id == id)) : null;
                            if (found) {
                                this.selectedItem = found;
                            }
                        })
                        .catch(() => {})
                        .finally(() => {});
                },

                handleFocusOut(event) {
                    const lookup = this.$refs.lookup;
                    if (lookup && ! lookup.contains(event.target)) {
                        this.showPopup = false;
                    }
                },
            },
        });
    </script>
@endPushOnce


