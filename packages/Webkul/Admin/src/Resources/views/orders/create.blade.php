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

@php use App\Models\SalesLead; @endphp
            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">Sales Lead</x-admin::form.control-group.label>
                <x-admin::form.control-group.control 
                    type="select" 
                    name="sales_lead_id" 
                    value="{{ $salesLeadId ?? old('sales_lead_id') ?? '' }}"
                    rules="required|integer|exists:salesleads,id"
                    :options="SalesLead::with('lead')->get()->mapWithKeys(function($salesLead) {
                        return [$salesLead->id => $salesLead->name . ' (' . ($salesLead->lead?->name ?? 'Geen lead') . ')'];
                    })->toArray()"
                />
            </x-admin::form.control-group>

            

            @include('admin::orders.partials.items')
        </div>
    </x-admin::form>
</x-admin::layouts>

