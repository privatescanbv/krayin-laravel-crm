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
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.partner_products.index.create.name')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            name="name"
                            rules="required|min:1|max:255"
                            :label="trans('admin::app.settings.partner_products.index.create.name')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.name')"
                        />

                        <x-admin::form.control-group.error control-name="name" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.partner_products.index.create.currency')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="currency"
                            value="{{ old('currency', $defaultCurrency) }}"
                            rules="required"
                            :label="trans('admin::app.settings.partner_products.index.create.currency')"
                        >
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency['code'] }}" @selected(old('currency', $defaultCurrency) === $currency['code'])>{{ $currency['label'] }}</option>
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
                            rules="required"
                            :label="trans('admin::app.settings.partner_products.index.create.sales_price')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.sales_price')"
                        />

                        <x-admin::form.control-group.error control-name="sales_price" />
                    </x-admin::form.control-group>

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
                            :checked="old('active', 1)"
                        />

                        <x-admin::form.control-group.error control-name="active" />
                    </x-admin::form.control-group>
                </div>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="description"
                        :label="trans('admin::app.settings.partner_products.index.create.description')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.description')"
                    />

                    <x-admin::form.control-group.error control-name="description" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.discount_info')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="discount_info"
                        :label="trans('admin::app.settings.partner_products.index.create.discount_info')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.discount_info')"
                    />

                    <x-admin::form.control-group.error control-name="discount_info" />
                </x-admin::form.control-group>
            </div>

            <!-- Purchase Price Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.settings.partner_products.index.create.purchase_prices')
                </h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.purchase_price_misc')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="price"
                            name="purchase_price_misc"
                            value="{{ old('purchase_price_misc', '0') }}"
                            :label="trans('admin::app.settings.partner_products.index.create.purchase_price_misc')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.purchase_price_misc')"
                        />

                        <x-admin::form.control-group.error control-name="purchase_price_misc" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.purchase_price_doctor')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="price"
                            name="purchase_price_doctor"
                            value="{{ old('purchase_price_doctor', '0') }}"
                            :label="trans('admin::app.settings.partner_products.index.create.purchase_price_doctor')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.purchase_price_doctor')"
                        />

                        <x-admin::form.control-group.error control-name="purchase_price_doctor" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.purchase_price_cardiology')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="price"
                            name="purchase_price_cardiology"
                            value="{{ old('purchase_price_cardiology', '0') }}"
                            :label="trans('admin::app.settings.partner_products.index.create.purchase_price_cardiology')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.purchase_price_cardiology')"
                        />

                        <x-admin::form.control-group.error control-name="purchase_price_cardiology" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.purchase_price_clinic')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="price"
                            name="purchase_price_clinic"
                            value="{{ old('purchase_price_clinic', '0') }}"
                            :label="trans('admin::app.settings.partner_products.index.create.purchase_price_clinic')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.purchase_price_clinic')"
                        />

                        <x-admin::form.control-group.error control-name="purchase_price_clinic" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.purchase_price_royal_doctors')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="price"
                            name="purchase_price_royal_doctors"
                            value="{{ old('purchase_price_royal_doctors', '0') }}"
                            :label="trans('admin::app.settings.partner_products.index.create.purchase_price_royal_doctors')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.purchase_price_royal_doctors')"
                        />

                        <x-admin::form.control-group.error control-name="purchase_price_royal_doctors" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.purchase_price_radiology')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="price"
                            name="purchase_price_radiology"
                            value="{{ old('purchase_price_radiology', '0') }}"
                            :label="trans('admin::app.settings.partner_products.index.create.purchase_price_radiology')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.purchase_price_radiology')"
                        />

                        <x-admin::form.control-group.error control-name="purchase_price_radiology" />
                    </x-admin::form.control-group>
                </div>

                <div class="mt-4 rounded-lg border border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.settings.partner_products.index.create.purchase_price_total')
                        </span>
                        <span class="text-lg font-bold text-gray-800 dark:text-white" id="purchase-price-total">€ 0,00</span>
                    </div>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.resource_type')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="resource_type_id"
                            value="{{ old('resource_type_id') }}"
                            rules="required|numeric"
                            :label="trans('admin::app.settings.partner_products.index.create.resource_type')"
                        >
                            <option value="">@lang('admin::app.select')</option>
                            @foreach ($resourceTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('resource_type_id') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </x-admin::form.control-group.control>

                        <x-admin::form.control-group.error control-name="resource_type_id" />
                    </x-admin::form.control-group>

                <x-admin::clinic-resource-selector
                    :clinics="$clinics"
                    :selected-clinics="old('clinics', [])"
                    :selected-resources="old('resources', [])"
                />

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.clinic_description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="clinic_description"
                        value="{{ old('clinic_description') }}"
                        :label="trans('admin::app.settings.partner_products.index.create.clinic_description')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.clinic_description')"
                    />

                    <x-admin::form.control-group.error control-name="clinic_description" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.duration')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="number"
                        name="duration"
                        value="{{ old('duration') }}"
                        :label="trans('admin::app.settings.partner_products.index.create.duration')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.duration')"
                    />

                    <x-admin::form.control-group.error control-name="duration" />
                </x-admin::form.control-group>

                @php
                    $oldRelatedProducts = collect(old('related_products', []))->map(function($id) {
                        $product = \App\Models\PartnerProduct::find($id);
                        return $product ? ['id' => $product->id, 'name' => $product->name] : null;
                    })->filter()->values()->toArray();
                @endphp

                <x-admin::partner-product-lookup
                    :src="route('admin.settings.partner_products.search')"
                    name="related_products"
                    :label="trans('admin::app.settings.partner_products.index.create.related_products')"
                    :search-placeholder="trans('admin::app.settings.partner_products.index.create.search_related_products')"
                    :value="$oldRelatedProducts"
                />
            </div>
        </div>
    </x-admin::form>

@pushOnce('scripts')
    <script type="module">
        const purchasePriceFields = [
            'purchase_price_misc',
            'purchase_price_doctor',
            'purchase_price_cardiology',
            'purchase_price_clinic',
            'purchase_price_royal_doctors',
            'purchase_price_radiology'
        ];

        function parsePrice(value) {
            if (!value) return 0;
            value = value.toString().replace(/\s+/g, '').replace(',', '.');
            return parseFloat(value) || 0;
        }

        function formatPrice(value) {
            return new Intl.NumberFormat('nl-NL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        }

        function calculatePurchasePriceTotal() {
            let total = 0;
            
            purchasePriceFields.forEach(fieldName => {
                const input = document.querySelector(`input[name="${fieldName}"]`);
                if (input) {
                    total += parsePrice(input.value);
                }
            });

            const totalElement = document.getElementById('purchase-price-total');
            if (totalElement) {
                totalElement.textContent = '€ ' + formatPrice(total);
            }
        }

        // Use event delegation on document
        document.addEventListener('input', function(e) {
            const fieldName = e.target.getAttribute('name');
            if (fieldName && purchasePriceFields.includes(fieldName)) {
                calculatePurchasePriceTotal();
            }
        }, true);

        document.addEventListener('change', function(e) {
            const fieldName = e.target.getAttribute('name');
            if (fieldName && purchasePriceFields.includes(fieldName)) {
                calculatePurchasePriceTotal();
            }
        }, true);

        // Initial calculation
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(calculatePurchasePriceTotal, 300);
        });
    </script>
@endPushOnce
</x-admin::layouts>

