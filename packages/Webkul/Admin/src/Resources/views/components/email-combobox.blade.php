{{--
    Generic editable email combobox.

    Usage in plain Blade (PHP variables available):
        <x-admin::email-combobox name="to" :suggestions="$emails" value="{{ old('to') }}" />

    Usage inside a Vue template (v-model, Vue data):
        Register the component via @pushOnce in the page's script block (see orders/confirm.blade.php),
        then use <v-email-combobox v-model="emailTo" :suggestions="entityEmails" /> directly.
--}}
<v-email-combobox {{ $attributes }}></v-email-combobox>

@pushOnce('scripts')
    <script type="text/x-template" id="v-email-combobox-template">
        <div class="relative flex-1">
            <input
                type="email"
                :name="name"
                :value="inputValue"
                :placeholder="placeholder"
                class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                @input="onInput"
                @focus="onFocus"
                @blur="onBlur"
                autocomplete="off"
            />
            <ul
                v-if="isOpen && suggestions.length"
                class="absolute z-50 mt-1 w-full rounded border border-gray-200 bg-white shadow-md dark:border-gray-700 dark:bg-gray-900"
            >
                <li
                    v-for="s in suggestions"
                    :key="s.value"
                    class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    @mousedown.prevent="selectSuggestion(s)"
                >
                    @{{ s.value }}<span v-if="s.is_default" class="ml-1 text-xs text-gray-400">(standaard)</span>
                </li>
            </ul>
        </div>
    </script>

    <script type="module">
        app.component('v-email-combobox', {
            template: '#v-email-combobox-template',
            props: {
                modelValue:  { type: String, default: '' },
                suggestions: { type: Array,  default: () => [] },
                placeholder: { type: String, default: '' },
                name:        { type: String, default: '' },
            },
            data() {
                return { inputValue: this.modelValue, isOpen: false };
            },
            watch: {
                modelValue(val) { this.inputValue = val; },
            },
            methods: {
                onInput(e)          { this.inputValue = e.target.value; this.$emit('update:modelValue', e.target.value); },
                onFocus()           { if (this.suggestions.length) this.isOpen = true; },
                onBlur()            { setTimeout(() => { this.isOpen = false; }, 150); },
                selectSuggestion(s) { this.inputValue = s.value; this.$emit('update:modelValue', s.value); this.isOpen = false; },
            },
        });
    </script>
@endPushOnce
