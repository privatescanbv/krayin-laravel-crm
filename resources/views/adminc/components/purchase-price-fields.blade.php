@props([
    'purchasePrice' => null,
    'fieldPrefix'   => '',
    'title'         => 'admin::app.partner_products.index.create.purchase_prices',
    'totalLabel'    => 'admin::app.partner_products.index.create.purchase_price_total',
    'totalId'       => 'purchase-price-total',
])

@php
    $suffixes = ['misc', 'doctor', 'cardiology', 'clinic', 'radiology'];
@endphp

<div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">
        {{ trans($title) }}
    </h3>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ($suffixes as $suffix)
            @php
                $fieldName = $fieldPrefix . 'purchase_price_' . $suffix;
                $fieldValue = old($fieldName, number_format($purchasePrice?->{'purchase_price_' . $suffix} ?? 0, 2, ',', ''));
                $transKey = 'admin::app.partner_products.index.create.' . $fieldPrefix . 'purchase_price_' . $suffix;
            @endphp

            <x-adminc::components.field
                type="price"
                name="{{ $fieldName }}"
                value="{{ $fieldValue }}"
                :label="trans($transKey)"
                :placeholder="trans($transKey)"
            />
        @endforeach
    </div>

    <div class="mt-4 rounded-lg border bg-neutral-100 p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between">
            <span class="font-semibold text-gray-800 dark:text-white">
                {{ trans($totalLabel) }}
            </span>
            <span class="text-lg font-bold text-gray-800 dark:text-white" id="{{ $totalId }}">€ {{ number_format($purchasePrice?->purchase_price ?? 0, 2, ',', '.') }}</span>
        </div>
    </div>
</div>

@push('scripts')
    <script type="module">
        const fields = [
            @foreach ($suffixes as $suffix)
            '{{ $fieldPrefix }}purchase_price_{{ $suffix }}',
            @endforeach
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

        function calculateTotal() {
            let total = 0;

            fields.forEach(fieldName => {
                const input = document.querySelector(`input[name="${fieldName}"]`);
                if (input) {
                    total += parsePrice(input.value);
                }
            });

            const totalElement = document.getElementById('{{ $totalId }}');
            if (totalElement) {
                totalElement.textContent = '€ ' + formatPrice(total);
            }
        }

        document.addEventListener('input', function(e) {
            const fieldName = e.target.getAttribute('name');
            if (fieldName && fields.includes(fieldName)) {
                calculateTotal();
            }
        }, true);

        document.addEventListener('change', function(e) {
            const fieldName = e.target.getAttribute('name');
            if (fieldName && fields.includes(fieldName)) {
                calculateTotal();
            }
        }, true);

        function initialize() {
            calculateTotal();
            setTimeout(calculateTotal, 100);
            setTimeout(calculateTotal, 500);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initialize);
        } else {
            initialize();
        }
    </script>
@endpush
