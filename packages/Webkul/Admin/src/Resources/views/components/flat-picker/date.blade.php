<v-date-picker {{ $attributes }}>
    {{ $slot }}
</v-date-picker>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-date-picker-template"
    >
        <span class="inline-block w-full">
            <span class="relative block">
                <slot></slot>

                <i ref="calendarIcon" class="icon-calendar absolute top-1/2 -translate-y-1/2 text-2xl text-gray-400 ltr:right-2 rtl:left-2"></i>
            </span>

            <p
                v-if="error"
                class="mt-1 text-xs italic text-red-600"
                data-date-picker-error
            >
                @{{ error }}
            </p>
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
                    datepicker: null,

                    error: null,

                    lastValidDate: null,

                    restoring: false,

                    form: null,
                };
            },

            mounted: function() {
                let options = this.setOptions();

                this.activate(options);

                // Capture phase on the form runs before vee-validate's submit handler
                this.form = this.$el.closest('form');
                this.form?.addEventListener('submit', this.blockSubmitWhileInvalid, true);

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

            beforeUnmount: function() {
                this.form?.removeEventListener('submit', this.blockSubmitWhileInvalid, true);
            },

            methods: {
                blockSubmitWhileInvalid: function(event) {
                    if (! this.error) {
                        return;
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    this.datepicker?.altInput.focus();
                },

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
                            // Rejects rollovers like 31-02 (JS would silently turn it into 2 March)
                            let makeDate = function(year, month, day) {
                                let date = new Date(year, month - 1, day);

                                return year >= 1000 && date.getMonth() === month - 1 && date.getDate() === day
                                    ? date
                                    : new Date(NaN);
                            };

                            // Two-digit year: up to 10 years ahead → 20xx, otherwise 19xx (72 → 1972, 26 → 2026)
                            let shortYear = dateString.match(/^(\d{1,2})-(\d{1,2})-(\d{2})$/) || dateString.match(/^(\d{2})(\d{2})(\d{2})$/);
                            if (shortYear) {
                                let year = parseInt(shortYear[3]);
                                year += year <= (new Date().getFullYear() % 100) + 10 ? 2000 : 1900;

                                return makeDate(year, parseInt(shortYear[2]), parseInt(shortYear[1]));
                            }

                            // Handle 8 digits without separators (ddmmyyyy)
                            if (/^\d{8}$/.test(dateString)) {
                                return makeDate(
                                    parseInt(dateString.substring(4, 8)),
                                    parseInt(dateString.substring(2, 4)),
                                    parseInt(dateString.substring(0, 2))
                                );
                            }

                            // Handle dd-mm-yyyy
                            let match = dateString.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
                            if (match) {
                                return makeDate(parseInt(match[3]), parseInt(match[2]), parseInt(match[1]));
                            }

                            // Handle yyyy-mm-dd (internal format)
                            match = dateString.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
                            if (match) {
                                return makeDate(parseInt(match[1]), parseInt(match[2]), parseInt(match[3]));
                            }

                            // Fallback
                            return new Date(dateString);
                        },
                        onChange: function(selectedDates, dateStr, instance) {
                            if (selectedDates.length && ! self.restoring) {
                                self.lastValidDate = dateStr;
                                self.error = null;
                            }

                            self.$emit("onChange", dateStr);
                        },
                        // Flatpickr clears the field on unparseable input; restore the last valid date instead
                        errorHandler: function(error) {
                            if (! self.datepicker || ! String(error.message).startsWith('Invalid date provided')) {
                                return console.warn(error);
                            }

                            // Set synchronously so a submit right after blur is already blocked
                            self.error = `"${self.datepicker.altInput.value}" is geen geldige datum (dd-mm-jjjj)`;

                            setTimeout(() => {
                                if (self.lastValidDate) {
                                    self.restoring = true;
                                    self.datepicker.setDate(self.lastValidDate, true);
                                    self.restoring = false;
                                }
                            });
                        }
                    };
                },

                activate: function(options) {
                    let element = this.$el.getElementsByTagName("input")[0];

                    this.datepicker = new Flatpickr(element, options);

                    this.lastValidDate = this.datepicker.selectedDates.length ? this.datepicker.input.value : null;
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
