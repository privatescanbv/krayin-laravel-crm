{{-- Demo page to test entity-selector component --}}
<x-admin::layouts>
    <x-slot:title>Entity Selector Demo</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">Entity Selector Demo</div>
        </div>

        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold">Single Select Mode</h3>
            <x-adminc::components.entity-selector
                name="single_entity"
                label="Single Entity Selection"
                placeholder="Selecteer een entiteit..."
                searchRoute="{{ route('admin.partner_products.search') }}"
                :items="[]"
            />
        </div>

        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold">Multi Select Mode</h3>
            <x-adminc::components.entity-selector
                name="multiple_entities"
                label="Multiple Entity Selection"
                placeholder="Selecteer partner products..."
                searchRoute="{{ route('admin.partner_products.search') }}"
                :multiple="true"
                :items="[]"
            />
        </div>

        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold">Multi Select with Pre-selected Items</h3>
            <x-adminc::components.entity-selector
                name="pre_selected_entities"
                label="Pre-selected Entities"
                placeholder="Selecteer meer items..."
                searchRoute="{{ route('admin.partner_products.search') }}"
                :multiple="true"
                :items="[
                    ['id' => 1, 'name' => 'Sample Partner Product 1'],
                    ['id' => 2, 'name' => 'Sample Partner Product 2']
                ]"
            />
        </div>

        <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold">Single Select with Current Value (like Lead Edit)</h3>
            <x-adminc::components.entity-selector
                name="current_value_entity"
                label="Contact Person Selection"
                placeholder="Selecteer contactpersoon..."
                searchRoute="{{ route('admin.contacts.persons.search') }}"
                :current-value="1"
                :current-label="'John Doe'"
                :can-add-new="true"
            />
        </div>
    </div>

    @stack('scripts')
</x-admin::layouts>
