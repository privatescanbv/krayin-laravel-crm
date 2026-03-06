@props([
    'partnerProduct' => null,
    'selectedClinics' => [],
    'selectedResources' => [],
    'relatedProducts' => [],
    'excludeId' => null,
    'templateProductId' => null,
])

@php use App\Enums\Currency;use App\Enums\ProductReports;use App\Helpers\ProductHelper;use App\Models\PartnerProduct;use App\Models\ResourceType;use App\Repositories\ClinicRepository; @endphp
@php
    $resourceTypes = ResourceType::orderBy('name')->get(['id', 'name']);
    $currencies = Currency::options();
    $defaultCurrency = Currency::default()->value;
    $clinics = app(ClinicRepository::class)->allActive(['id', 'name']);
    $reportingOptions = ProductReports::getOptions();
@endphp

    <!-- Hidden field for product_id -->
@if ($templateProductId)
    <input type="hidden" name="product_id" value="{{ $templateProductId }}"/>
@elseif ($partnerProduct && $partnerProduct->product_id)
    <input type="hidden" name="product_id" value="{{ old('product_id', $partnerProduct->product_id) }}"/>
@endif

<!-- Naam en Duur -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <x-adminc::components.field
        type="text"
        name="name"
        value="{{ old('name', $partnerProduct->name ?? '') }}"
        rules="required|min:1|max:255"
        :label="trans('admin::app.partner_products.index.create.name')"
        :placeholder="trans('admin::app.partner_products.index.create.name')"
    />

    <x-adminc::components.field
        type="number"
        name="duration"
        value="{{ old('duration', $partnerProduct->duration ?? '') }}"
        :label="trans('admin::app.partner_products.index.create.duration')"
        :placeholder="trans('admin::app.partner_products.index.create.duration')"
    />
</div>

<!-- Associated Product (Readonly) -->
@if ($partnerProduct && $partnerProduct->product)
<x-adminc::components.field
    type="textarea"
    name="description"
    label="Omschrijving"
    value="{{ old('description', $partnerProduct->description ?? '') }}"
    :placeholder="trans('admin::app.partner_products.index.create.description')"
/>
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.partner_products.index.create.associated_product')
    </x-admin::form.control-group.label>
    <div
        class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        {{ ProductHelper::formatNameWithPathLazy($partnerProduct->product) }}
    </div>
</x-admin::form.control-group>
@endif

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <x-adminc::components.field
        type="select"
        name="currency"
        value="{{ old('currency', $partnerProduct->currency ?? $defaultCurrency) }}"
        rules="required"
        :label="trans('admin::app.partner_products.index.create.currency')"
    >
        @foreach ($currencies as $currency)
            <option
                value="{{ $currency['code'] }}" @selected(old('currency', $partnerProduct->currency ?? $defaultCurrency) === $currency['code'])>{{ $currency['label'] }}</option>
        @endforeach
    </x-adminc::components.field>

    <x-adminc::components.field
        type="textarea"
        name="clinic_description"
        value="{{ old('clinic_description', $partnerProduct->clinic_description ?? '') }}"
        :label="trans('admin::app.partner_products.index.create.clinic_description')"
        :placeholder="trans('admin::app.partner_products.index.create.clinic_description')"
    />
</div>

<div class="space-y-4">

    <!-- Kortingsinformatie -->
    <x-adminc::components.field
        type="textarea"
        name="discount_info"
        value="{{ old('discount_info', $partnerProduct->discount_info ?? '') }}"
        :label="trans('admin::app.partner_products.index.create.discount_info')"
        :placeholder="trans('admin::app.partner_products.index.create.discount_info')"
    />
</div>

<!-- Active checkbox -->
<x-adminc::components.field
    type="switch"
    name="active"
    value="1"
    :checked="(bool) old('active', $partnerProduct->active ?? true)"
    :label="trans('admin::app.partner_products.index.create.active')"
/>

<!-- Resource Type -->
<x-adminc::components.field
    type="select"
    name="resource_type_id"
    value="{{ old('resource_type_id', $partnerProduct->resource_type_id ?? '') }}"
    rules="required|numeric"
    :label="trans('admin::app.partner_products.index.create.resource_type')"
>
    <option value="">@lang('admin::app.select')</option>
    @foreach ($resourceTypes as $type)
        <option
            value="{{ $type->id }}" @selected(old('resource_type_id', $partnerProduct->resource_type_id ?? '') == $type->id)>{{ $type->name }}</option>
    @endforeach
</x-adminc::components.field>

<!-- Clinics and Resources -->
<x-admin::clinic-resource-selector
    :clinics="$clinics"
    :selected-clinics="$selectedClinics"
    :selected-resources="$selectedResources"
/>

<!-- Related Products -->
<x-admin::form.control-group>
    @include('adminc.components.product-selector')

    @php
        // Map relatedProducts to include name_with_path if available
        // Convert to array if it's a Collection, then map to selector format
        $relatedProductsForSelector = collect($relatedProducts ?? [])
            ->map(function($pp) {
                // Ensure we have an array with id/name keys
                if (is_array($pp) && isset($pp['id'])) {
                    return [
                        'id' => $pp['id'] ?? null,
                        'name' => $pp['name'] ?? '',
                        'name_with_path' => $pp['name_with_path'] ?? $pp['name'] ?? '',
                    ];
                }
                return null;
            })
            ->filter(fn($p) => $p !== null && !empty($p['id']))
            ->values()
            ->toArray();
    @endphp

    <v-product-selector
        name="related_products"
        placeholder="{{ trans('admin::app.partner_products.index.create.search_related_products') }}"
        search-route="{{ route('admin.partner_products.search') }}"
        :can-add-new="false"
        :multiple="true"
        :items='@json($relatedProductsForSelector)'
    />
    <x-admin::form.control-group.label>
       @lang('admin::app.partner_products.index.create.related_products')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.error control-name="related_products"/>

</x-admin::form.control-group>

<!-- Reporting (moved below Related Products) -->
<x-admin::form.control-group>

{{--    <x-admin::form.control-group.label>--}}
{{--        @lang('admin::app.partner_products.index.create.reporting')--}}
{{--    </x-admin::form.control-group.label>--}}
    <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
        @lang('admin::app.partner_products.index.create.reporting')
    </div>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @php
            $selectedReporting = PartnerProduct::normalizeReporting(old('reporting', $partnerProduct->reporting ?? []));
        @endphp
        @foreach ($reportingOptions as $value => $label)
            <div class="flex items-center">
                <input
                    type="checkbox"
                    id="reporting_{{ $value }}"
                    name="reporting[]"
                    value="{{ $value }}"
                    class="h-4 w-4 flex-none rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                    @checked(in_array($value, $selectedReporting, true))
                />
                <label for="reporting_{{ $value }}" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $label }}
                </label>
            </div>
        @endforeach
    </div>

    <x-admin::form.control-group.error control-name="reporting"/>

</x-admin::form.control-group>
