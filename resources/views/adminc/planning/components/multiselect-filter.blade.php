@pushOnce('scripts')
    <script type="text/x-template" id="v-multiselect-filter-template">
        <div class="flex flex-col w-full">
            <label v-if="label" class="block text-xs mb-1 text-gray-600 dark:text-gray-400">@{{ label }}</label>

            <!-- Selected values as tags -->
            <div v-if="selectedValues.length > 0" class="mb-2 flex flex-wrap gap-1.5">
                <span
                    v-for="value in selectedValues"
                    :key="value"
                    class="inline-flex items-center gap-1 rounded bg-gray-600 px-2 py-1 text-xs font-semibold text-white dark:bg-gray-700"
                >
                    @{{ getOptionLabel(value) }}
                    <span
                        class="icon-cross-large cursor-pointer text-sm text-white hover:text-gray-200"
                        @click="removeValue(value)"
                    ></span>
                </span>
            </div>

            <!-- Dropdown -->
            <x-admin::dropdown position="bottom-left" close-on-click="false">
                <x-slot:toggle>
                    <button
                        type="button"
                        class="inline-flex w-full cursor-pointer appearance-none items-center justify-between gap-x-2 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm leading-6 text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-400"
                    >
                        <span class="text-gray-400 dark:text-gray-400">
                            @{{ selectedValues.length > 0 ? placeholder : (placeholder || 'Selecteer...') }}
                        </span>
                        <span class="icon-down-arrow text-xl"></span>
                    </button>
                </x-slot:toggle>

                <x-slot:menu>
                    <div
                        v-for="option in options"
                        :key="option.value"
                        @click="toggleValue(option.value)"
                        class="flex cursor-pointer items-center justify-between gap-2.5 rounded-md p-1.5 text-base text-gray-800 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                    >
                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                :checked="selectedValues.includes(option.value)"
                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                @click.stop
                                @change="toggleValue(option.value)"
                            />
                            <span>@{{ option.label }}</span>
                        </div>
                    </div>
                </x-slot:menu>
            </x-admin::dropdown>
        </div>
    </script>

    <script type="module">
        app.component('v-multiselect-filter', {
            template: '#v-multiselect-filter-template',

            props: {
                modelValue: {
                    type: Array,
                    default: () => []
                },
                options: {
                    type: Array,
                    required: true
                },
                label: {
                    type: String,
                    default: ''
                },
                placeholder: {
                    type: String,
                    default: 'Selecteer...'
                }
            },

            data() {
                return {
                    selectedValues: this.modelValue || []
                };
            },

            watch: {
                modelValue(newValue) {
                    this.selectedValues = newValue || [];
                },
                selectedValues(newValue) {
                    this.$emit('update:modelValue', newValue);
                }
            },

            methods: {
                toggleValue(value) {
                    if (this.selectedValues.includes(value)) {
                        this.selectedValues = this.selectedValues.filter(v => v !== value);
                    } else {
                        this.selectedValues = [...this.selectedValues, value];
                    }
                },

                removeValue(value) {
                    this.selectedValues = this.selectedValues.filter(v => v !== value);
                },

                getOptionLabel(value) {
                    const option = this.options.find(o => o.value === value);
                    return option ? option.label : value;
                }
            }
        });
    </script>
@endPushOnce
