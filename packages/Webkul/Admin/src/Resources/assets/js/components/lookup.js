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
                        v-text="item.name"
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
                searchTerm: '',
                results: [],
                selectedItem: this.value || {},
                abortController: null,
            };
        },

        methods: {
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
                        `${this.src}?q=${encodeURIComponent(this.searchTerm)}`,
                        { signal: this.abortController.signal }
                    );
                    this.results = await res.json();
                } catch (e) {
                    if (e.name !== 'AbortError') console.error(e);
                }
            },

            select(item) {
                this.selectedItem = item;
                this.open = false;
                this.searchTerm = item.name;
                this.$emit('on-selected', item);
            },
        },
    });
}
