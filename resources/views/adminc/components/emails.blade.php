@php use App\Enums\ContactLabel; @endphp
{!! view_render_event('admin.emails.before') !!}

<div class="flex flex-col gap-4">

    <v-emails-component
        name="{{ $name ?? 'emails' }}"
        :value='@json($value ?? [])'
        :errors='@json($errors->getMessages() ?? [])'
        :readonly='@json($readonly ?? false)'
    ></v-emails-component>
</div>

{!! view_render_event('admin.emails.after') !!}

@pushOnce('scripts')
    @verbatim
        <script type="text/x-template" id="v-emails-component-template">
            <div>
                <div v-if="topLevelErrors.length"
                     class="mb-2 rounded border border-red-400 bg-red-100 px-3 py-2 text-red-800 dark:bg-red-900 dark:text-red-200">
                    <div v-for="(msg, i) in topLevelErrors" :key="i">{{ msg }}</div>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="(email, index) in emails"
                        :key="index"
                        class="flex items-center space-x-2 gap-x-4"
                    >
                        <div class="flex-1">
                            <input
                                type="email"
                                :name="name + '[' + index + '][value]'"
                                v-model="email.value"
                                :class="getInputClass(index)"
                                placeholder="Voer email-adres in"
                                :readonly="readonly"
                            />
                            <div v-if="getEmailError(index)" class="mt-1 text-sm text-status-expired-text">
                                {{ getEmailError(index) }}
                            </div>
                        </div>

                        <select
                            :name="name + '[' + index + '][label]'"
                            v-model="email.label"
                            class="flex-1"
                            :disabled="readonly"
                        >
                            <option
                                v-for="opt in labelOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >{{ opt.label }}</option>
                        </select>
                        <!-- Mirror label value when readonly so it posts -->
                        <input
                            v-if="readonly"
                            type="hidden"
                            :name="name + '[' + index + '][label]'"
                            :value="email.label"
                        />

                        <div class="flex items-center space-x-2 gap-x-1">
                            <input
                                type="checkbox"
                                :name="name + '[' + index + '][is_default]'"
                                :id="'email_default_' + index"
                                :checked="email.is_default === true || email.is_default === 'on'"
                                @change="handleDefaultChange(index, $event)"
                                class="h-4 w-4 text-activity-note-text focus:ring-blue-500 border-gray-300 rounded"
                                :disabled="readonly"
                            />
                            <label :for="'email_default_' + index" class="text-sm text-gray-700 dark:text-gray-300">
                                Default
                            </label>
                            <!-- Mirror checkbox when readonly so it posts if checked -->
                            <input
                                v-if="readonly && (email.is_default === true || email.is_default === 'on' || email.is_default === '1')"
                                type="hidden"
                                :name="name + '[' + index + '][is_default]'"
                                value="on"
                            />
                        </div>

                        <button
                            v-if="!readonly"
                            type="button"
                            @click="removeEmail(index)"
                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            <span class="sr-only">Remove Email</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    v-if="!readonly"
                    type="button"
                    @click="addEmail"
                    class="mt-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                >
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Voeg e-mailadres toe
                </button>
            </div>
        </script>
    @endverbatim

    <script type="module">
        const CONTACT_LABEL_OPTIONS = @json(ContactLabel::options());
        const CONTACT_LABEL_DEFAULT = @json(ContactLabel::default()->value);

        app.component('v-emails-component', {
            template: '#v-emails-component-template',

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
                },
                readonly: {
                    type: Boolean,
                    default: false
                }
            },

            data() {
                return {
                    emails: this.processEmails(this.value),
                    labelOptions: CONTACT_LABEL_OPTIONS,
                    defaultLabel: CONTACT_LABEL_DEFAULT,
                }
            },

            mounted() {
                this.ensureDefaultEmail();
            },

            watch: {
                emails: {
                    handler(newEmails) {
                        this.$emit('input', newEmails);
                    },
                    deep: true
                }
            },

            computed: {
                topLevelErrors() {
                    const msgs = this.errors && this.errors[this.name];
                    return Array.isArray(msgs) ? msgs : [];
                }
            },

            methods: {
                processEmails(emails) {
                    // Ensure emails is an array
                    if (!Array.isArray(emails)) {
                        emails = [];
                    }

                    // Process all emails (including empty ones for user input)
                    let processedEmails = emails.map(email => ({
                        value: email?.value || '',
                        label: (email?.label && String(email.label).trim() !== '') ? email.label : this.defaultLabel,
                        is_default: email?.is_default === true || email?.is_default === 'on' || email?.is_default === '1'
                    }));

                    // If no emails at all, add one empty email for user to fill
                    if (processedEmails.length === 0) {
                        return [{value: '', label: this.defaultLabel, is_default: true}];
                    }

                    return processedEmails;
                },

                addEmail() {
                    this.emails.push({value: '', label: this.defaultLabel, is_default: false});
                },

                removeEmail(index) {
                    if (this.emails.length > 1) {
                        const wasDefault = this.emails[index].is_default === true || this.emails[index].is_default === 'on';
                        this.emails.splice(index, 1);

                        // If we removed the default email, make the first one default
                        if (wasDefault && this.emails.length > 0) {
                            this.emails[0].is_default = true;
                        }
                    }
                },

                handleDefaultChange(index, event) {
                    const isChecked = event.target.checked;

                    // Uncheck all other checkboxes
                    this.emails.forEach((email, i) => {
                        if (i !== index) {
                            email.is_default = false;
                        }
                    });

                    // Set the current email's default status
                    this.emails[index].is_default = isChecked;

                    // If no email is checked, make the first one default
                    if (!isChecked && this.emails.length > 0) {
                        this.emails[0].is_default = true;
                    }
                },

                ensureDefaultEmail() {
                    // If no email is marked as default, make the first one default
                    const hasDefault = this.emails.some(email =>
                        email.is_default === true || email.is_default === 'on' || email.is_default === '1'
                    );
                    if (!hasDefault && this.emails.length > 0) {
                        this.emails[0].is_default = true;
                    }
                },


                getInputClass(index) {
                    const baseClass = 'w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-1 dark:bg-gray-700 dark:text-white';
                    const hasError = this.getEmailError(index);

                    if (hasError) {
                        return baseClass + ' border-red-300 focus:border-error focus:ring-red-500 dark:border-red-600';
                    } else {
                        return baseClass + ' border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600';
                    }
                },

                getEmailError(index) {
                    const errorKey = this.name + '.' + index + '.value';
                    return this.errors[errorKey] ? this.errors[errorKey][0] : null;
                }
            }
        });
    </script>
@endPushOnce

