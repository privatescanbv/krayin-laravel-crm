@props([
    'partnerProduct' => null,
    'resourceTypes' => [],
    'currencies' => [],
    'defaultCurrency' => 'EUR',
    'clinics' => [],
    'selectedClinics' => [],
    'selectedResources' => [],
    'relatedProducts' => [],
    'excludeId' => null,
])

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

        <x-admin::form.control-group.error control-name="name" />
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

        <x-admin::form.control-group.error control-name="duration" />
    </x-admin::form.control-group>
</div>

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

        <x-admin::form.control-group.error control-name="description" />
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

        <x-admin::form.control-group.error control-name="clinic_description" />
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
                <option value="{{ $currency['code'] }}" @selected(old('currency', $partnerProduct->currency ?? $defaultCurrency) === $currency['code'])>{{ $currency['label'] }}</option>
            @endforeach
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="currency" />
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

        <x-admin::form.control-group.error control-name="sales_price" />
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

    <x-admin::form.control-group.error control-name="discount_info" />
</x-admin::form.control-group>

<!-- Active checkbox -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.settings.partner_products.index.create.active')
    </x-admin::form.control-group.label>

    <input type="hidden" name="active" value="0" />
    <x-admin::form.control-group.control
        type="checkbox"
        name="active"
        value="1"
        :label="trans('admin::app.settings.partner_products.index.create.active')"
        :checked="old('active', $partnerProduct->active ?? 1)"
    />

    <x-admin::form.control-group.error control-name="active" />
</x-admin::form.control-group>
