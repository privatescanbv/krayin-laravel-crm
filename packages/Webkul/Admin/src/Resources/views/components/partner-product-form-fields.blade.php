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

    <!-- Hidden field for product_id (template product) -->
@if($templateProductId)
    <input type="hidden" name="product_id" value="{{ $templateProductId }}"/>
@endif

<!-- Naam en Duur -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.settings.partner_products.index.create.name')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="text"
            name="name"
            value="{{ old('name', $partnerProduct->name ?? '') }}"
            rules="required|min:1|max:255"
            :label="trans('admin::app.settings.partner_products.index.create.name')"
            :placeholder="trans('admin::app.settings.partner_products.index.create.name')"
        />

        <x-admin::form.control-group.error control-name="name"/>
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('admin::app.settings.partner_products.index.create.duration')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="number"
            name="duration"
            value="{{ old('duration', $partnerProduct->duration ?? '') }}"
            :label="trans('admin::app.settings.partner_products.index.create.duration')"
            :placeholder="trans('admin::app.settings.partner_products.index.create.duration')"
        />

        <x-admin::form.control-group.error control-name="duration"/>
    </x-admin::form.control-group>
</div>

<!-- Associated Product (Readonly) -->
@if($partnerProduct && $partnerProduct->product)
    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('admin::app.settings.partner_products.index.create.associated_product')
        </x-admin::form.control-group.label>

        <div
            class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            {{ ProductHelper::formatNameWithPathLazy($partnerProduct->product) }}
        </div>
    </x-admin::form.control-group>
@endif

<!-- Omschrijving en Omschrijving kliniek -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('admin::app.settings.partner_products.index.create.description')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="textarea"
            name="description"
            value="{{ old('description', $partnerProduct->description ?? '') }}"
            :label="trans('admin::app.settings.partner_products.index.create.description')"
            :placeholder="trans('admin::app.settings.partner_products.index.create.description')"
        />

        <x-admin::form.control-group.error control-name="description"/>
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('admin::app.settings.partner_products.index.create.clinic_description')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="textarea"
            name="clinic_description"
            value="{{ old('clinic_description', $partnerProduct->clinic_description ?? '') }}"
            :label="trans('admin::app.settings.partner_products.index.create.clinic_description')"
            :placeholder="trans('admin::app.settings.partner_products.index.create.clinic_description')"
        />

        <x-admin::form.control-group.error control-name="clinic_description"/>
    </x-admin::form.control-group>
</div>

<!-- Valuta en Verkoopprijs -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.settings.partner_products.index.create.currency')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="select"
            name="currency"
            value="{{ old('currency', $partnerProduct->currency ?? $defaultCurrency) }}"
            rules="required"
            :label="trans('admin::app.settings.partner_products.index.create.currency')"
        >
            @foreach ($currencies as $currency)
                <option
                    value="{{ $currency['code'] }}" @selected(old('currency', $partnerProduct->currency ?? $defaultCurrency) === $currency['code'])>{{ $currency['label'] }}</option>
            @endforeach
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="currency"/>
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('admin::app.settings.partner_products.index.create.sales_price')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="price"
            name="sales_price"
            value="{{ old('sales_price', $partnerProduct ? number_format($partnerProduct->sales_price, 2, ',', '') : '') }}"
            rules="required"
            :label="trans('admin::app.settings.partner_products.index.create.sales_price')"
            :placeholder="trans('admin::app.settings.partner_products.index.create.sales_price')"
        />

        <x-admin::form.control-group.error control-name="sales_price"/>
    </x-admin::form.control-group>
</div>

<!-- Gerelateerde verkoopprijs -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('admin::app.settings.partner_products.index.create.related_sales_price')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="price"
            name="related_sales_price"
            value="{{ old('related_sales_price', $partnerProduct ? number_format($partnerProduct->related_sales_price, 2, ',', '') : '') }}"
            :label="trans('admin::app.settings.partner_products.index.create.related_sales_price')"
            :placeholder="trans('admin::app.settings.partner_products.index.create.related_sales_price')"
        />

        <x-admin::form.control-group.error control-name="related_sales_price"/>
    </x-admin::form.control-group>
</div>

<!-- Kortingsinformatie -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.settings.partner_products.index.create.discount_info')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.control
        type="textarea"
        name="discount_info"
        value="{{ old('discount_info', $partnerProduct->discount_info ?? '') }}"
        :label="trans('admin::app.settings.partner_products.index.create.discount_info')"
        :placeholder="trans('admin::app.settings.partner_products.index.create.discount_info')"
    />

    <x-admin::form.control-group.error control-name="discount_info"/>
</x-admin::form.control-group>

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
        :checked="old('active', $partnerProduct->active ?? 1)"
    />

    <x-admin::form.control-group.error control-name="active"/>
</x-admin::form.control-group>

<!-- Resource Type -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.settings.partner_products.index.create.resource_type')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.control
        type="select"
        name="resource_type_id"
        value="{{ old('resource_type_id', $partnerProduct->resource_type_id ?? '') }}"
        rules="required|numeric"
        :label="trans('admin::app.settings.partner_products.index.create.resource_type')"
    >
        <option value="">@lang('admin::app.select')</option>
        @foreach ($resourceTypes as $type)
            <option
                value="{{ $type->id }}" @selected(old('resource_type_id', $partnerProduct->resource_type_id ?? '') == $type->id)>{{ $type->name }}</option>
        @endforeach
    </x-admin::form.control-group.control>

    <x-admin::form.control-group.error control-name="resource_type_id"/>
</x-admin::form.control-group>

<!-- Clinics and Resources -->
<x-admin::clinic-resource-selector
    :clinics="$clinics"
    :selected-clinics="$selectedClinics"
    :selected-resources="$selectedResources"
/>

<!-- Related Products -->
<x-admin::partner-product-lookup
    :src="route('admin.settings.partner_products.search')"
    name="related_products"
    :label="trans('admin::app.settings.partner_products.index.create.related_products')"
    :search-placeholder="trans('admin::app.settings.partner_products.index.create.search_related_products')"
    :value="$relatedProducts"
    :exclude-id="$excludeId"
/>

<!-- Reporting (moved below Related Products) -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.settings.partner_products.index.create.reporting')
    </x-admin::form.control-group.label>

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
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
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
