@php use App\Models\SalesLead; @endphp
<x-admin::layouts>
    <x-slot:title>
        Order bewerken
    </x-slot>

    <x-admin::form :action="route('admin.orders.update', ['id' => $orders->id])" method="POST">
        <input type="hidden" name="_method" value="put">

        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="orders.edit" :entity="$orders"/>

                    <div class="text-xl font-bold dark:text-gray-300">
                        Order bewerken
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
                <x-admin::form.control-group.control type="text" name="title" :value="$orders->title" rules="required"/>
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">Sales Lead</x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="select"
                    name="sales_lead_id"
                    value="{{ $orders->sales_lead_id ?? '' }}"
                    rules="required|integer|exists:salesleads,id"
                >
                    <option value="">Selecteer een Sales Lead</option>
                    @if(isset($salesLeads))
                        @foreach($salesLeads as $id => $name)
                            <option value="{{ $id }}" {{ $orders->sales_lead_id == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    @endif
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">Status</x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="select"
                    name="status"
                    value="{{ $orders->status->value ?? '' }}"
                    rules="required"
                >
                    @foreach(\App\Enums\OrderStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ $orders->status === $status ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>

            @include('admin::orders.partials.items', ['order' => $orders])
        </div>
    </x-admin::form>
</x-admin::layouts>

