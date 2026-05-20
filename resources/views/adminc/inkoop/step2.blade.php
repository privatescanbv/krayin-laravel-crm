<x-admin::layouts>
    <x-slot:title>Inkoop stap 2</x-slot>

    <x-admin::form :action="route('admin.inkoop.save-product-crm-ids', $invoice->id)" method="POST">
        @method('PUT')

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-gray-300">Factuurregels koppelen</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $percentageResolvedInvoiceItems }}% regels gekoppeld</div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.inkoop.step1', $invoice->id) }}" class="secondary-button">Terug</a>
                    <button type="submit" class="primary-button">Opslaan en bijwerken</button>
                    <a href="{{ route('admin.inkoop.step3', $invoice->id) }}" class="secondary-button">Verder</a>
                </div>
            </div>

            @foreach ($persons as $person)
                <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b px-4 py-3 dark:border-gray-800">
                        <div class="font-medium text-gray-800 dark:text-gray-200">{{ trim($person->firstname . ' ' . $person->lastname) }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ ($orderItemsByPerson[$person->id] ?? collect())->count() }} CRM orderregels</div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                                <tr>
                                    <th class="px-4 py-3">Factuurregel</th>
                                    <th class="px-4 py-3">Datum</th>
                                    <th class="px-4 py-3">Prijs</th>
                                    <th class="px-4 py-3">CRM product</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-800">
                                @foreach ($person->invoiceItems as $item)
                                    @php
                                        $suggested = $filteredProductsByInvoiceItemId[$person->id][$item->id] ?? null;
                                        $selected = $item->crmProducts->pluck('crm_id')->all() ?: (array) $suggested;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $item->name ?? $item->description }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ optional($item->date)->format('d-m-Y') }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">€ {{ number_format((float) $item->price, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3">
                                            <select multiple name="crm_ids[{{ $person->id }}][{{ $item->id }}][]" class="min-h-[92px] w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                                                @foreach (($orderItemsByPerson[$person->id] ?? collect()) as $orderItem)
                                                    @php
                                                        $productName = $orderItem->product->name ?? $orderItem->name ?? 'Onbekend product';
                                                        $label = '€ ' . number_format((float) $orderItem->total_price, 2, ',', '.') . ' - ' . $productName . ' - ' . ($orderItem->person->name ?? '-');
                                                    @endphp
                                                    <option value="{{ $orderItem->id }}" @selected(in_array($orderItem->id, $selected))>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if ($item->crmProducts->isNotEmpty())
                                                <button formaction="{{ route('admin.inkoop.reset-crm-id', [$invoice->id, $item->id]) }}" formmethod="POST" name="_method" value="PUT" class="secondary-button">Reset</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin::form>
</x-admin::layouts>
