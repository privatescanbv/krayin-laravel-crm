// packages/Webkul/Admin/src/Resources/assets/js/components/lookup.js

export default function registerLookupComponent(app) {
    if (!app) {
        return;
    }
    
    // Check if component is already registered
    if (app._context.components['v-lookup']) {
        return;
    }

    app.component('v-lookup', {
        template: `
            <div class="relative w-full">
                <!-- Input -->
                <input
                    type="text"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :placeholder="placeholder"
                    v-model="searchTerm"
                    @focus="open = true"
                    @input="search"
                />

                <!-- Dropdown -->
                <ul
                    v-if="open"
                    class="absolute z-10 w-full mt-1 bg-white border border-gray-300
                           rounded-md shadow-lg max-h-60 overflow-auto"
                >
                    <li
                        v-for="item in results"
                        :key="item.id"
                        class="px-3 py-2 hover:bg-blue-50 cursor-pointer"
                        @click="select(item)"
                        v-text="item.name_with_path ?? item.name"
                    ></li>
                    <li v-if="!results.length"
                        class="px-3 py-2 text-gray-500 text-sm">Geen resultaten</li>
                </ul>
            </div>
        `,

        props: {
            src: { type: String, required: true },
            name: { type: String, required: true },
            label: { type: String, default: '' },
            placeholder: { type: String, default: '' },
            value: { type: Object, default: () => ({}) },
            rules: { type: String, default: '' },
            canAddNew: { type: Boolean, default: false },
        },

        emits: ['on-selected'],

        data() {
            return {
                open: false,
                searchTerm: this.value?.name_with_path || this.value?.name || '',
                results: [],
                selectedItem: this.value || {},
                abortController: null,
            };
        },

        mounted() {
            if (this.value?.id) {
                this._createOrUpdateHiddenInput(this.value.id);
            }
        },

        methods: {
            _createOrUpdateHiddenInput(id) {
                let hidden = this.$el.querySelector(`input[type="hidden"][name="${this.name}"]`);
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = this.name;
                    this.$el.appendChild(hidden);
                }
                hidden.value = id;
            },

            async search() {
                if (!this.src || this.searchTerm.length < 2) {
                    this.results = [];
                    return;
                }

                // cancel vorige request
                if (this.abortController) this.abortController.abort();
                this.abortController = new AbortController();

                try {
                    const res = await fetch(
                        `${this.src}?query=${encodeURIComponent(this.searchTerm)}`,
                        { signal: this.abortController.signal }
                    );
                    const json = await res.json();
                    this.results = json?.data || (Array.isArray(json) ? json : []);
                } catch (e) {
                    if (e.name !== 'AbortError') console.error(e);
                }
            },

            select(item) {
                this.selectedItem = item;
                this.open = false;
                this.searchTerm = item.name_with_path ?? item.name;
                this._createOrUpdateHiddenInput(item.id);
                this.$emit('on-selected', item);
            },
        },
    });
}
