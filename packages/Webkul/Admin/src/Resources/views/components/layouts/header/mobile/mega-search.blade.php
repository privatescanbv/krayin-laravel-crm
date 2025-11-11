<v-mobile-mega-search>
    <i class="icon-search flex items-center text-2xl"></i>
</v-mobile-mega-search>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-mobile-mega-search-template"
    >
        <div>
            <i
                class="icon-search flex items-center text-2xl"
                @click="toggleSearchInput"
                v-show="!isSearchVisible"
            ></i>

            <div
                v-show="isSearchVisible"
                class="absolute left-1/2 top-3 z-[10002] flex w-full max-w-full -translate-x-1/2 items-center px-2"
            >
                <i class="icon-search absolute top-2 flex items-center text-2xl ltr:left-4 rtl:right-4"></i>

                <input
                    type="text"
                    class="peer block w-full rounded-3xl border bg-white px-10 py-1.5 leading-6 text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                    :class="{'border-gray-400': isDropdownOpen}"
                    placeholder="@lang('admin::app.components.layouts.header.mega-search.title')"
                    v-model.lazy="searchTerm"
                    @click="searchTerm.length >= 2 ? isDropdownOpen = true : {}"
                    v-debounce="500"
                    ref="searchInput"
                >

                <i class="icon-cross-large absolute top-2 flex items-center text-2xl ltr:right-4 rtl:left-4"></i>

                <div
                    class="absolute top-10 z-10 w-full rounded-lg border bg-white shadow-[0px_0px_0px_0px_rgba(0,0,0,0.10),0px_1px_3px_0px_rgba(0,0,0,0.10),0px_5px_5px_0px_rgba(0,0,0,0.09),0px_12px_7px_0px_rgba(0,0,0,0.05),0px_22px_9px_0px_rgba(0,0,0,0.01),0px_34px_9px_0px_rgba(0,0,0,0.00)] dark:border-gray-800 dark:bg-gray-900"
                    v-if="isDropdownOpen"
                >
                    <!-- Search Tabs -->
                    <div class="flex border-b text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <div
                            class="cursor-pointer p-4 hover:bg-gray-100 dark:hover:bg-gray-950"
                            :class="{ 'border-b-2 border-brandColor': activeTab == tab.key }"
                            v-for="tab in tabs"
                            @click="activeTab = tab.key; updateSearchParams();"
                        >
                            @{{ tab.title }}
                        </div>
                    </div>

                    <!-- Searched Results -->
                    <template v-if="activeTab == 'sales'">
                        <template v-if="isLoading">
                            <x-admin::shimmer.header.mega-search.leads />
                        </template>

                        <template v-else>
                            <div class="grid max-h-[400px] overflow-y-auto">
                                <template v-for="sale in searchedResults.sales">
                                    <a
                                        :href="'{{ route('admin.sales-leads.view', ':id') }}'.replace(':id', sale.id)"
                                        class="flex cursor-pointer justify-between gap-2.5 border-b border-slate-300 p-4 last:border-b-0 hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-gray-950"
                                    >
                                        <!-- Left Information -->
                                        <div class="flex gap-2.5">
                                            <!-- Details -->
                                            <div class="grid place-content-start gap-1.5">
                                                <p class="text-base font-semibold text-gray-600 dark:text-gray-300">
                                                    @{{ sale.name }}
                                                </p>

                                                <p class="text-gray-500">
                                                    @{{ sale.updated_at }}
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                </template>
                            </div>

                            <div class="flex border-t p-3 dark:border-gray-800">
                                <template v-if="searchedResults.sales.length">
                                    <a
                                        :href="`${'{{ route('admin.sales-leads.index') }}'}?search=${encodeURIComponent(params.search)}&searchFields=${encodeURIComponent(params.searchFields)}&searchJoin=or`"
                                        class="cursor-pointer text-xs font-semibold text-brandColor transition-all hover:underline"
                                    >
                                        @{{ `@lang('admin::app.components.layouts.header.mega-search.explore-all-matching-sales')`.replace(':query', searchTerm).replace(':count', searchedResults.sales.length) }}
                                    </a>
                                </template>

                                <template v-else>
                                    <a
                                        href="{{ route('admin.sales-leads.index') }}"
                                        class="cursor-pointer text-xs font-semibold text-brandColor transition-all hover:underline"
                                    >
                                        @lang('admin::app.components.layouts.header.mega-search.explore-all-sales')
                                    </a>
                                </template>
                            </div>
                        </template>
                    </template>

                    <template v-if="activeTab == 'leads'">
                        <template v-if="isLoading">
                            <x-admin::shimmer.header.mega-search.leads />
                        </template>

                        <template v-else>
                            <div class="grid max-h-[400px] overflow-y-auto">
                                <template v-for="lead in searchedResults.leads">
                                    <a
                                        :href="'{{ route('admin.leads.view', ':id') }}'.replace(':id', lead.id)"
                                        class="flex cursor-pointer justify-between gap-2.5 border-b border-slate-300 p-4 last:border-b-0 hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-gray-950"
                                    >
                                        <!-- Left Information -->
                                        <div class="flex gap-2.5">
                                            <!-- Details -->
                                            <div class="grid place-content-start gap-1.5">
                                                <p class="text-base font-semibold text-gray-600 dark:text-gray-300">
                                                    @{{ lead.name }}
                                                </p>

                                                <div class="flex gap-2 items-center">
                                                    <p class="text-gray-500">
                                                        @{{ lead.updated_at }}
                                                    </p>
                                                    <template v-if="lead.stage && lead.stage.name">
                                                        <span class="text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                                            @{{ lead.stage.name }}
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </template>
                                
                                <!-- Show create lead button if no results and search term looks like phone number -->
                                <template v-if="!searchedResults.leads.length && isPhoneNumber(searchTerm)">
                                    <div class="flex justify-center p-4 border-b border-slate-300 dark:border-gray-800">
                                        <a
                                            :href="`{{ route('admin.leads.create') }}?phone=${encodeURIComponent(searchTerm)}`"
                                            class="px-4 py-2 bg-brandColor text-white rounded-lg hover:bg-opacity-90 transition-all text-sm font-semibold"
                                        >
                                            @lang('admin::app.components.layouts.header.mega-search.create-lead')
                                        </a>
                                    </div>
                                </template>
                            </div>

                            <div class="flex border-t p-3 dark:border-gray-800">
                                <template v-if="searchedResults.leads.length">
                                    <a
                                        :href="`${'{{ route('admin.leads.index') }}'}?search=${encodeURIComponent(params.search)}&searchFields=${encodeURIComponent(params.searchFields)}&searchJoin=or`"
                                        class="cursor-pointer text-xs font-semibold text-brandColor transition-all hover:underline"
                                    >
                                        @{{ `@lang('admin::app.components.layouts.header.mega-search.explore-all-matching-leads')`.replace(':query', searchTerm).replace(':count', searchedResults.leads.length) }}
                                    </a>
                                </template>

                                <template v-else>
                                    <a
                                        href="{{ route('admin.leads.index') }}"
                                        class="cursor-pointer text-xs font-semibold text-brandColor transition-all hover:underline"
                                    >
                                        @lang('admin::app.components.layouts.header.mega-search.explore-all-leads')
                                    </a>
                                </template>
                            </div>
                        </template>
                    </template>

                    <template v-if="activeTab == 'persons'">
                        <template v-if="isLoading">
                            <x-admin::shimmer.header.mega-search.persons />
                        </template>

                        <template v-else>
                            <div class="grid max-h-[400px] overflow-y-auto">
                                <template v-for="person in searchedResults.persons">
                                    <a
                                        :href="'{{ route('admin.contacts.persons.view', ':id') }}'.replace(':id', person.id)"
                                        class="flex cursor-pointer justify-between gap-2.5 border-b border-slate-300 p-4 last:border-b-0 hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-gray-950"
                                    >
                                        <!-- Left Information -->
                                        <div class="flex gap-2.5">
                                            <!-- Details -->
                                            <div class="grid place-content-start gap-1.5">
                                                <p class="text-base font-semibold text-gray-600 dark:text-gray-300">
                                                    @{{ person.name }}
                                                </p>

                                                <p class="text-gray-500">
                                                    @{{ person.emails.map((item) => `${item.value}(${item.label})`).join(', ') }}
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                </template>
                            </div>

                            <div class="flex border-t p-3 dark:border-gray-800">
                                <template v-if="searchedResults.persons.length">
                                    <a
                                        :href="'{{ route('admin.contacts.persons.index') }}?search=:query'.replace(':query', searchTerm)"
                                        class="cursor-pointer text-xs font-semibold text-brandColor transition-all hover:underline"
                                    >
                                        @{{ `@lang('admin::app.components.layouts.header.mega-search.explore-all-matching-contacts')`.replace(':query', searchTerm).replace(':count', searchedResults.persons.length) }}
                                    </a>
                                </template>

                                <template v-else>
                                    <a
                                        href="{{ route('admin.contacts.persons.index') }}"
                                        class="cursor-pointer text-xs font-semibold text-brandColor transition-all hover:underline"
                                    >
                                        @lang('admin::app.components.layouts.header.mega-search.explore-all-contacts')
                                    </a>
                                </template>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-mobile-mega-search', {
            template: '#v-mobile-mega-search-template',

            data() {
                return  {
                    activeTab: 'leads',

                    isSearchVisible: false,

                    isDropdownOpen: false,

                    tabs: {
                        leads: {
                            key: 'leads',
                            title: "@lang('admin::app.components.layouts.header.mega-search.tabs.leads')",
                            is_active: true,
                            endpoint: "{{ route('admin.leads.search') }}",
                            query_params: [
                                {
                                    search: 'name',
                                    searchFields: 'first_name:like;last_name:like;married_name:like',
                                },
                                {
                                    search: 'user.name',
                                    searchFields: 'user.name:like',
                                },
                                {
                                    search: 'email',
                                    searchFields: 'emails:like',
                                },
                                {
                                    search: 'phone',
                                    searchFields: 'phones:like',
                                },
                            ],
                        },

                        sales: {
                            key: 'sales',
                            title: "@lang('admin::app.components.layouts.header.mega-search.tabs.sales')",
                            is_active: false,
                            endpoint: "{{ route('admin.sales-leads.search') }}",
                            query_params: [
                                {
                                    search: 'name',
                                    searchFields: 'name:like',
                                },
                            ],
                        },

                        persons: {
                            key: 'persons',
                            title: "@lang('admin::app.components.layouts.header.mega-search.tabs.persons')",
                            is_active: false,
                            endpoint: "{{ route('admin.contacts.persons.search') }}",
                            query_params: [
                                {
                                    search: 'name',
                                    searchFields: 'first_name:like;last_name:like;married_name:like',
                                },
                                {
                                    search: 'organization.name',
                                    searchFields: 'organization.name:like',
                                },
                                {
                                    search: 'email',
                                    searchFields: 'emails:like',
                                },
                                {
                                    search: 'phone',
                                    searchFields: 'phones:like',
                                },
                            ],
                        },
                    },

                    isLoading: false,

                    searchTerm: '',

                    searchedResults: {
                        leads: [],
                        sales: [],
                        persons: []
                    },

                    params: {
                        search: '',
                        searchFields: '',
                    },
                };
            },

            watch: {
                searchTerm: 'updateSearchParams',

                activeTab: 'updateSearchParams',
            },

            created() {
                window.addEventListener('click', this.handleFocusOut);
            },

            beforeUnmount() {
                window.removeEventListener('click', this.handleFocusOut);
            },

            methods: {
                toggleSearchInput() {
                    this.isSearchVisible = ! this.isSearchVisible;
                    this.isDropdownOpen = false;

                    if (this.isSearchVisible) {
                        this.$nextTick(() => {
                            if (this.$refs.searchInput) {
                                this.$refs.searchInput.focus();
                            }
                        });
                    } else {
                        this.searchTerm = '';
                    }
                },

                search(endpoint) {
                    const url = endpoint || (this.tabs[this.activeTab] ? this.tabs[this.activeTab].endpoint : null);

                    if (this.searchTerm.length <= 1) {
                        this.searchedResults[this.activeTab] = [];

                        this.isDropdownOpen = false;

                        return;
                    }

                    this.isDropdownOpen = true;
                    this.isLoading = true;

                    if (this.params.search && url) {
                        this.$axios.get(url, {
                            params: {
                                ...this.params,
                                limit: 15,
                            },
                        })
                            .then((response) => {
                                this.searchedResults[this.activeTab] = response.data.data;
                            })
                            .catch((error) => {})
                            .finally(() => this.isLoading = false);
                    } else {
                        // nothing to search on or endpoint missing
                        this.isLoading = false;
                    }
                },

                handleFocusOut(e) {
                    if (! this.$el.contains(e.target) || e.target.classList.contains('icon-cross-large')) {
                        this.isDropdownOpen = false;

                        if (! this.isDropdownOpen) {
                            this.isSearchVisible = false;
                            this.searchTerm = '';
                        }
                    }
                },

                updateSearchParams() {
                    const newTerm = this.searchTerm;

                    this.params = {
                        search: '',
                        searchFields: '',
                    };

                    const tab = this.tabs[this.activeTab];

                    const looksLikePhone = this.isPhoneNumber(newTerm);
                    const looksLikeEmail = this.isEmail(newTerm);
                    
                    if (looksLikePhone) {
                        // For phone-like terms, restrict search to phone-only per tab to avoid AND with name
                        const digits = (newTerm || '').replace(/\D/g, '');
                        if (digits.length >= 3) {
                            if (this.activeTab === 'leads') {
                                this.params.search = `phone:${digits};`;
                                this.params.searchFields = `phones:like;`;
                            } else if (this.activeTab === 'persons') {
                                this.params.search = `phone:${digits};`;
                                this.params.searchFields = `phones:like;`;
                            } else if (this.activeTab === 'sales') {
                                // skip for now, later maybe search on persons by phone
                            }
                        }
                    } else if (looksLikeEmail) {
                        // For email-like terms, restrict search to email-only per tab to avoid AND with name/phone
                        if (this.activeTab === 'leads') {
                            this.params.search = `email:${newTerm};`;
                            this.params.searchFields = `emails:like;`;
                        } else if (this.activeTab === 'persons') {
                            this.params.search = `email:${newTerm};`;
                            this.params.searchFields = `emails:like;`;
                        } else if (this.activeTab === 'sales') {
                            // Sales doesn't support email search, skip
                        }
                    } else {
                        // Default behavior: map all query params for text searches
                        this.params.search += tab.query_params.map((param) => `${param.search}:${newTerm};`).join('');
                        this.params.searchFields += tab.query_params.map((param) => `${param.searchFields};`).join('');
                    }

                    this.search(tab.endpoint);
                },

                isPhoneNumber(term) {
                    if (!term || term.length < 3) return false;
                    // Check if it's mostly digits or starts with +
                    const cleaned = term.replace(/\s/g, '');
                    return /^\+?[\d\s\-\(\)]+$/.test(cleaned) && cleaned.replace(/\D/g, '').length >= 3;
                },

                isEmail(term) {
                    if (!term || typeof term !== 'string') return false;
                    // Simple email validation - check for @ symbol and basic format
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    return emailRegex.test(term.trim());
                },
            },
        });
    </script>
@endPushOnce
