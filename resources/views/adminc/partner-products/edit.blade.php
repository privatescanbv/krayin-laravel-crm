<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.partner_products.index.edit.title')
    </x-slot>

    <x-admin::form :action="route('admin.partner_products.update', $partner_products->id)" method="POST">
        @method('PUT')

        <!-- Hidden fields for return navigation -->
        @if (request('return_to') === 'clinic_view' && request('clinic_id'))
            <input type="hidden" name="return_to" value="clinic_view">
            <input type="hidden" name="clinic_id" value="{{ request('clinic_id') }}">
        @endif
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="partner_products.edit" :entity="$partner_products" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.partner_products.index.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.partner_products.index.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @php
                    $selectedClinics = old('clinics', $partner_products->clinics->pluck('id')->toArray());
                    $selectedResources = old('resources', $partner_products->resources->pluck('id')->toArray());
                @endphp

                <x-adminc::partner-products.partner-product-form-fields
                    :partner-product="$partner_products"
                    :selected-clinics="$selectedClinics"
                    :selected-resources="$selectedResources"
                    :related-products="$partner_products->relatedProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->toArray()"
                    :exclude-id="$partner_products->id"
                />
            </div>

            <x-adminc::partner-products.partner-product-purchase-prices :partner-product="$partner_products" />

            <!-- Related Purchase Prices -->
            <x-adminc::partner-products.partner-product-related-purchase-prices :partner-product="$partner_products" />

        </div>
    </x-admin::form>
</x-admin::layouts>

