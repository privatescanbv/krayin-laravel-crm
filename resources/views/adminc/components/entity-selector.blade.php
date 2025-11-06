@pushOnce('scripts')
    @verbatim
        <script type="text/x-template" id="v-entity-selector-template">
            <div class="w-full space-y-3">

                <label v-if="label" class="block font-semibold mb-1">{{ label }}</label>
                <p v-if="hint" class="text-sm text-gray-600 dark:text-gray-400">
                    <i>{{ hint }}</i>
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                    <!-- Selected items -->
                    <div class="space-y-2">
                        <template v-if="selectedItems.length">
                            <div
                                    v-for="(item, idx) in selectedItems"
                                    :key="item.id ?? idx"
                                    class="p-2 border rounded bg-green-50 border-green-200 flex items-center justify-between"
                            >
                                <div class="text-sm font-medium truncate">
                                    {{ item.name ?? item.label ?? item.text ?? ('#' + (item.id ?? idx)) }}
                                </div>
                                <button
                                        type="button"
                                        class="text-red-600 hover:text-red-800 p-1"
                                        @click="removeItem(idx)"
                                        :title="'Verwijder'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <div class="p-2 border rounded bg-gray-50 border-gray-200 text-gray-500 text-sm">
                                Geen items geselecteerd
                            </div>
                        </template>
                    </div>

                    <!-- Search input and suggestions -->
                    <div>
                        <div class="relative">
                            <input
                                    v-model="search"
                                    @input="onSearch"
                                    :placeholder="placeholder || 'Zoek item...'"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-2 bg-white dark:bg-gray-800 dark:border-gray-600"
                                    autocomplete="off"
                            />
                            <div v-if="isSearching" class="absolute right-3 top-1/2 transform -translate-y-1/2 -mb-1">
                                <svg class="h-4 w-4 animate-spin text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>

                        <ul v-if="suggestions.length" class="border rounded bg-white shadow mb-2 max-h-60 overflow-y-auto">
                            <li
                                    v-for="s in suggestions"
                                    :key="s.id ?? s.value ?? s"
                                    @click="selectSuggestion(s)"
                                    class="px-3 py-2 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                            >
                                <slot name="suggestion" :item="s">
                                    <div class="flex items-center justify-between">
                                        <div class="font-medium">{{ s.name ?? s.label ?? s.text ?? s }}</div>
                                        <span class="ml-2 text-green-600 text-xs">+ Toevoegen</span>
                                    </div>
                                </slot>
                            </li>
                        </ul>

                        <div v-if="search.length >= 2 && !isSearching && suggestions.length === 0" class="p-3 border rounded bg-blue-50 border-blue-200" v-show="canAddNew">
                            <div class="text-center">
                                <div class="text-sm text-blue-700 mb-2">Geen resultaten voor "{{ search }}"</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs -->
                <template v-if="name">
                    <input
                            v-for="(it, i) in selectedItems"
                            type="hidden"
                            :name="multiple ? (name + '[' + i + ']') : name"
                            :value="it.id ?? it.value ?? ''"
                    />
                </template>
            </div>
        </script>

        <script type="module">
            if (!app._context.components['v-entity-selector']) {
                app.component('v-entity-selector', {
                template: '#v-entity-selector-template',
                props: ['name','label', 'hint','placeholder','searchRoute','canAddNew','multiple','style','items','eventName','fetcher'],
                data() {
                    return {
                        search: '',
                        suggestions: [],
                        isSearching: false,
                        searchTimeout: null,
                        selectedItems: Array.isArray(this.items) ? [...this.items] : [],
                    };
                },
                watch: {
                    items: {
                        deep: true,
                        handler(newVal){
                            this.selectedItems = Array.isArray(newVal) ? [...newVal] : [];
                        }
                    }
                },
                methods: {
                    onSearch() {
                        clearTimeout(this.searchTimeout);
                        if (!this.searchRoute || (this.search || '').length < 2) {
                            this.suggestions = [];
                            return;
                        }
                        this.searchTimeout = setTimeout(this.fetchSuggestions, 300);
                    },
                    async fetchSuggestions() {
                        this.isSearching = true;
                        try {
                            let results = [];
                            if (typeof this.fetcher === 'function') {
                                results = await this.fetcher(this.search);
                            } else {
                                const response = await axios.get(this.searchRoute, { params: { query: this.search } });
                                const data = (response && response.data && (response.data.data || response.data)) || [];
                                results = Array.isArray(data) ? data : [];
                            }
                            this.suggestions = (results || []).filter(s => !this.isSelected((s.id ?? s.value)));
                        } catch (e) {
                            console.error('Entity search failed', e);
                            this.suggestions = [];
                        } finally {
                            this.isSearching = false;
                        }
                    },
                    selectSuggestion(s) {
                        const item = typeof s === 'object' ? s : { id: s, name: String(s) };
                        if (!this.isSelected(item.id ?? item.value)) {
                            if (this.multiple !== false) {
                                this.selectedItems.push(item);
                            } else {
                                this.selectedItems = [item];
                            }
                            this.$emit('select', item);
                            this.$emit('update:items', this.selectedItems);
                        }
                        this.search = '';
                        this.suggestions = [];
                    },
                    removeItem(index) {
                        const removed = this.selectedItems.splice(index, 1)[0];
                        this.$emit('remove', removed);
                        this.$emit('update:items', this.selectedItems);
                    },
                    isSelected(id) {
                        return this.selectedItems.some(i => (i.id ?? i.value) === id);
                    },
                    emitCreateNew() {
                        this.$emit('create-new', { query: this.search });
                    }
                }
            });
            }
        </script>
    @endverbatim
@endPushOnce
