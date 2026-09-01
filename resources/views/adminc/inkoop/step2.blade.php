<x-admin::layouts>
    <x-slot:title>Inkoop stap 2</x-slot>

    <x-admin::form :action="route('admin.inkoop.save-product-crm-ids', $invoice->id)" method="POST">
        @method('PUT')

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-gray-300">Factuurregels koppelen</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $percentageResolvedInvoiceItems }}% regels gekoppeld</div>
                    <div class="text-xs text-blue-600 dark:text-blue-400">
                        Gefilterd op kliniek: <strong>{{ $invoice->clinic?->name ?? 'Onbekend' }}</strong>
                        @if ($invoice->reference_date)
                            · Referentiemaand: <strong>{{ $invoice->reference_date->translatedFormat('F Y') }}</strong>
                            · Onderzoeksmaand: <strong>{{ $invoice->expectedExaminationMonth()->translatedFormat('F Y') }}</strong>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.inkoop.step1', $invoice->id) }}" class="secondary-button">Terug</a>
                    <button type="submit" class="primary-button">Opslaan en bijwerken</button>
                    <a href="{{ route('admin.inkoop.step3', $invoice->id) }}" class="secondary-button">Verder</a>
                </div>
            </div>

            @foreach ($persons as $person)
                @php
                    $openInvoiceItems = $person->invoiceItems->filter(fn ($item) => $item->crmProducts->isEmpty())->values();
                    $linkedInvoiceItems = $person->invoiceItems->filter(fn ($item) => $item->crmProducts->isNotEmpty())->values();
                    $uniqueOrdersForPerson = ($orderItemsByPerson[$person->id] ?? collect())
                        ->map(fn ($oi) => $oi->order)
                        ->filter()
                        ->unique('id')
                        ->sortBy('order_number')
                        ->values();
                @endphp

                <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b px-4 py-3 dark:border-gray-800">
                        <div class="font-medium text-gray-800 dark:text-gray-200">{{ trim($person->firstname . ' ' . $person->lastname) }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $openInvoiceItems->count() }} open
                            @if ($linkedInvoiceItems->isNotEmpty())
                                · {{ $linkedInvoiceItems->count() }} afgeletterd
                            @endif
                            · {{ ($orderItemsByPerson[$person->id] ?? collect())->count() }} CRM orderregels
                        </div>
                    </div>

                    @if ($openInvoiceItems->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full table-fixed text-left text-sm">
                                <thead class="bg-gray-50 text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                                    <tr>
                                        <th class="w-[24%] px-4 py-3">Factuurregel</th>
                                        <th class="w-[10%] px-4 py-3">Datum</th>
                                        <th class="w-[8%] px-4 py-3">Prijs</th>
                                        <th class="px-4 py-3">CRM product</th>
                                        <th class="w-[10%] px-4 py-3">Orders</th>
                                        <th class="w-[5%] px-4 py-3" title="Vink aan om te forceren als geheel ontvangen">Forceer GB</th>
                                        <th class="w-[7%] px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y dark:divide-gray-800">
                                    @foreach ($openInvoiceItems as $item)
                                        @php
                                            $suggested = $filteredProductsByInvoiceItemId[$person->id][$item->id] ?? null;
                                            $selected = (array) $suggested;
                                            $invoicePrice = (float) $item->price;
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $item->name ?? $item->description }}</td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ optional($item->date)->format('d-m-Y') }}</td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">€ {{ number_format($invoicePrice, 2, ',', '.') }}</td>
                                            <td class="px-4 py-3">
                                                <select
                                                    multiple
                                                    name="crm_ids[{{ $person->id }}][{{ $item->id }}][]"
                                                    class="js-inkoop-crm-select min-h-[92px] w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                                                    data-invoice-price="{{ number_format($invoicePrice, 2, '.', '') }}"
                                                    data-diff-target="inkoop-diff-{{ $item->id }}"
                                                >
                                                    @foreach (($orderItemsByPerson[$person->id] ?? collect()) as $orderItem)
                                                        @php
                                                            $orderNumber  = $orderItem->order?->order_number ?? '?';
                                                            $invoiceTotal = (float) ($orderItem->invoicePurchasePrice?->purchase_price ?? 0);
                                                            $statusLabel  = $invoiceTotal > 0 ? 'Afgeletterd' : 'Niet afgeletterd';
                                                            $inkoopprijs  = (float) $orderItem->resolvedPurchasePrice()->purchase_price;
                                                            $priceLabel   = $inkoopprijs > 0
                                                                ? '€ ' . number_format($inkoopprijs, 2, ',', '.')
                                                                : 'Geen IP';
                                                            $label = $priceLabel
                                                                . ' - ' . $orderItem->getProductName()
                                                                . ' - ' . ($orderItem->person->name ?? '-')
                                                                . ' - #' . $orderNumber
                                                                . ' - ' . $statusLabel;
                                                        @endphp
                                                        <option
                                                            value="{{ $orderItem->id }}"
                                                            data-inkoopprijs="{{ number_format($inkoopprijs, 2, '.', '') }}"
                                                            @selected(in_array($orderItem->id, $selected))
                                                        >{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <div
                                                    id="inkoop-diff-{{ $item->id }}"
                                                    class="js-inkoop-diff mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                                                    data-invoice-price="{{ number_format($invoicePrice, 2, '.', '') }}"
                                                ></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-col gap-1.5">
                                                    @foreach ($uniqueOrdersForPerson as $order)
                                                        @php $purchaseStatus = $order->purchaseStatus(); @endphp
                                                        <div class="flex flex-col gap-0.5">
                                                            <a href="{{ route('admin.orders.view', $order->id) . '#afletteren' }}"
                                                               target="_blank"
                                                               class="text-xs text-blue-600 hover:underline dark:text-blue-400">
                                                                #{{ $order->order_number }}
                                                            </a>
                                                            @if ($purchaseStatus->label())
                                                                <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $purchaseStatus->badgeClass() }}">
                                                                    {{ $purchaseStatus->label() }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    name="force_item_ids[]"
                                                    value="{{ $item->id }}"
                                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 dark:border-gray-600 dark:bg-gray-900"
                                                />
                                            </td>
                                            <td class="px-4 py-3 text-right"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-4 py-3 text-sm text-green-700 dark:text-green-400">
                            Alle factuurregels van deze patiënt zijn afgeletterd.
                        </div>
                    @endif

                    @if ($linkedInvoiceItems->isNotEmpty())
                        <div class="border-t dark:border-gray-800">
                            <div class="bg-green-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-green-800 dark:bg-green-900/20 dark:text-green-300">
                                Afgeletterd
                            </div>
                            <ul class="divide-y dark:divide-gray-800">
                                @foreach ($linkedInvoiceItems as $item)
                                    @php
                                        $crmSum = (float) $item->crmProducts->sum('purchase_price');
                                        $invoicePrice = (float) $item->price;
                                        $diff = round($invoicePrice - $crmSum, 2);
                                    @endphp
                                    <li class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium text-gray-800 dark:text-gray-200">
                                                {{ $item->name ?? $item->description }}
                                            </div>
                                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                Factuur € {{ number_format($invoicePrice, 2, ',', '.') }}
                                                · CRM som € {{ number_format($crmSum, 2, ',', '.') }}
                                                · {{ $item->crmProducts->count() }} product(en)
                                                @if (abs($diff) >= 0.01)
                                                    · <span class="font-medium text-amber-700 dark:text-amber-400">Verschil € {{ number_format($diff, 2, ',', '.') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="secondary-button shrink-0"
                                            data-reset-url="{{ route('admin.inkoop.reset-crm-id', [$invoice->id, $item->id]) }}"
                                            data-csrf="{{ csrf_token() }}"
                                            onclick="inkoopStep2Reset(this)"
                                        >Reset</button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-admin::form>
</x-admin::layouts>

<script>
window.inkoopStep2Reset = function (btn) {
    if (!confirm('Weet je zeker dat je de CRM koppeling wilt resetten?')) return;

    fetch(btn.dataset.resetUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': btn.dataset.csrf,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ _method: 'PUT' }),
    }).then(function (r) {
        if (!r.ok) throw new Error('Fout: ' + r.status);
        window.location.reload();
    }).catch(function (err) {
        alert(err.message || 'Netwerkfout. Probeer opnieuw.');
    });
};

(function () {
    function formatEur(value) {
        return '€ ' + value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function updateDiff(select) {
        var targetId = select.dataset.diffTarget;
        var target = targetId ? document.getElementById(targetId) : null;
        if (!target) return;

        var invoicePrice = parseFloat(select.dataset.invoicePrice || '0') || 0;
        var crmSum = 0;
        var selectedCount = 0;

        Array.prototype.forEach.call(select.selectedOptions, function (option) {
            crmSum += parseFloat(option.dataset.inkoopprijs || '0') || 0;
            selectedCount++;
        });

        if (selectedCount === 0) {
            target.textContent = '';
            target.className = 'js-inkoop-diff mt-1.5 text-xs text-gray-500 dark:text-gray-400';
            return;
        }

        var diff = Math.round((invoicePrice - crmSum) * 100) / 100;
        var absDiff = Math.abs(diff);
        var hasDiff = absDiff >= 0.01;

        target.innerHTML =
            'Factuur: ' + formatEur(invoicePrice) +
            ' · CRM som: ' + formatEur(crmSum) +
            ' · Verschil: ' + formatEur(diff);

        target.className = hasDiff
            ? 'js-inkoop-diff mt-1.5 text-xs font-medium text-amber-700 dark:text-amber-400'
            : 'js-inkoop-diff mt-1.5 text-xs font-medium text-green-700 dark:text-green-400';
    }

    document.querySelectorAll('.js-inkoop-crm-select').forEach(function (select) {
        select.addEventListener('change', function () {
            updateDiff(select);
        });
        updateDiff(select);
    });
})();
</script>
