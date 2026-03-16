@props(['order'])

@php
    use App\Enums\Currency;
    use App\Enums\PaymentType;

    $paymentTypeOptions = PaymentType::options();
    $currencyOptions = Currency::options();
    $defaultCurrencyCode = Currency::default()->value;
    $today = now()->format('Y-m-d');

    $totalToPay = (float) ($order->total_price ?? 0);
    $totalPaid = round((float) $order->payments->sum('amount'), 2);
    $openAmount = round($totalToPay - $totalPaid, 2);
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Klant betalingen</h3>
        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Aanbetaling, kliniekbetaling en terugbetalingen voor deze order.
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Totaal te betalen</div>
            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                @if ($totalToPay > 0)
                    {{ Currency::formatMoney($defaultCurrencyCode, $totalToPay) }}
                @else
                    —
                @endif
            </div>
        </div>
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Reeds betaald</div>
            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                {{ Currency::formatMoney($defaultCurrencyCode, $totalPaid) }}
            </div>
        </div>
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Nog open</div>
            <div class="mt-1 text-lg font-semibold {{ $openAmount > 0 ? 'text-amber-600 dark:text-amber-500' : 'text-gray-900 dark:text-white' }}">
                @if ($totalToPay > 0)
                    {{ Currency::formatMoney($defaultCurrencyCode, $openAmount) }}
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    <div id="order-payments-{{ $order->id }}" class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-4 flex items-center justify-between">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Beheer betalingen gekoppeld aan deze order.
            </div>

            <button
                type="button"
                id="order-payments-add-{{ $order->id }}"
                class="primary-button"
                @disabled(! bouncer()->hasPermission('orders.edit'))
            >
                + Betaling toevoegen
            </button>
        </div>

        <div
            id="order-payments-form-{{ $order->id }}"
            class="mb-4 hidden rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800"
        >
            <h4
                id="order-payments-form-title-{{ $order->id }}"
                class="mb-3 text-sm font-semibold text-gray-900 dark:text-white"
            >
                Nieuwe betaling
            </h4>

            <div
                id="order-payments-error-{{ $order->id }}"
                class="hidden mb-3 rounded bg-red-50 p-2 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-300"
            ></div>

            <form id="order-payments-form-element-{{ $order->id }}">
                @csrf

                <input type="hidden" name="payment_id" value="">

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <x-adminc::components.field
                        label="Bedrag *"
                        name="amount"
                        type="number"
                        rules="required|numeric|min:0"
                        step="0.01"
                        min="0"
                        class="w-full"
                    />

                    <x-adminc::components.field
                        label="Type *"
                        name="type"
                        type="select"
                        rules="required"
                        class="w-full"
                    >
                        @foreach ($paymentTypeOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-adminc::components.field>

                    <x-adminc::components.field
                        label="Methode *"
                        name="method"
                        type="select"
                        rules="required"
                        class="w-full"
                    >
                        <option value="pin">Pin</option>
                        <option value="cash">Contant</option>
                        <option value="creditcard">Creditcard</option>
                    </x-adminc::components.field>

                    <x-adminc::components.field
                        label="Datum"
                        name="paid_at"
                        type="date"
                        value="{{ $today }}"
                        class="w-full"
                    />

                    <x-adminc::components.field
                        label="Valuta"
                        name="currency"
                        type="select"
                        class="w-full"
                    >
                        @foreach ($currencyOptions as $option)
                            <option value="{{ $option['code'] }}" @selected($option['code'] === $defaultCurrencyCode)>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </x-adminc::components.field>
                </div>

                <div class="mt-4 flex gap-2">
                    <button
                        type="submit"
                        id="order-payments-save-{{ $order->id }}"
                        class="primary-button"
                    >
                        Opslaan
                    </button>

                    <button
                        type="button"
                        id="order-payments-cancel-{{ $order->id }}"
                        class="secondary-button"
                    >
                        Annuleren
                    </button>
                </div>
            </form>
        </div>

        @php
            $payments = $order->payments;
        @endphp

        @if ($payments->isEmpty())
            <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Nog geen betalingen geregistreerd.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Bedrag</th>
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Methode</th>
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Datum</th>
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Valuta</th>
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            @php
                                /** @var \App\Models\OrderPayment $payment */
                                $typeValue = $payment->type instanceof PaymentType ? $payment->type->value : ($payment->type ?? $payment->getRawOriginal('type'));
                                $typeLabel = $payment->type instanceof PaymentType
                                    ? $payment->type->label()
                                    : (PaymentType::tryFrom($typeValue)?->label() ?? $typeValue);

                                $methodLabels = [
                                    'pin'         => 'Pin',
                                    'cash'        => 'Contant',
                                    'creditcard'  => 'Creditcard',
                                ];

                                $methodLabel = $methodLabels[$payment->method] ?? $payment->method;

                                $currencyCode = $payment->currency ?: $defaultCurrencyCode;
                                $displayAmount = Currency::formatMoney($currencyCode, (float) $payment->amount);
                            @endphp

                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-2 py-2 font-medium text-gray-900 dark:text-white">
                                    {{ $displayAmount }}
                                </td>
                                <td class="px-2 py-2 text-gray-700 dark:text-gray-300">
                                    {{ $typeLabel }}
                                </td>
                                <td class="px-2 py-2 text-gray-700 dark:text-gray-300">
                                    {{ $methodLabel }}
                                </td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-400">
                                    {{ optional($payment->paid_at)->format('Y-m-d') ?? '—' }}
                                </td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-400">
                                    {{ $currencyCode }}
                                </td>
                                <td class="px-2 py-2 whitespace-nowrap">
                                    <button
                                        type="button"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center icon-edit"
                                        title="Betaling bewerken"
                                        data-role="payment-edit"
                                        data-id="{{ $payment->id }}"
                                        data-amount="{{ $payment->amount }}"
                                        data-type="{{ $typeValue }}"
                                        data-method="{{ $payment->method }}"
                                        data-paid-at="{{ optional($payment->paid_at)->format('Y-m-d') }}"
                                        data-currency="{{ $currencyCode }}"
                                    >
                                        <span class="sr-only">Bewerken</span>
                                    </button>

                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center icon-delete"
                                        title="Betaling verwijderen"
                                        data-role="payment-delete"
                                        data-id="{{ $payment->id }}"
                                    >
                                        <span class="sr-only">Verwijderen</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@pushOnce('scripts')
<script>
(function () {
    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    window.initOrderPaymentsTab = window.initOrderPaymentsTab || function (orderId) {
        var containerId = 'order-payments-' + orderId;
        var container = document.getElementById(containerId);

        window.__orderPaymentsInit = window.__orderPaymentsInit || {};

        if (!container || window.__orderPaymentsInit[containerId]) {
            return;
        }

        window.__orderPaymentsInit[containerId] = true;

        var today = '{{ $today }}';

        var formWrapper = document.getElementById('order-payments-form-{{ $order->id }}');
        var formElement = document.getElementById('order-payments-form-element-{{ $order->id }}');
        var titleEl = document.getElementById('order-payments-form-title-{{ $order->id }}');
        var errorEl = document.getElementById('order-payments-error-{{ $order->id }}');
        var addBtn = document.getElementById('order-payments-add-{{ $order->id }}');
        var saveBtn = document.getElementById('order-payments-save-{{ $order->id }}');
        var cancelBtn = document.getElementById('order-payments-cancel-{{ $order->id }}');

        if (!formWrapper || !formElement || !addBtn || !saveBtn || !cancelBtn) {
            return;
        }

        function openForm(mode, payment) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');

            var paymentIdInput = formElement.querySelector('input[name="payment_id"]');
            var amountInput = formElement.querySelector('input[name="amount"]');
            var typeSelect = formElement.querySelector('select[name="type"]');
            var methodSelect = formElement.querySelector('select[name="method"]');
            var paidAtInput = formElement.querySelector('input[name="paid_at"]');
            var currencySelect = formElement.querySelector('select[name="currency"]');

            if (mode === 'edit' && payment) {
                if (titleEl) titleEl.textContent = 'Betaling bewerken';
                if (paymentIdInput) paymentIdInput.value = payment.id || '';
                if (amountInput) amountInput.value = payment.amount || '';
                if (typeSelect) typeSelect.value = payment.type || '';
                if (methodSelect) methodSelect.value = payment.method || '';
                if (paidAtInput) paidAtInput.value = payment.paid_at || '';
                if (currencySelect) currencySelect.value = payment.currency || '';
            } else {
                if (titleEl) titleEl.textContent = 'Nieuwe betaling';
                if (paymentIdInput) paymentIdInput.value = '';
                if (amountInput) amountInput.value = '';
                if (typeSelect) typeSelect.selectedIndex = 0;
                if (methodSelect) methodSelect.selectedIndex = 0;
                if (paidAtInput) paidAtInput.value = today;
                if (currencySelect && currencySelect.value === '') {
                    var selected = currencySelect.querySelector('option[selected]');
                    currencySelect.value = selected ? selected.value : '{{ $defaultCurrencyCode }}';
                }
            }

            formWrapper.classList.remove('hidden');
        }

        function closeForm() {
            formWrapper.classList.add('hidden');
        }

        function showError(message) {
            if (!errorEl) return;

            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }

        addBtn.addEventListener('click', function () {
            openForm('create', null);
        });

        cancelBtn.addEventListener('click', function () {
            closeForm();
        });

        container.querySelectorAll('[data-role="payment-edit"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openForm('edit', {
                    id: btn.getAttribute('data-id'),
                    amount: btn.getAttribute('data-amount'),
                    type: btn.getAttribute('data-type'),
                    method: btn.getAttribute('data-method'),
                    paid_at: btn.getAttribute('data-paid-at'),
                    currency: btn.getAttribute('data-currency')
                });
            });
        });

        container.querySelectorAll('[data-role="payment-delete"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                if (!id) return;

                if (!confirm('Betaling verwijderen?')) {
                    return;
                }

                fetch('/admin/orders/' + {{ $order->id }} + '/payments/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf(),
                        'Accept': 'application/json'
                    }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Fout: ' + response.status);
                    }

                    window.location.reload();
                }).catch(function () {
                    alert('Kon betaling niet verwijderen. Probeer opnieuw.');
                });
            });
        });

        formElement.addEventListener('submit', function (event) {
            event.preventDefault();

            var paymentIdInput = formElement.querySelector('input[name="payment_id"]');
            var amountInput = formElement.querySelector('input[name="amount"]');
            var typeSelect = formElement.querySelector('select[name="type"]');
            var methodSelect = formElement.querySelector('select[name="method"]');
            var paidAtInput = formElement.querySelector('input[name="paid_at"]');
            var currencySelect = formElement.querySelector('select[name="currency"]');

            var payload = {
                amount: amountInput ? amountInput.value : null,
                type: typeSelect ? typeSelect.value : null,
                method: methodSelect ? methodSelect.value : null,
                paid_at: paidAtInput ? (paidAtInput.value || null) : null,
                currency: currencySelect ? (currencySelect.value || null) : null
            };

            var paymentId = paymentIdInput ? paymentIdInput.value : '';
            var url = '/admin/orders/' + {{ $order->id }} + '/payments' + (paymentId ? '/' + paymentId : '');
            var method = paymentId ? 'PUT' : 'POST';

            saveBtn.disabled = true;
            saveBtn.textContent = 'Opslaan...';

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            }).then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (body) {
                        var message = body.message || 'Er is een fout opgetreden (' + response.status + ').';

                        if (body.errors) {
                            var firstError = Object.values(body.errors)[0];
                            if (Array.isArray(firstError) && firstError.length) {
                                message = firstError[0];
                            }
                        }

                        throw new Error(message);
                    });
                }

                return response.json();
            }).then(function () {
                window.location.reload();
            }).catch(function (error) {
                showError(error.message || 'Netwerkfout. Probeer opnieuw.');
            }).finally(function () {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Opslaan';
            });
        });
    };
})();
</script>
@endPushOnce
