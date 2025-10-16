@php use App\Enums\Currency;use App\Helpers\ProductHelper;use App\Models\ProductType;use App\Models\ResourceType;use Webkul\Product\Models\ProductGroup; @endphp
@props([
    'product' => null,
    'selectedPartnerProducts' => [],
])

@php
    $currencies = Currency::options();
    $defaultCurrency = Currency::default()->value;
    $productTypes = ProductType::orderBy('name')->get(['id', 'name']);
    $resourceTypes = ResourceType::orderBy('name')->get(['id', 'name']);
    $productGroups = ProductGroup::with('parent.parent.parent.parent.parent')->orderBy('name')->get();
@endphp

    <!-- Naam -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label class="required">
        @lang('admin::app.products.create.name')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.control
        type="text"
        name="name"
        value="{{ old('name', $product->name ?? '') }}"
        rules="required|max:255"
        :label="trans('admin::app.products.create.name')"
        :placeholder="trans('admin::app.products.create.name')"
    />

    <x-admin::form.control-group.error control-name="name"/>
</x-admin::form.control-group>

<!-- Omschrijving -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.products.create.description')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.control
        type="textarea"
        name="description"
        value="{{ old('description', $product->description ?? '') }}"
        :label="trans('admin::app.products.create.description')"
        :placeholder="trans('admin::app.products.create.description')"
    />

    <x-admin::form.control-group.error control-name="description"/>
</x-admin::form.control-group>

<!-- Grid met 3 kolommen: Valuta, Kosten, Verkoopprijs -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.settings.partner_products.index.create.currency')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="select"
            name="currency"
            value="{{ old('currency', $product->currency ?? $defaultCurrency) }}"
            rules="required"
            :label="trans('admin::app.settings.partner_products.index.create.currency')"
        >
            @foreach ($currencies as $currency)
                <option
                    value="{{ $currency['code'] }}" @selected(old('currency', $product->currency ?? $defaultCurrency) === $currency['code'])>{{ $currency['label'] }}</option>
            @endforeach
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="currency"/>
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('admin::app.products.create.costs')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="price"
            name="costs"
            value="{{ old('costs', $product && $product->costs ? number_format($product->costs, 2, ',', '') : '') }}"
            :label="trans('admin::app.products.create.costs')"
            :placeholder="trans('admin::app.products.create.costs')"
        />

        <x-admin::form.control-group.error control-name="costs"/>
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.products.create.price')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="price"
            name="price"
            value="{{ old('price', $product && $product->price ? number_format($product->price, 2, ',', '') : '') }}"
            rules="required"
            :label="trans('admin::app.products.create.price')"
            :placeholder="trans('admin::app.products.create.price')"
        />

        <x-admin::form.control-group.error control-name="price"/>
    </x-admin::form.control-group>
</div>

<!-- Active checkbox -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.settings.partner_products.index.create.active')
    </x-admin::form.control-group.label>

    <input type="hidden" name="active" value="0"/>
    <x-admin::form.control-group.control
        type="checkbox"
        name="active"
        value="1"
        :label="trans('admin::app.settings.partner_products.index.create.active')"
        :checked="old('active', $product->active ?? 1)"
    />

    <x-admin::form.control-group.error control-name="active"/>
</x-admin::form.control-group>

<!-- Grid met 2 kolommen voor types en groep -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">

    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('admin::app.products.create.product_type')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="select"
            name="product_type_id"
            value="{{ old('product_type_id', $product->product_type_id ?? '') }}"
            :label="trans('admin::app.products.create.product_type')"
        >
            <option value="">@lang('admin::app.select')</option>
            @foreach ($productTypes as $type)
                <option
                    value="{{ $type->id }}" @selected(old('product_type_id', $product->product_type_id ?? '') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="product_type_id"/>
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.products.create.resource_type')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="select"
            name="resource_type_id"
            value="{{ old('resource_type_id', $product->resource_type_id ?? '') }}"
            rules="required|integer"
            :label="trans('admin::app.products.create.resource_type')"
        >
            <option value="">@lang('admin::app.select')</option>
            @foreach ($resourceTypes as $type)
                <option
                    value="{{ $type->id }}" @selected(old('resource_type_id', $product->resource_type_id ?? '') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="resource_type_id"/>
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.products.create.product_group')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="select"
            name="product_group_id"
            value="{{ old('product_group_id', $product->product_group_id ?? '') }}"
            rules="required|integer"
            :label="trans('admin::app.products.create.product_group')"
        >
            <option value="">@lang('admin::app.select')</option>
            @foreach ($productGroups as $group)
                <option
                    value="{{ $group->id }}" @selected(old('product_group_id', $product->product_group_id ?? '') == $group->id)>{{ $group->path }}</option>
            @endforeach
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="product_group_id"/>
    </x-admin::form.control-group>
</div>

<!-- Attributes -->
{!! view_render_event('admin.products.' . ($product ? 'edit' : 'create') . '.attributes.before', ['product' => $product]) !!}

<x-admin::attributes
    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
        'entity_type' => 'products',
        ['code', 'NOTIN', ['name', 'description', 'price', 'costs', 'product_type_id', 'resource_type_id', 'product_group_id']],
    ])"
    :entity="$product"
/>

{!! view_render_event('admin.products.' . ($product ? 'edit' : 'create') . '.attributes.after', ['product' => $product]) !!}

<!-- Partner Products Selection -->
<x-admin::partner-product-lookup
    :src="route('admin.settings.partner_products.search')"
    name="partner_products"
    :label="trans('admin::app.products.create.partner_products')"
    :search-placeholder="trans('admin::app.products.create.search_partner_products')"
    :value="$selectedPartnerProducts"
/>
