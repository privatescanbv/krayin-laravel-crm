{!! view_render_event('admin.emails.before') !!}

<div class="flex flex-col gap-4">
{{--    <div class="flex flex-col gap-1">--}}
{{--        <p class="text-base font-semibold dark:text-white">--}}
{{--            @lang('admin::app.leads.common.emails.title')--}}
{{--        </p>--}}
{{--    </div>--}}

    <v-emails-component
        :name="'emails'"
        :value='@json($value ?? [])'
    ></v-emails-component>
</div>

{!! view_render_event('admin.emails.after') !!}

@pushOnce('scripts')
    @verbatim
        <script type="text/x-template" id="v-emails-component-template">
            <div>
                <div class="space-y-3">
                    <div
                        v-for="(email, index) in emails"
                        :key="index"
                        class="flex items-center space-x-2"
                    >
                        <input
                            type="email"
                            :name="name + '[' + index + '][value]'"
                            v-model="email.value"
                            class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            placeholder="Enter email address"
                        />

                        <select
                            :name="name + '[' + index + '][label]'"
                            v-model="email.label"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="work">Work</option>
                            <option value="home">Home</option>
                            <option value="other">Other</option>
                        </select>

                        <div class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                :name="name + '[' + index + '][is_default]'"
                                :id="'email_default_' + index"
                                :checked="email.is_default === true || email.is_default === 'on'"
                                @change="handleDefaultChange(index, $event)"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label :for="'email_default_' + index" class="text-sm text-gray-700 dark:text-gray-300">
                                Default
                            </label>
                        </div>

                        <button
                            type="button"
                            @click="removeEmail(index)"
                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            <span class="sr-only">Remove Email</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    @click="addEmail"
                    class="mt-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                >
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Email
                </button>
            </div>
        </script>

        <script type="module">
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
                    }
                },

                data() {
                    return {
                        emails: this.processEmails(this.value)
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

                methods: {
                    processEmails(emails) {
                        if (emails.length === 0) {
                            return [{ value: '', label: 'work', is_default: true }];
                        }

                        // Convert "on" to true and ensure boolean values
                        return emails.map(email => ({
                            ...email,
                            is_default: email.is_default === true || email.is_default === 'on' || email.is_default === '1'
                        }));
                    },

                    addEmail() {
                        this.emails.push({ value: '', label: 'work', is_default: false });
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
                    }
                }
            });
        </script>
    @endverbatim
@endPushOnce

@php
    $emails = $value ?? [];
    if (empty($emails)) {
        $emails = [['value' => '', 'label' => 'work', 'is_default' => true]];
    }
@endphp

