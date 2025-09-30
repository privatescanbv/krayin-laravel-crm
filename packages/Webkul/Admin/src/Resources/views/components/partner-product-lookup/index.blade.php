@props([
    'src' => '',
    'name' => '',
    'label' => 'Related Products',
    'searchPlaceholder' => 'Search products...',
    'value' => [],
    'excludeId' => null,
])

<v-partner-product-lookup
    src="{{ $src }}"
    name="{{ $name }}"
    label="{{ $label }}"
    search-placeholder="{{ $searchPlaceholder }}"
    :value='@json($value)'
    :exclude-id="{{ $excludeId }}"
></v-partner-product-lookup>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-partner-product-lookup-template"
    >
        <div class="relative">
            <x-admin::form.control-group.label>
                @{{ label }}
            </x-admin::form.control-group.label>

            <!-- Selected Items Display -->
            <div class="mb-2 flex flex-wrap gap-2">
                <div
                    v-for="(item, index) in selectedItems"
                    :key="item.id"
                    class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 dark:border-gray-800 dark:bg-gray-900"
                >
                    <span class="text-sm dark:text-white">@{{ item.name }}</span>
                    <i
                        class="icon-cross-large cursor-pointer text-lg text-gray-600 dark:text-gray-300"
                        @click="removeItem(index)"
                    ></i>
                </div>
            </div>

            <!-- Search Input -->
            <div
                class="relative"
                ref="lookup"
            >
                <div
                    class="relative inline-block w-full"
                    @click="toggle"
                >
                    <div class="relative flex cursor-pointer items-center justify-between rounded border border-gray-200 p-2 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:text-gray-300">
                        <span class="overflow-hidden text-ellipsis">
                            @lang('admin::app.components.lookup.click-to-add')
                        </span>

                        <div class="flex items-center gap-2">
                            <i
                                class="text-2xl text-gray-600"
                                :class="showPopup ? 'icon-up-arrow' : 'icon-down-arrow'"
                            ></i>
                        </div>
                    </div>
                </div>

                <!-- Hidden Inputs for Selected Items -->
                <input
                    v-for="item in selectedItems"
                    :key="item.id"
                    type="hidden"
                    :name="name + '[]'"
                    :value="item.id"
                />

                <!-- Popup Box -->
                <div
                    v-if="showPopup"
                    class="absolute top-full z-10 mt-1 flex w-full origin-top transform flex-col gap-2 rounded-lg border border-gray-200 bg-white p-2 shadow-lg transition-transform dark:border-gray-900 dark:bg-gray-800"
                >
                    <!-- Search Bar -->
                    <div class="relative flex items-center">
                        <input
                            type="text"
                            v-model.lazy="searchTerm"
                            v-debounce="500"
                            class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                            :placeholder="searchPlaceholder"
                            ref="searchInput"
                            @keyup="search"
                        />

                        <span class="absolute flex items-center ltr:right-2 rtl:left-2">
                            <div
                                class="relative"
                                v-if="isSearching"
                            >
                                 <x-admin::spinner />
                            </div>
                        </span>
                    </div>

                    <!-- Results List -->
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
        </div>
    </script>

    <script type="module">
        app.component('v-partner-product-lookup', {
            template: '#v-partner-product-lookup-template',

            props: {
                src: {
                    type: String,
                    required: true,
                },

                name: {
                    type: String,
                    required: true,
                },

                label: {
                    type: String,
                    default: 'Related Products',
                },

                searchPlaceholder: {
                    type: String,
                    default: 'Search products...',
                },

                value: {
                    type: Array,
                    default: () => [],
                },

                excludeId: {
                    type: [Number, String],
                    default: null,
                },
            },

            data() {
                return {
                    showPopup: false,
                    searchTerm: '',
                    selectedItems: [],
                    searchedResults: [],
                    isSearching: false,
                    cancelToken: null,
                };
            },

            mounted() {
                if (this.value && Array.isArray(this.value)) {
                    this.selectedItems = [...this.value];
                }
            },

            created() {
                window.addEventListener('click', this.handleFocusOut);
            },

            beforeDestroy() {
                window.removeEventListener('click', this.handleFocusOut);
            },

            watch: {
                searchTerm(newVal, oldVal) {
                    this.search();
                },
            },

            computed: {
                filteredResults() {
                    // Filter out already selected items and the current product itself
                    return this.searchedResults.filter(item => {
                        const isNotSelected = !this.selectedItems.some(selected => selected.id == item.id);
                        const isNotCurrentProduct = this.excludeId ? item.id != this.excludeId : true;
                        return isNotSelected && isNotCurrentProduct;
                    });
                }
            },

            methods: {
                toggle() {
                    this.showPopup = ! this.showPopup;

                    if (this.showPopup) {
                        this.$nextTick(() => this.$refs.searchInput.focus());
                    }
                },

                selectItem(item) {
                    if (!this.selectedItems.some(selected => selected.id === item.id)) {
                        this.selectedItems.push(item);
                    }

                    this.searchTerm = '';
                    this.searchedResults = [];
                    this.showPopup = false;
                },

                removeItem(index) {
                    this.selectedItems.splice(index, 1);
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

                    this.$axios.get(this.src, {
                            params: {
                                query: this.searchTerm
                            },
                            cancelToken: this.cancelToken.token,
                        })
                        .then(response => {
                            this.searchedResults = response.data.data || response.data;
                        })
                        .catch(error => {
                            if (! this.$axios.isCancel(error)) {
                                console.error("Search request failed:", error);
                            }

                            this.isSearching = false;
                        })
                        .finally(() => this.isSearching = false);
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