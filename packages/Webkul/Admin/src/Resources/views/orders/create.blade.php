<x-admin::layouts>
    <x-slot:title>
        Order aanmaken
    </x-slot>

    <x-admin::form :action="route('admin.orders.store')" method="POST">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
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

            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">Titel</x-admin::form.control-group.label>
                <x-admin::form.control-group.control type="text" name="title" rules="required" />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">Sales Lead</x-admin::form.control-group.label>
                <x-admin::form.control-group.control 
                    type="select" 
                    name="sales_lead_id" 
                    value="{{ $salesLeadId ?? old('sales_lead_id') ?? '' }}"
                    rules="required|integer"
                >
                    <option value="">Selecteer een Sales Lead</option>
                    @if(isset($salesLeads))
                        @foreach($salesLeads as $id => $name)
                            <option value="{{ $id }}" {{ ($salesLeadId ?? old('sales_lead_id')) == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    @endif
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>



            @include('admin::orders.partials.items', ['persons' => $persons ?? []])
        </div>
    </x-admin::form>

    @pushOnce('scripts')
        <script type="module">
            document.addEventListener('DOMContentLoaded', function() {
                const salesLeadSelect = document.querySelector('select[name="sales_lead_id"]');
                
                if (salesLeadSelect) {
                    salesLeadSelect.addEventListener('change', function() {
                        const salesLeadId = this.value;
                        if (salesLeadId) {
                            // Load persons for the selected sales lead
                            fetch(`/admin/orders/persons/${salesLeadId}`)
                                .then(response => response.json())
                                .then(data => {
                                    // Update the persons data in the order items component
                                    if (window.app && window.app.config && window.app.config.globalProperties) {
                                        const app = window.app;
                                        // Find the order items component and update its persons data
                                        const orderItemsVue = app._instance?.proxy?.$refs?.orderItemsList;
                                        if (orderItemsVue) {
                                            orderItemsVue.persons = data.persons || {};
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading persons:', error);
                                });
                        }
                    });
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>

