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
<x-adminc::components.field
    type="text"
    name="name"
    value="{{ old('name', $product->name ?? '') }}"
    rules="required|max:255"
    :label="trans('admin::app.products.create.name')"
    :placeholder="trans('admin::app.products.create.name')"
/>

<!-- Omschrijving -->
<x-adminc::components.field
    type="textarea"
    name="description"
    value="{{ old('description', $product->description ?? '') }}"
    :label="trans('admin::app.products.create.description')"
    :placeholder="trans('admin::app.products.create.description')"
/>

<!-- Grid met 3 kolommen: Valuta, Kosten, Verkoopprijs -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
    <x-adminc::components.field
        type="select"
        name="currency"
        value="{{ old('currency', $product->currency ?? $defaultCurrency) }}"
        rules="required"
        :label="trans('admin::app.partner_products.index.create.currency')"
    >
        @foreach ($currencies as $currency)
            <option
                value="{{ $currency['code'] }}" @selected(old('currency', $product->currency ?? $defaultCurrency) === $currency['code'])>{{ $currency['label'] }}</option>
        @endforeach
    </x-adminc::components.field>

    <x-adminc::components.field
        type="price"
        name="costs"
        value="{{ old('costs', $product && $product->costs ? number_format($product->costs, 2, ',', '') : '') }}"
        :label="trans('admin::app.products.create.costs')"
        :placeholder="trans('admin::app.products.create.costs')"
    />

    <x-adminc::components.field
        type="price"
        name="price"
        value="{{ old('price', $product && $product->price ? number_format($product->price, 2, ',', '') : '') }}"
        rules="required"
        :label="trans('admin::app.products.create.price')"
        :placeholder="trans('admin::app.products.create.price')"
    />
</div>

<!-- Active checkbox -->
<x-adminc::components.field
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
</x-adminc::components.field>

    <x-adminc::components.field
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
    </x-adminc::components.field>

    <x-adminc::components.field
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
    </x-adminc::components.field>
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

<x-admin::form.control-group>

    @include('adminc.components.entity-selector')

    <v-entity-selector
        name="partner_products"
        label="Partner products"
        placeholder="Selecteer .."
        search-route="{{ route('admin.partner_products.search') }}"
        :can-add-new="true"
        :multiple="true"
        :items='@json($selectedPartnerProducts ?? [])'
        item-edit-route="{{ rtrim(route('admin.partner_products.edit', ['id' => 0]), '0') }}{id}"
    />
    <input type="hidden" name="active" value="0"/>
    <x-admin::form.control-group.control
        type="checkbox"
        name="active"
        value="1"
        :label="trans('admin::app.partner_products.index.create.active')"
        :checked="old('active', $product->active ?? 1)"
    />
    <x-admin::form.control-group.label>
        @lang('admin::app.partner_products.index.create.active')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.error control-name="partner_products"/>

    <x-admin::form.control-group.error control-name="active"/>

</x-admin::form.control-group>

