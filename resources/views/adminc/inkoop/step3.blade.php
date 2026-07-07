@php use App\Enums\OrderPurchaseStatus; @endphp
<x-admin::layouts>
    <x-slot:title>Inkoop stap 3</x-slot>

    <div class="flex flex-col gap-4">
        <div
            class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-gray-300">Inkoop afronden</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $percentageResolvedPersons }}%
                    patienten, {{ $percentageResolvedInvoiceItems }}% regels gekoppeld
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.inkoop.step2', $invoice->id) }}" class="secondary-button">Terug</a>
                <x-admin::form :action="route('admin.inkoop.mark-as-processed', $invoice->id)" method="POST">
                    @method('PUT')
                    <button type="submit" class="primary-button">Markeer als verwerkt</button>
                </x-admin::form>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Factuur</div>
                <div
                    class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $invoice->name ?? $invoice->filename }}</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Factuur status</div>
                <div
                    class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $invoice->status?->label() ?? $invoice->status }}</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Open regels</div>
                <div class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $unprocessedItems->count() }}</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Kliniek</div>
                <div
                    class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $invoice->clinic?->name ?? '—' }}</div>
            </div>
        </div>

        <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 text-sm dark:border-orange-800 dark:bg-orange-900/20">
            <div class="flex items-start gap-2">
                <span class="icon-warning mt-0.5 shrink-0 text-base text-orange-600 dark:text-orange-400"></span>
                <div>
                    <div class="font-semibold text-orange-800 dark:text-orange-200">Onderstaande factuurregels zijn niet automatisch gekoppeld aan het CRM.</div>
                    <div class="mt-1 text-orange-700 dark:text-orange-300">Controleer de gegevens en verwerk deze regels handmatig in het CRM vóór je de factuur markeert als verwerkt.</div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Ongematchte factuurregel(s)</th>
                        <th class="px-4 py-3">Datum</th>
                        <th class="px-4 py-3">Prijs</th>
                        <th class="px-4 py-3">CRM orderregel</th>
                        <th class="px-4 py-3">Order status</th>
                        <th class="px-4 py-3">Orderregel status</th>
                        <th class="px-4 py-3">Afletter status</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-800">
                @foreach ($persons as $person)
                    @php
                        $personOrderItems    = $crmOrderItemsByPerson[$person->id] ?? collect();
                        $unmatchedOrderItems = $personOrderItems->filter(fn ($oi) => !isset($invoiceDataByOrderItemId[$oi->id]));
                        $matchedCount        = $personOrderItems->count() - $unmatchedOrderItems->count();
                        $totalCount          = $personOrderItems->count();
                        $unmatchedInvItems   = $person->invoiceItems->filter(fn ($ii) => $ii->crmProducts->isEmpty());
                    @endphp

                    {{-- Persoon niet gekoppeld aan CRM --}}
                    @if (empty($person->crm_id))
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                {{ trim($person->firstname . ' ' . $person->lastname) }}
                            </td>
                            <td class="px-4 py-3" colspan="7">
                                <span class="text-xs text-orange-500 dark:text-orange-400">Niet gekoppeld aan CRM — ga terug naar stap 1</span>
                            </td>
                        </tr>

                    {{-- Geen ongematchte orderregels: sla deze persoon over --}}
                    @elseif ($unmatchedOrderItems->isEmpty() && $unmatchedInvItems->isEmpty())
                        {{-- Alles gekoppeld, niets te tonen --}}

                    @else
                        @foreach ($unmatchedOrderItems as $orderItem)
                            @php
                                $orderItemPurchaseStatus = $orderItemPurchaseStatuses[$orderItem->id] ?? null;
                                $orderPurchaseStatus     = $orderPurchaseStatuses[$orderItem->order_id] ?? null;
                                $orderItemStatus         = $orderItem->status;
                                $crmPurchasePrice        = $orderItem->purchasePrice?->purchase_price;

                                $invItemsToShow = $unmatchedInvItems;
                            @endphp
                            <tr class="bg-orange-50 dark:bg-orange-900/10">
                                {{-- Patient + hoeveel er al gekoppeld zijn --}}
                                <td class="px-4 py-3 align-top font-medium text-gray-800 dark:text-gray-200">
                                    @if ($loop->first)
                                        <div>{{ trim($person->firstname . ' ' . $person->lastname) }}</div>
                                        @if ($totalCount > 0)
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $matchedCount }}/{{ $totalCount }} orderregel(s) gekoppeld
                                            </div>
                                        @endif
                                    @endif
                                </td>

                                {{-- Ongematchte factuurregels van deze persoon als context --}}
                                <td class="px-4 py-3 align-top">
                                    @if ($loop->first)
                                        @forelse ($invItemsToShow as $invItem)
                                            <div class="text-sm text-gray-700 dark:text-gray-300">{{ $invItem->name ?? $invItem->description ?? '—' }}</div>
                                        @empty
                                            <span class="text-xs text-gray-400 dark:text-gray-500">Geen open factuurregels</span>
                                        @endforelse
                                    @endif
                                </td>

                                {{-- Datum van eerste ongematchte factuurregel --}}
                                <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-400">
                                    @if ($loop->first)
                                        @foreach ($invItemsToShow as $invItem)
                                            <div>{{ $invItem->date?->format('d-m-Y') ?? '—' }}</div>
                                        @endforeach
                                    @endif
                                </td>

                                {{-- Prijs van ongematchte factuurregels --}}
                                <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-400">
                                    @if ($loop->first)
                                        @foreach ($invItemsToShow as $invItem)
                                            <div>€&nbsp;{{ number_format((float) $invItem->price, 2, ',', '.') }}</div>
                                        @endforeach
                                    @endif
                                </td>

                                {{-- CRM orderregel: ordernummer + product + inkoopprijs --}}
                                <td class="px-4 py-3 align-top">
                                    <div class="flex flex-wrap items-center gap-1.5 text-sm">
                                        @if ($orderItem->order)
                                            <a href="{{ route('admin.orders.view', $orderItem->order->id) }}#afletteren"
                                               target="_blank"
                                               class="text-xs text-blue-600 hover:underline dark:text-blue-400 shrink-0">
                                                #{{ $orderItem->order->order_number }}
                                            </a>
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                        <span class="text-gray-700 dark:text-gray-300">{{ $orderItem->product->name ?? $orderItem->name ?? 'Onbekend product' }}</span>
                                        @if ($crmPurchasePrice !== null)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">€&nbsp;{{ number_format((float) $crmPurchasePrice, 2, ',', '.') }}</span>
                                        @endif
                                        @if ($orderItemPurchaseStatus && $orderItemPurchaseStatus !== OrderPurchaseStatus::HIDDEN)
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $orderItemPurchaseStatus->badgeClass() }}">
                                                {{ $orderItemPurchaseStatus->label() }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Order status (pipeline stage van de order) --}}
                                <td class="px-4 py-3 align-top">
                                    @if ($orderItem->order?->stage)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $orderItem->order->stage->name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Orderregel status --}}
                                <td class="px-4 py-3 align-top">
                                    @if ($orderItemStatus)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $orderItemStatus->badgeClass() }}">
                                            {{ $orderItemStatus->label() }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Afletter status --}}
                                <td class="px-4 py-3 align-top">
                                    @if ($orderPurchaseStatus && $orderPurchaseStatus !== OrderPurchaseStatus::HIDDEN)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $orderPurchaseStatus->badgeClass() }}">
                                            {{ $orderPurchaseStatus->label() }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        {{-- Ongematchte factuurregels zonder CRM koppeling: toon alle factuurgegevens voor handmatige verwerking --}}
                        @if ($unmatchedInvItems->isNotEmpty() && $unmatchedOrderItems->isEmpty())
                            @foreach ($unmatchedInvItems as $invItem)
                                <tr class="bg-orange-50 dark:bg-orange-900/10">
                                    <td class="px-4 py-3 align-top font-medium text-gray-800 dark:text-gray-200">
                                        @if ($loop->first)
                                            <div>{{ trim($person->firstname . ' ' . $person->lastname) }}</div>
                                            @if ($totalCount > 0)
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $matchedCount }}/{{ $totalCount }} orderregel(s) gekoppeld
                                                </div>
                                            @endif
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">
                                        <div>{{ $invItem->name ?? $invItem->description ?? '—' }}</div>
                                        @if ($invItem->description && $invItem->name && $invItem->description !== $invItem->name)
                                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $invItem->description }}</div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-400">
                                        {{ $invItem->date?->format('d-m-Y') ?? '—' }}
                                    </td>

                                    <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-400">
                                        @if ($invItem->quantity && $invItem->unit_price)
                                            <div>{{ rtrim(rtrim(number_format((float) $invItem->quantity, 2, ',', '.'), '0'), ',') }} × €&nbsp;{{ number_format((float) $invItem->unit_price, 2, ',', '.') }}</div>
                                        @endif
                                        <div class="font-medium text-gray-800 dark:text-gray-200">
                                            €&nbsp;{{ number_format((float) ($invItem->total_price ?? $invItem->price ?? 0), 2, ',', '.') }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 align-top" colspan="4">
                                        <span class="inline-flex items-center gap-1 text-xs text-orange-600 dark:text-orange-400">
                                            <span class="icon-warning text-sm"></span>
                                            Geen CRM-match — handmatig verwerken
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin::layouts>
