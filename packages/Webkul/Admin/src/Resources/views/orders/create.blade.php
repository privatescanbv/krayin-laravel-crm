<x-admin::layouts>
    <x-slot:title>
        Order aanmaken
    </x-slot>

    @include('adminc.components.sales-lead-selector')

    <x-admin::form :action="route('admin.orders.store')" method="POST">
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.clinics" />

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
                    />
                </div>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
