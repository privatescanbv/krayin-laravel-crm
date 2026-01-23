<v-datetime-picker {{ $attributes }}>
    {{ $slot }}
</v-datetime-picker>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-datetime-picker-template"
    >
        <span class="relative inline-block w-full">
            <slot></slot>

            <i ref="calendarIcon" class="icon-calendar absolute top-1/2 -translate-y-1/2 cursor-pointer text-2xl text-gray-400 ltr:right-2 rtl:left-2"></i>
        </span>
    </script>

    <script type="module">
        app.component('v-datetime-picker', {
            template: '#v-datetime-picker-template',

            props: {
                name: String,

                value: String,

                allowInput: {
                    type: Boolean,
                    default: true,
                },

                disable: Array,

                minDate: String,

                maxDate: String,
            },

            data: function() {
                return {
                    datepicker: null
                };
            },

            mounted: function() {
                let options = this.setOptions();

                this.activate(options);

                // Keep behavior consistent with date picker: click icon opens picker.
                this.$nextTick(() => {
                    if (this.$refs.calendarIcon) {
                        this.$refs.calendarIcon.addEventListener('click', () => {
                            if (this.datepicker) {
                                this.datepicker.open();
                            }
                        });
                    }

                    // Set initial value if provided
                    if (this.value) {
                        this.datepicker?.setDate?.(this.value);
                    }
                });
            },

            methods: {
                setOptions: function() {
                    let self = this;

                    return {
                        allowInput: this.allowInput ?? true,
                        disable: this.disable ?? [],
                        minDate: this.minDate ?? '',
                        maxDate: this.maxDate ?? '',
                        altInput: true,
                        altFormat: "d-m-Y H:i",
                        dateFormat: "Y-m-d H:i",
                        enableTime: true,
                        time_24hr: true,
                        weekNumbers: true,
                        defaultDate: this.value || null,
                        clickOpens: false,

                        onChange: function(selectedDates, dateStr, instance) {
                            self.$emit("onChange", dateStr);
                        },
                    };
                },

                activate: function(options) {
                    let element = this.$el.getElementsByTagName("input")[0];

                    this.datepicker = new Flatpickr(element, options);

                    /**
                     * Flatpickr renders the visible input as `altInput` (type="text") and
                     * hides the original input (type="hidden"). Browser autofill targets
                     * the visible input, so we must disable autocomplete there as well.
                     */
                    const applyNoAutofillAttrs = (input) => {
                        if (! input) return;

                        input.setAttribute('autocomplete', 'off');
                        input.setAttribute('autocorrect', 'off');
                        input.setAttribute('autocapitalize', 'off');
                        input.setAttribute('spellcheck', 'false');

                        // Common password manager hints (harmless for browsers).
                        input.setAttribute('data-lpignore', 'true');
                        input.setAttribute('data-1p-ignore', 'true');
                        input.setAttribute('data-form-type', 'other');
                    };

                    applyNoAutofillAttrs(element);
                    applyNoAutofillAttrs(this.datepicker?.altInput);
                },

                clear: function() {
                    this.datepicker.clear();
                }
            }
        });
    </script>
@endPushOnce
