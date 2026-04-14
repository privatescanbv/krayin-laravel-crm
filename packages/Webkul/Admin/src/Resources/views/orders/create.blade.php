<x-admin::layouts>
    <x-slot:title>
        Order aanmaken
    </x-slot>

    @push('scripts')
        <script>
            window.adminc = window.adminc || {};
            /**
             * VeeValidate (v-field) houdt waarden bij los van het DOM; alleen .value zetten
             * synchroniseert niet. Events triggeren dezelfde handlers als typen.
             */
            window.adminc.setNativeInputValueSyncingVeeValidate = function (input, value) {
                if (! input) {
                    return;
                }
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };
            window.adminc.syncOrderTitleFromSalesLeadSelection = function (selectedItem, componentEl) {
                if (! selectedItem || selectedItem.name == null || selectedItem.name === '') {
                    return;
                }
                const form = componentEl && componentEl.closest ? componentEl.closest('form') : null;
                const titleInput = form && form.querySelector('input[name="title"]');
                if (titleInput && titleInput.value.trim() !== '') {
                    return;
                }
                window.adminc.setNativeInputValueSyncingVeeValidate(titleInput, selectedItem.name);
            };
        </script>
    @endpush

    @include('adminc.components.sales-lead-selector')

    <x-admin::form :action="route('admin.orders.store')" method="POST">
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="orders.create" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        Order aanmaken
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        Opslaan
                    </button>
                </div>
            </div>

            <!-- Wit panel met velden (zoals edit order) - 1 kolom -->
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-6">
                    <x-adminc::components.field
                        type="text"
                        name="title"
                        label="Titel"
                        rules="required"
                        :value="old('title', $defaultOrderTitle ?? '')"
                    />

                    <x-adminc::components.field
                        type="switch"
                        name="combine_order"
                        label="Orders combineren"
                        value="1"
                        :checked="old('combine_order', '1') === '1'"
                    />

                    <v-sales-lead-selector
                        name="sales_lead_id"
                        label="Sales"
                        placeholder="Zoek sales lead..."
                        :current-value="{{ json_encode($salesLeadId ?? old('sales_lead_id')) }}"
                        current-label="{{ e($salesLeadName ?? '') }}"
                        :can-add-new="false"
                        selection-change-callback="syncOrderTitleFromSalesLeadSelection"
                    />
                </div>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
