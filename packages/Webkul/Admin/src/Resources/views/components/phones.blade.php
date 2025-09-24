{!! view_render_event('admin.phones.before') !!}

<div class="flex flex-col gap-4">

    <v-phones-component
        :name="'phones'"
        :value='@json($value ?? [])'
        :errors='@json($errors->getMessages() ?? [])'
    ></v-phones-component>
</div>

{!! view_render_event('admin.phones.after') !!}

@pushOnce('scripts')
    @verbatim
        <script type="text/x-template" id="v-phones-component-template">
            <div>
                <div class="space-y-3">
                    <div
                        v-for="(phone, index) in phones"
                        :key="index"
                        class="flex items-center space-x-2"
                    >
                        <div class="flex-1">
                            <input
                                type="tel"
                                :name="name + '[' + index + '][value]'"
                                v-model="phone.value"
                                :class="getInputClass(index)"
                                placeholder="Voer telefoonnummer in"
                            />
                            <div v-if="getPhoneError(index)" class="mt-1 text-sm text-red-600">
                                {{ getPhoneError(index) }}
                            </div>
                        </div>

                        <select
                            :name="name + '[' + index + '][label]'"
                            v-model="phone.label"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option
                                v-for="opt in labelOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >@{{ opt.label }}</option>
                        </select>

                        <div class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                :name="name + '[' + index + '][is_default]'"
                                :id="'phone_default_' + index"
                                :checked="phone.is_default === true || phone.is_default === 'on'"
                                @change="handleDefaultChange(index, $event)"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label :for="'phone_default_' + index" class="text-sm text-gray-700 dark:text-gray-300">
                                Default
                            </label>
                        </div>

                        <button
                            type="button"
                            @click="removePhone(index)"
                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            <span class="sr-only">Remove Phone</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    @click="addPhone"
                    class="mt-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                >
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Voeg telefoonnummer toe
                </button>
            </div>
        </script>
    @endverbatim

    <script type="module">
        const CONTACT_LABEL_OPTIONS = @json(\App\Enums\ContactLabel::options());
        const CONTACT_LABEL_DEFAULT = @json(\App\Enums\ContactLabel::default()->value);

        app.component('v-phones-component', {
            template: '#v-phones-component-template',

            props: {
                name: {
                    type: String,
                    required: true
                },
                value: {
                    type: Array,
                    default: () => []
                },
                errors: {
                    type: Object,
                    default: () => ({})
                }
            },

            data() {
                return {
                    phones: this.processPhones(this.value),
                    labelOptions: CONTACT_LABEL_OPTIONS,
                    defaultLabel: CONTACT_LABEL_DEFAULT,
                }
            },

            mounted() {
                this.ensureDefaultPhone();
            },

            watch: {
                phones: {
                    handler(newPhones) {
                        this.$emit('input', newPhones);
                    },
                    deep: true
                }
            },

            methods: {
                processPhones(phones) {
                    // Ensure phones is an array
                    if (!Array.isArray(phones)) {
                        phones = [];
                    }

                    // Filter out empty values and process the phones
                    let validPhones = phones
                        .filter(phone => phone && phone.value && phone.value.trim() !== '')
                        .map(phone => ({
                            ...phone,
                            is_default: phone.is_default === true || phone.is_default === 'on' || phone.is_default === '1'
                        }));

                    // If no valid phones, return a default empty phone
                    if (validPhones.length === 0) {
                        return [{ value: '', label: this.defaultLabel, is_default: true }];
                    }

                    return validPhones;
                },

                addPhone() {
                    this.phones.push({ value: '', label: this.defaultLabel, is_default: false });
                },

                removePhone(index) {
                    if (this.phones.length > 1) {
                        const wasDefault = this.phones[index].is_default === true || this.phones[index].is_default === 'on';
                        this.phones.splice(index, 1);

                        // If we removed the default phone, make the first one default
                        if (wasDefault && this.phones.length > 0) {
                            this.phones[0].is_default = true;
                        }
                    }
                },

                handleDefaultChange(index, event) {
                    const isChecked = event.target.checked;

                    // Uncheck all other checkboxes
                    this.phones.forEach((phone, i) => {
                        if (i !== index) {
                            phone.is_default = false;
                        }
                    });

                    // Set the current phone's default status
                    this.phones[index].is_default = isChecked;

                    // If no phone is checked, make the first one default
                    if (!isChecked && this.phones.length > 0) {
                        this.phones[0].is_default = true;
                    }
                },

                ensureDefaultPhone() {
                    // If no phone is marked as default, make the first one default
                    const hasDefault = this.phones.some(phone =>
                        phone.is_default === true || phone.is_default === 'on' || phone.is_default === '1'
                    );
                    if (!hasDefault && this.phones.length > 0) {
                        this.phones[0].is_default = true;
                    }
                },

                getInputClass(index) {
                    const baseClass = 'w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-1 dark:bg-gray-700 dark:text-white';
                    const hasError = this.getPhoneError(index);

                    if (hasError) {
                        return baseClass + ' border-red-300 focus:border-red-500 focus:ring-red-500 dark:border-red-600';
                    } else {
                        return baseClass + ' border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600';
                    }
                },

                getPhoneError(index) {
                    const errorKey = this.name + '.' + index + '.value';
                    return this.errors[errorKey] ? this.errors[errorKey][0] : null;
                }
            }
        });
    </script>
@endPushOnce

@php /* moved normalization to Vue component; server no-op */ @endphp
