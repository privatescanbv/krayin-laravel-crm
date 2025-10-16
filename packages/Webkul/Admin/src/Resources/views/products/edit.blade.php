
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.products.edit.title')
    </x-slot>

    {!! view_render_event('admin.products.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.products.update', $product->id)"
        encType="multipart/form-data"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs
                        name="products.edit"
                        :entity="$product"
                     />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.products.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.products.edit.create_button.before', ['product' => $product]) !!}

                        <!-- Edit button for Product -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.products.create.save-btn')
                        </button>

                        {!! view_render_event('admin.products.edit.create_button.after', ['product' => $product]) !!}
                    </div>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @if ($errors->any())
                    <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.products.create.general')
                </p>

                @php
                    $selectedPartnerProducts = $product->partnerProducts->map(function($p) {
                        $clinicNames = $p->clinics->pluck('name')->join(', ');
                        return [
                            'id' => $p->id, 
                            'name' => $clinicNames ? $clinicNames . ' - ' . $p->name : $p->name
                        ];
                    })->toArray();
                @endphp
                <x-admin::product-form-fields
                    :product="$product"
                    :selected-partner-products="$selectedPartnerProducts"
                />
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.products.edit.form.after') !!}
</x-admin::layouts>
