@props([
    'partnerProduct' => null,
])

<div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">
        @lang('admin::app.partner_products.index.create.related_purchase_prices')
    </h3>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <x-admin::form.control-group>
            <x-admin::form.control-group.control
                type="price"
                name="rel_purchase_price_misc"
                value="{{ old('rel_purchase_price_misc', $partnerProduct ? number_format($partnerProduct->rel_purchase_price_misc ?? 0, 2, ',', '') : '0') }}"
                :label="trans('admin::app.partner_products.index.create.rel_purchase_price_misc')"
                :placeholder="trans('admin::app.partner_products.index.create.rel_purchase_price_misc')"
            />

            <x-admin::form.control-group.error control-name="rel_purchase_price_misc" />

            <x-admin::form.control-group.label>
                @lang('admin::app.partner_products.index.create.rel_purchase_price_misc')
            </x-admin::form.control-group.label>
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.control
                type="price"
                name="rel_purchase_price_doctor"
                value="{{ old('rel_purchase_price_doctor', $partnerProduct ? number_format($partnerProduct->rel_purchase_price_doctor ?? 0, 2, ',', '') : '0') }}"
                :label="trans('admin::app.partner_products.index.create.rel_purchase_price_doctor')"
                :placeholder="trans('admin::app.partner_products.index.create.rel_purchase_price_doctor')"
            />

            <x-admin::form.control-group.error control-name="rel_purchase_price_doctor" />

            <x-admin::form.control-group.label>
                @lang('admin::app.partner_products.index.create.rel_purchase_price_doctor')
            </x-admin::form.control-group.label>
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.control
                type="price"
                name="rel_purchase_price_cardiology"
                value="{{ old('rel_purchase_price_cardiology', $partnerProduct ? number_format($partnerProduct->rel_purchase_price_cardiology ?? 0, 2, ',', '') : '0') }}"
                :label="trans('admin::app.partner_products.index.create.rel_purchase_price_cardiology')"
                :placeholder="trans('admin::app.partner_products.index.create.rel_purchase_price_cardiology')"
            />

            <x-admin::form.control-group.error control-name="rel_purchase_price_cardiology" />

            <x-admin::form.control-group.label>
                @lang('admin::app.partner_products.index.create.rel_purchase_price_cardiology')
            </x-admin::form.control-group.label>
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.control
                type="price"
                name="rel_purchase_price_clinic"
                value="{{ old('rel_purchase_price_clinic', $partnerProduct ? number_format($partnerProduct->rel_purchase_price_clinic ?? 0, 2, ',', '') : '0') }}"
                :label="trans('admin::app.partner_products.index.create.rel_purchase_price_clinic')"
                :placeholder="trans('admin::app.partner_products.index.create.rel_purchase_price_clinic')"
            />

            <x-admin::form.control-group.error control-name="rel_purchase_price_clinic" />

            <x-admin::form.control-group.label>
                @lang('admin::app.partner_products.index.create.rel_purchase_price_clinic')
            </x-admin::form.control-group.label>
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.control
                type="price"
                name="rel_purchase_price_radiology"
                value="{{ old('rel_purchase_price_radiology', $partnerProduct ? number_format($partnerProduct->rel_purchase_price_radiology ?? 0, 2, ',', '') : '0') }}"
                :label="trans('admin::app.partner_products.index.create.rel_purchase_price_radiology')"
                :placeholder="trans('admin::app.partner_products.index.create.rel_purchase_price_radiology')"
            />

            <x-admin::form.control-group.error control-name="rel_purchase_price_radiology" />

            <x-admin::form.control-group.label>
                @lang('admin::app.partner_products.index.create.rel_purchase_price_radiology')
            </x-admin::form.control-group.label>
        </x-admin::form.control-group>
    </div>

    <div class="mt-4 rounded-lg border bg-neutral-100 p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between">
            <span class="font-semibold text-gray-800 dark:text-white">
                @lang('admin::app.partner_products.index.create.rel_purchase_price_total')
            </span>
            <span class="text-lg font-bold text-gray-800 dark:text-white" id="rel-purchase-price-total">€ {{ number_format($partnerProduct->rel_purchase_price ?? 0, 2, ',', '.') }}</span>
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script type="module">
        const relPurchasePriceFields = [
            'rel_purchase_price_misc',
            'rel_purchase_price_doctor',
            'rel_purchase_price_cardiology',
            'rel_purchase_price_clinic',
            'rel_purchase_price_radiology'
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

        function calculateRelPurchasePriceTotal() {
            let total = 0;

            relPurchasePriceFields.forEach(fieldName => {
                const input = document.querySelector(`input[name="${fieldName}"]`);
                if (input) {
                    total += parsePrice(input.value);
                }
            });

            const totalElement = document.getElementById('rel-purchase-price-total');
            if (totalElement) {
                totalElement.textContent = '€ ' + formatPrice(total);
            }
        }

        // Use event delegation on document
        document.addEventListener('input', function(e) {
            const fieldName = e.target.getAttribute('name');
            if (fieldName && relPurchasePriceFields.includes(fieldName)) {
                calculateRelPurchasePriceTotal();
            }
        }, true);

        document.addEventListener('change', function(e) {
            const fieldName = e.target.getAttribute('name');
            if (fieldName && relPurchasePriceFields.includes(fieldName)) {
                calculateRelPurchasePriceTotal();
            }
        }, true);

        // Initial calculation - use multiple approaches to ensure it works
        function initializeRelPurchasePriceCalculation() {
            // Try immediate calculation
            calculateRelPurchasePriceTotal();

            // Try again after a short delay
            setTimeout(calculateRelPurchasePriceTotal, 100);
            setTimeout(calculateRelPurchasePriceTotal, 500);
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeRelPurchasePriceCalculation);
        } else {
            // DOM is already ready
            initializeRelPurchasePriceCalculation();
        }
    </script>
@endPushOnce
