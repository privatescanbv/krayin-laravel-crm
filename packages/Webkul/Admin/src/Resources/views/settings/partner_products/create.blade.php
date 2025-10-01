<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.partner_products.index.create.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.partner_products.store')" method="POST">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.partner_products.create" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.partner_products.index.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.partner_products.index.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @php
                    $oldRelatedProducts = collect(old('related_products', []))->map(function($id) {
                        $product = \App\Models\PartnerProduct::find($id);
                        return $product ? ['id' => $product->id, 'name' => $product->name] : null;
                    })->filter()->values()->toArray();
                @endphp

                <x-admin::partner-product-form-fields
                    :selected-clinics="old('clinics', [])"
                    :selected-resources="old('resources', [])"
                    :related-products="$oldRelatedProducts"
                />
            </div>

            <x-admin::partner-product-purchase-prices />
        </div>
    </x-admin::form>
</x-admin::layouts>

