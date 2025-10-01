{!! view_render_event('admin.products.view.attributes.before', ['product' => $product]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800 dark:text-white">
    <x-admin::accordion  class="select-none !border-none">
        <x-slot:header class="!p-0">
            <h4 class="font-semibold dark:text-white">
                @lang('admin::app.products.view.attributes.about-product')
            </h4>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.products.view.attributes.view.before', ['product' => $product]) !!}
    
            <!-- Product Details -->
            <div class="grid grid-cols-2 gap-2 text-sm mb-4">
                <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.products.view.currency')</div>
                <div class="dark:text-white">{{ $product->currency }}</div>

                <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.products.view.price')</div>
                <div class="dark:text-white">€ {{ number_format($product->price ?? 0, 2, ',', '.') }}</div>

                <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.products.view.costs')</div>
                <div class="dark:text-white">€ {{ number_format($product->costs ?? 0, 2, ',', '.') }}</div>

                <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.products.view.product_type')</div>
                <div class="dark:text-white">{{ $product->productType?->name ?? '-' }}</div>

                <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.products.view.resource_type')</div>
                <div class="dark:text-white">{{ $product->resourceType?->name ?? '-' }}</div>

                <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.products.view.product_group')</div>
                <div class="dark:text-white">{{ $product->productGroup?->name ?? '-' }}</div>
            </div>

            @if($product->description)
                <div class="mb-4">
                    <div class="text-gray-600 dark:text-gray-400 mb-1">@lang('admin::app.products.view.description')</div>
                    <div class="dark:text-white">{{ $product->description }}</div>
                </div>
            @endif

            <!-- Attributes Listing -->
            <div>
                <!-- Custom Attributes --> 
                <x-admin::attributes.view
                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                        'entity_type' => 'products',
                        ['code', 'NOTIN', ['price', 'costs', 'product_type_id', 'resource_type_id', 'product_group_id', 'status']]
                    ])->sortBy('sort_order')"
                    :entity="$product"
                    :url="route('admin.products.update', $product->id)"   
                    :allow-edit="true"
                />
            </div>
            
            {!! view_render_event('admin.products.view.attributes.view.after', ['product' => $product]) !!}
        </x-slot>
    </x-admin::accordion>

    @if ($product->partnerProducts && $product->partnerProducts->count() > 0)
        <x-admin::accordion class="mt-4 select-none !border-none">
            <x-slot:header class="!p-0">
                <h4 class="font-semibold dark:text-white">
                    @lang('admin::app.products.view.partner_products')
                </h4>
            </x-slot>

            <x-slot:content class="mt-4 !px-0 !pb-0">
                <div class="flex flex-col gap-2">
                    @foreach ($product->partnerProducts as $partnerProduct)
                        <a 
                            href="{{ route('admin.settings.partner_products.view', $partnerProduct->id) }}"
                            class="flex items-center justify-between rounded-lg border border-gray-200 p-3 text-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            <div class="flex flex-col gap-1">
                                <span class="font-medium text-gray-800 dark:text-white">{{ $partnerProduct->name }}</span>
                                @if($partnerProduct->description)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($partnerProduct->description, 80) }}</span>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-800 dark:text-white">€ {{ number_format($partnerProduct->sales_price, 2, ',', '.') }}</div>
                                @if($partnerProduct->active)
                                    <span class="text-xs text-green-600 dark:text-green-400">@lang('admin::app.common.active')</span>
                                @else
                                    <span class="text-xs text-red-600 dark:text-red-400">@lang('admin::app.common.inactive')</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </x-slot>
        </x-admin::accordion>
    @endif
</div>

{!! view_render_event('admin.products.view.attributes.before', ['product' => $product]) !!}