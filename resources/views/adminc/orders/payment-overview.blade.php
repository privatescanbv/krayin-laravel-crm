@php
    use App\Enums\Currency;
@endphp

<x-admin::layouts>
    <x-slot:title>
        Betalingsoverzicht
    </x-slot>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Sticky header                                                        --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="flex items-center justify-between text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 backdrop-blur-md pt-4 sticky top-16 z-10">
        <div class="flex flex-col">
            <x-admin::breadcrumbs name="orders" />
            <div class="text-xl font-bold dark:text-white">Betalingsoverzicht</div>
        </div>

        <div class="flex items-center gap-x-2.5">
            {{-- Pipeline switcher --}}
            @php
                $navTabs = collect($pipelines)->map(fn ($p) => [
                    'label' => $p->name,
                    'href'  => route('admin.orders.payment-overview', ['pipeline_id' => $p->id]),
                    'id'    => $p->id,
                ]);
            @endphp
            <x-adminc::components.pipeline-nav :tabs="$navTabs" :current-id="$currentPipelineId" />

            <a href="{{ route('admin.orders.index') }}" class="secondary-button">
                Terug naar orders
            </a>

            @if ($orders->isNotEmpty())
                <button type="button" id="save-payments-btn" class="primary-button" onclick="window.savePayments()">
                    Opslaan
                </button>
            @endif
        </div>
    </div>

    {{-- Flash message --}}
    <div id="payment-overview-flash" class="hidden mt-4 rounded p-3 text-sm font-medium"></div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Summary cards                                                        --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Openstaande orders</div>
            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $orders->count() }}</div>
        </div>

        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Totaal nog open</div>
            <div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-500">
                {{ Currency::formatMoney($defaultCurrencyCode, $orders->sum(fn ($o) => round((float) ($o->total_price ?? 0) - $o->netPaidAmount(), 2))) }}
            </div>
        </div>

        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Totaalwaarde orders</div>
            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                {{ Currency::formatMoney($defaultCurrencyCode, $orders->sum(fn ($o) => (float) ($o->total_price ?? 0))) }}
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Payment grid                                                         --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="mt-4 rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 overflow-hidden">

        @if ($orders->isEmpty())
            <div class="py-16 text-center text-sm text-gray-500 dark:text-gray-400">
                <div class="mb-2 text-4xl">✓</div>
                Alle orders zijn volledig betaald.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="payment-overview-table">
                    <thead class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        <tr>
                            {{-- Read-only columns --}}
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Order nr
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Titel
                            </th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Totaal
                            </th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Reeds betaald
                            </th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-amber-600 dark:text-amber-500 uppercase tracking-wider">
                                Nog open
                            </th>

                            {{-- Editable columns (visually separated by dashed border) --}}
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider border-l-2 border-dashed border-gray-300 dark:border-gray-600">
                                Bedrag
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Type
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Methode
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Datum
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Valuta
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($orders as $index => $order)
                            @php
                                $totalPrice = round((float) ($order->total_price ?? 0), 2);
                                $netPaid    = $order->netPaidAmount();
                                $openAmount = round($totalPrice - $netPaid, 2);
                            @endphp

                            <tr class="hover:bg-blue-50/40 dark:hover:bg-gray-800/50 transition-colors">
                                {{-- Order nr --}}
                                <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    <a
                                        href="{{ route('admin.orders.view', $order->id) }}"
                                        class="hover:text-blue-600 dark:hover:text-blue-400 hover:underline"
                                        target="_blank"
                                    >
                                        {{ $order->order_number ?? '#' . $order->id }}
                                    </a>
                                </td>

                                {{-- Titel --}}
                                <td class="px-3 py-2 text-gray-900 dark:text-white max-w-[180px] truncate" title="{{ $order->title }}">
                                    {{ $order->title }}
                                </td>

                                {{-- Totaal --}}
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap tabular-nums">
                                    {{ Currency::formatMoney($defaultCurrencyCode, $totalPrice) }}
                                </td>

                                {{-- Reeds betaald --}}
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap tabular-nums">
                                    {{ Currency::formatMoney($defaultCurrencyCode, $netPaid) }}
                                </td>

                                {{-- Nog open --}}
                                <td class="px-3 py-2 text-right font-semibold text-amber-600 dark:text-amber-500 whitespace-nowrap tabular-nums">
                                    {{ Currency::formatMoney($defaultCurrencyCode, $openAmount) }}
                                </td>

                                {{-- === Editable: Bedrag === --}}
                                <td class="px-3 py-2 border-l-2 border-dashed border-gray-300 dark:border-gray-600">
                                    <input type="hidden" name="rows[{{ $index }}][order_id]" value="{{ $order->id }}">
                                    <input type="hidden" name="rows[{{ $index }}][payment_id]" value="">
                                    <input
                                        type="number"
                                        name="rows[{{ $index }}][amount]"
                                        placeholder="0.00"
                                        step="0.01"
                                        min="0"
                                        value=""
                                        class="w-28 rounded border border-gray-300 bg-white px-2 py-1 text-sm text-right tabular-nums
                                               focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                               dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                </td>

                                {{-- === Editable: Type === --}}
                                <td class="px-3 py-2">
                                    <select
                                        name="rows[{{ $index }}][type]"
                                        class="w-full rounded border border-gray-300 bg-white px-2 py-1 text-sm
                                               focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                               dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                        @foreach ($paymentTypeOptions as $option)
                                            <option
                                                value="{{ $option['value'] }}"
                                                @selected($option['value'] === 'advance')
                                            >{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                {{-- === Editable: Methode === --}}
                                <td class="px-3 py-2">
                                    <select
                                        name="rows[{{ $index }}][method]"
                                        class="w-full rounded border border-gray-300 bg-white px-2 py-1 text-sm
                                               focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                               dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                        @foreach ($paymentMethodOptions as $option)
                                            <option
                                                value="{{ $option['value'] }}"
                                                @selected($option['value'] === 'bank')
                                            >{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                {{-- === Editable: Datum === --}}
                                <td class="px-3 py-2">
                                    <input
                                        type="date"
                                        name="rows[{{ $index }}][paid_at]"
                                        value="{{ $today }}"
                                        class="rounded border border-gray-300 bg-white px-2 py-1 text-sm
                                               focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                               dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                </td>

                                {{-- === Editable: Valuta === --}}
                                <td class="px-3 py-2">
                                    <select
                                        name="rows[{{ $index }}][currency]"
                                        class="w-24 rounded border border-gray-300 bg-white px-2 py-1 text-sm
                                               focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                               dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                        @foreach ($currencyOptions as $option)
                                            <option
                                                value="{{ $option['code'] }}"
                                                @selected($option['code'] === $defaultCurrencyCode)
                                            >{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Bottom action bar --}}
            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Vul het bedrag in waar nodig en klik op <strong>Opslaan</strong>.
                    Rijen met bedrag 0 worden overgeslagen.
                </p>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            window.savePayments = function() {
                console.log('Save actie gestart via onclick!');

                var flashEl = document.getElementById('payment-overview-flash');
                var saveBtn = document.getElementById('save-payments-btn');
                var saveBtnBottom = document.getElementById('save-payments-btn-bottom');

                function showFlash(message, isError) {
                    if (!flashEl) return;
                    flashEl.textContent = message;
                    flashEl.className = isError
                        ? 'mt-4 rounded p-3 text-sm font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                        : 'mt-4 rounded p-3 text-sm font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300';
                    flashEl.classList.remove('hidden');
                    flashEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }

                function setSaving(saving) {
                    [saveBtn, saveBtnBottom].forEach(function (btn) {
                        if (!btn) return;
                        btn.disabled = saving;
                        btn.textContent = saving ? 'Opslaan...' : 'Betalingen opslaan';
                    });
                }

                var table = document.getElementById('payment-overview-table');
                var rows = [];
                if (table) {
                    table.querySelectorAll('tbody tr').forEach(function (tr) {
                        var amountEl = tr.querySelector('input[name$="[amount]"]');
                        if (!amountEl) return;

                        var amount = parseFloat(amountEl.value);
                        if (isNaN(amount) || amount <= 0) return;

                        rows.push({
                            order_id:   tr.querySelector('input[name$="[order_id]"]')?.value,
                            payment_id: tr.querySelector('input[name$="[payment_id]"]')?.value || null,
                            amount:     amountEl.value,
                            type:       tr.querySelector('select[name$="[type]"]')?.value || 'advance',
                            method:     tr.querySelector('select[name$="[method]"]')?.value || 'bank',
                            paid_at:    tr.querySelector('input[name$="[paid_at]"]')?.value || null,
                            currency:   tr.querySelector('select[name$="[currency]"]')?.value || 'EUR',
                        });
                    });
                }

                if (rows.length === 0) {
                    showFlash('Vul minimaal één bedrag groter dan 0 in.', true);
                    return;
                }

                setSaving(true);

                var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

                fetch('{{ route('admin.orders.payment-overview.save') }}', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ rows: rows }),
                })
                    .then(function (res) {
                        if (!res.ok) return res.json().then(function(b) { throw new Error(b.message || 'Fout'); });
                        return res.json();
                    })
                    .then(function (body) {
                        showFlash(body.message || 'Opgeslagen!', false);
                        setTimeout(function () { window.location.reload(); }, 2200);
                    })
                    .catch(function (err) {
                        showFlash(err.message, true);
                        setSaving(false);
                    });
            };
        </script>
    @endpush
</x-admin::layouts>
