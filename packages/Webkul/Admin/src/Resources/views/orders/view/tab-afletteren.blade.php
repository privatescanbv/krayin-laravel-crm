@props(['order'])

@php
    $priceFields = [
        'purchase_price_misc'       => 'Overig',
        'purchase_price_doctor'     => 'Arts',
        'purchase_price_cardiology' => 'Cardiologie',
        'purchase_price_clinic'     => 'Kliniek',
        'purchase_price_radiology'  => 'Radiologie',
    ];

    $asAmount = function ($value): float {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    };

    $round2 = fn (float $v): float => round($v, 2);

    $formatEur = function (float $value): string {
        return '€ ' . number_format($value, 2, ',', '.');
    };

    $statusFor = function (float $purchaseTotal, float $invoiceTotal) use ($round2): string {
        $p = $round2($purchaseTotal);
        $i = $round2($invoiceTotal);

        if ($p <= 0 && $i <= 0) {
            return 'VERBERG';
        }

        if ($i > 0 && $p > 0) {
            return $i === $p ? 'Geheel ontvangen' : 'Gedeeltelijk ontvangen';
        }

        if ($i <= 0 && $p > 0) {
            return 'Niet ontvangen';
        }

        if ($i > 0 && $p <= 0) {
            return 'Invoice zonder inkoopprijs';
        }

        return 'Onbekend';
    };

    $badgeClass = function (string $status): string {
        return match ($status) {
            'Geheel ontvangen' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
            'Gedeeltelijk ontvangen' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
            'Niet ontvangen' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200',
            'Invoice zonder inkoopprijs' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        };
    };

    $rows = [];
    $summary = [
        'purchase_total' => 0.0,
        'invoice_total'  => 0.0,
        'diff_total'     => 0.0,
        'counts'         => [],
    ];

    foreach (($order->orderItems ?? collect()) as $item) {
        $purchaseTotal = $asAmount($item->purchasePrice?->purchase_price);
        $invoiceTotal  = $asAmount($item->invoicePurchasePrice?->purchase_price);
        $status = $statusFor($purchaseTotal, $invoiceTotal);

        if ($status === 'VERBERG') {
            continue;
        }

        $diff = $invoiceTotal - $purchaseTotal;

        $summary['purchase_total'] += $purchaseTotal;
        $summary['invoice_total']  += $invoiceTotal;
        $summary['diff_total']     += $diff;
        $summary['counts'][$status] = ($summary['counts'][$status] ?? 0) + 1;

        $rows[] = [
            'item'          => $item,
            'purchaseTotal' => $purchaseTotal,
            'invoiceTotal'  => $invoiceTotal,
            'diff'          => $diff,
            'status'        => $status,
        ];
    }

    $summary['purchase_total'] = $round2($summary['purchase_total']);
    $summary['invoice_total']  = $round2($summary['invoice_total']);
    $summary['diff_total']     = $round2($summary['diff_total']);
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Afletteren</h3>
        </div>
        <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Overzicht van inkoop vs invoice (daadwerkelijk betaald) per order item.
        </div>
    </div>

    @if (count($rows) === 0)
        <div class="rounded-lg border bg-white p-6 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            Geen items met inkoop- of invoicebedrag (beide 0 wordt niet getoond).
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Inkoop totaal</div>
                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $formatEur($summary['purchase_total']) }}</div>
            </div>

            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Invoice totaal</div>
                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $formatEur($summary['invoice_total']) }}</div>
            </div>

            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Verschil (invoice - inkoop)</div>
                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $formatEur($summary['diff_total']) }}</div>
            </div>

            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Statussen</div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($summary['counts'] as $label => $count)
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClass($label) }}">
                            <span>{{ $label }}</span>
                            <span class="rounded bg-white/60 px-1.5 py-0.5 text-[11px] font-bold text-gray-700 dark:bg-black/20 dark:text-gray-100">
                                {{ $count }}
                            </span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Product</th>
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Persoon</th>
                            <th class="px-2 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Inkoop</th>
                            <th class="px-2 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Invoice</th>
                            <th class="px-2 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Verschil</th>
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                /** @var \App\Models\OrderItem $item */
                                $item = $row['item'];
                                $purchaseTotal = $row['purchaseTotal'];
                                $invoiceTotal = $row['invoiceTotal'];
                                $diff = $row['diff'];
                                $status = $row['status'];

                                $detailsRows = [];
                                foreach ($priceFields as $field => $label) {
                                    $p = $asAmount($item->purchasePrice?->{$field});
                                    $i = $asAmount($item->invoicePurchasePrice?->{$field});

                                    if ($round2($p) <= 0 && $round2($i) <= 0) {
                                        continue;
                                    }

                                    $detailsRows[] = [
                                        'label' => $label,
                                        'purchase' => $p,
                                        'invoice' => $i,
                                        'diff' => $i - $p,
                                    ];
                                }
                            @endphp

                            <tr class="border-b border-gray-100 align-top dark:border-gray-800">
                                <td class="px-2 py-3 text-gray-900 dark:text-white">
                                    {{ $item->getProductName() ?: '-' }}
                                </td>
                                <td class="px-2 py-3 text-gray-900 dark:text-white">
                                    {{ $item->person?->name ?? '-' }}
                                </td>
                                <td class="px-2 py-3 text-right text-gray-900 dark:text-white">
                                    {{ $formatEur($round2($purchaseTotal)) }}
                                </td>
                                <td class="px-2 py-3 text-right text-gray-900 dark:text-white">
                                    {{ $formatEur($round2($invoiceTotal)) }}
                                </td>
                                <td class="px-2 py-3 text-right text-gray-900 dark:text-white">
                                    {{ $formatEur($round2($diff)) }}
                                </td>
                                <td class="px-2 py-3">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClass($status) }}">
                                        {{ $status }}
                                    </span>
                                </td>
                                <td class="px-2 py-3">
                                    @if (count($detailsRows) === 0)
                                        <span class="text-xs text-gray-400">—</span>
                                    @else
                                        <details class="rounded-md border border-gray-200 bg-gray-50 p-2 dark:border-gray-800 dark:bg-gray-950/30">
                                            <summary class="cursor-pointer select-none text-xs font-medium text-gray-700 dark:text-gray-200">
                                                Uitsplitsing
                                            </summary>
                                            <div class="mt-2 overflow-x-auto">
                                                <table class="w-full text-xs">
                                                    <thead>
                                                        <tr class="border-b border-gray-200 dark:border-gray-800">
                                                            <th class="py-1 text-left font-medium text-gray-500 dark:text-gray-400">Onderdeel</th>
                                                            <th class="py-1 text-right font-medium text-gray-500 dark:text-gray-400">Inkoop</th>
                                                            <th class="py-1 text-right font-medium text-gray-500 dark:text-gray-400">Invoice</th>
                                                            <th class="py-1 text-right font-medium text-gray-500 dark:text-gray-400">Verschil</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($detailsRows as $dr)
                                                            <tr class="border-b border-gray-100 dark:border-gray-900">
                                                                <td class="py-1 text-gray-800 dark:text-gray-200">{{ $dr['label'] }}</td>
                                                                <td class="py-1 text-right text-gray-800 dark:text-gray-200">{{ $formatEur($round2($dr['purchase'])) }}</td>
                                                                <td class="py-1 text-right text-gray-800 dark:text-gray-200">{{ $formatEur($round2($dr['invoice'])) }}</td>
                                                                <td class="py-1 text-right text-gray-800 dark:text-gray-200">{{ $formatEur($round2($dr['diff'])) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
