<v-date-picker {{ $attributes }}>
    {{ $slot }}
</v-date-picker>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-date-picker-template"
    >
        <span class="relative inline-block w-full">
            <slot></slot>

            <i ref="calendarIcon" class="icon-calendar absolute top-1/2 -translate-y-1/2 text-2xl text-gray-400 ltr:right-2 rtl:left-2"></i>
        </span>
    </script>

    <script type="module">
        app.component('v-date-picker', {
            template: '#v-date-picker-template',

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

                // Set initial value if provided
                this.$nextTick(() => {
                    if (this.$refs.calendarIcon) {
                        this.$refs.calendarIcon.addEventListener('click', () => {
                            if (this.datepicker) {
                                this.datepicker.open();
                            }
                        });
                    }

                    if (this.value) {
                        this.setDate(this.value);
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
                        altFormat: "d-m-Y",
                        dateFormat: "Y-m-d",
                        weekNumbers: true,
                        defaultDate: this.value || null,
                        clickOpens: false,
                        parseDate: function(dateString, format) {
                            // Handle 8 digits without separators (ddmmyyyy)
                            if (/^\d{8}$/.test(dateString)) {
                                return new Date(
                                    parseInt(dateString.substring(4, 8)),
                                    parseInt(dateString.substring(2, 4)) - 1,
                                    parseInt(dateString.substring(0, 2))
                                );
                            }

                            // Handle dd-mm-yyyy
                            let match = dateString.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
                            if (match) {
                                return new Date(parseInt(match[3]), parseInt(match[2]) - 1, parseInt(match[1]));
                            }

                            // Handle yyyy-mm-dd (internal format)
                            match = dateString.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
                            if (match) {
                                return new Date(parseInt(match[1]), parseInt(match[2]) - 1, parseInt(match[3]));
                            }

                            // Fallback
                            return new Date(dateString);
                        },
                        onChange: function(selectedDates, dateStr, instance) {
                            self.$emit("onChange", dateStr);
                        }
                    };
                },

                activate: function(options) {
                    let element = this.$el.getElementsByTagName("input")[0];

                    this.datepicker = new Flatpickr(element, options);
                },

                setDate: function(date) {
                    if (this.datepicker && date) {
                        this.datepicker.setDate(date);
                    }
                },

                clear: function() {
                    this.datepicker.clear();
                }
            }
        });
    </script>
@endPushOnce
