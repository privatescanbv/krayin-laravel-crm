@props([
    'partnerProduct' => null,
])

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
                value="{{ old('purchase_price_misc', $partnerProduct ? number_format($partnerProduct->purchase_price_misc ?? 0, 2, ',', '') : '0') }}"
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
                value="{{ old('purchase_price_doctor', $partnerProduct ? number_format($partnerProduct->purchase_price_doctor ?? 0, 2, ',', '') : '0') }}"
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
                value="{{ old('purchase_price_cardiology', $partnerProduct ? number_format($partnerProduct->purchase_price_cardiology ?? 0, 2, ',', '') : '0') }}"
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
                value="{{ old('purchase_price_clinic', $partnerProduct ? number_format($partnerProduct->purchase_price_clinic ?? 0, 2, ',', '') : '0') }}"
                :label="trans('admin::app.settings.partner_products.index.create.purchase_price_clinic')"
                :placeholder="trans('admin::app.settings.partner_products.index.create.purchase_price_clinic')"
            />

            <x-admin::form.control-group.error control-name="purchase_price_clinic" />
        </x-admin::form.control-group>


        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.settings.partner_products.index.create.purchase_price_radiology')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="price"
                name="purchase_price_radiology"
                value="{{ old('purchase_price_radiology', $partnerProduct ? number_format($partnerProduct->purchase_price_radiology ?? 0, 2, ',', '') : '0') }}"
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
            <span class="text-lg font-bold text-gray-800 dark:text-white" id="purchase-price-total">€ {{ number_format($partnerProduct->purchase_price ?? 0, 2, ',', '.') }}</span>
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script type="module">
        const purchasePriceFields = [
            'purchase_price_misc',
            'purchase_price_doctor',
            'purchase_price_cardiology',
            'purchase_price_clinic',
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

        // Initial calculation - use multiple approaches to ensure it works
        function initializePurchasePriceCalculation() {
            // Try immediate calculation
            calculatePurchasePriceTotal();
            
            // Try again after a short delay
            setTimeout(calculatePurchasePriceTotal, 100);
            setTimeout(calculatePurchasePriceTotal, 500);
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializePurchasePriceCalculation);
        } else {
            // DOM is already ready
            initializePurchasePriceCalculation();
        }
    </script>
@endPushOnce
