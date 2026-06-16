<x-admin::layouts>
    <x-slot:title>Inkoop stap 3</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-gray-300">Inkoop afronden</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $percentageResolvedPersons }}% patienten, {{ $percentageResolvedInvoiceItems }}% regels gekoppeld</div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.inkoop.step2', $invoice->id) }}" class="secondary-button">Terug</a>
                <x-admin::form :action="route('admin.inkoop.mark-as-processed', $invoice->id)" method="POST">
                    @method('PUT')
                    <button type="submit" class="primary-button">Markeer als verwerkt</button>
                </x-admin::form>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Factuur</div>
                <div class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $invoice->name ?? $invoice->filename }}</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Status</div>
                <div class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $invoice->status?->label() ?? $invoice->status }}</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Open regels</div>
                <div class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $unprocessedItems->count() }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Regels</th>
                        <th class="px-4 py-3">CRM orderregels</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-800">
                    @foreach ($persons as $person)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ trim($person->firstname . ' ' . $person->lastname) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $person->invoiceItems->count() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1 text-gray-600 dark:text-gray-400">
                                    @if (empty($person->crm_id))
                                        <span class="text-xs text-orange-500 dark:text-orange-400">
                                            Niet gekoppeld aan CRM — ga terug naar stap 1
                                        </span>
                                    @else
                                        @forelse ($crmOrderItemsByPerson[$person->id] ?? collect() as $orderItem)
                                            <div class="flex items-center gap-1 text-sm">
                                                @if ($orderItem->order)
                                                    <a href="{{ route('admin.orders.view', $orderItem->order->id) }}"
                                                       target="_blank"
                                                       class="text-xs text-blue-600 hover:underline dark:text-blue-400 shrink-0">
                                                        #{{ $orderItem->order->order_number }}
                                                    </a>
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                                <span>{{ $orderItem->product->name ?? $orderItem->name ?? 'Onbekend product' }}</span>
                                            </div>
                                        @empty
                                            <span class="text-xs text-gray-400 dark:text-gray-500">Geen orderregels voor deze kliniek</span>
                                        @endforelse
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin::layouts>
