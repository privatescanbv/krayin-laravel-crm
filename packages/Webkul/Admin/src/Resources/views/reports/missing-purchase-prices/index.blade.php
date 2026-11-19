<x-admin::layouts>
    <x-slot:title>
        Orders zonder inkoopprijs
    </x-slot>

    <div class="mb-5 grid gap-1.5">
        <p class="text-2xl font-semibold dark:text-white">Orders zonder inkoopprijs</p>
        <p class="text-gray-600 dark:text-gray-300">
            Orders met gewonnen orderregels waarvan alle inkoopprijzen 0 zijn ({{ $orders->count() }}). Tijdelijk overzicht.
        </p>
    </div>

    <form method="GET" class="mb-4 flex items-center gap-2">
        <label for="planbaar" class="text-sm dark:text-gray-300">Planbare producten:</label>
        <select id="planbaar" name="planbaar" onchange="this.form.submit()" class="rounded-lg border bg-white px-3 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <option value="ja" @selected($filter === 'ja')>Planbaar</option>
            <option value="nee" @selected($filter === 'nee')>Niet planbaar</option>
            <option value="alles" @selected($filter === 'alles')>Alles</option>
        </select>

        <label for="sugar" class="ml-4 text-sm dark:text-gray-300">Sugar CRM:</label>
        <select id="sugar" name="sugar" onchange="this.form.submit()" class="rounded-lg border bg-white px-3 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <option value="beide" @selected($sugar === 'beide')>Beide</option>
            <option value="import" @selected($sugar === 'import')>Import Sugar CRM</option>
            <option value="geen" @selected($sugar === 'geen')>Geen import</option>
        </select>
    </form>

    <div class="overflow-x-auto rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
        <table class="w-full text-left text-sm dark:text-gray-300">
            <thead class="border-b bg-neutral-bg dark:border-gray-800 dark:bg-gray-950">
                <tr>
                    <th class="p-3">Order</th>
                    <th class="p-3">Titel</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Aangemaakt</th>
                    <th class="p-3">Regels</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-b last:border-0 dark:border-gray-800">
                        <td class="p-3">
                            <a class="text-brandColor hover:underline" href="{{ route('admin.orders.view', $order->id) }}">
                                {{ $order->order_number ?: '#'.$order->id }}
                            </a>
                        </td>
                        <td class="p-3">{{ $order->title }}</td>
                        <td class="p-3">{{ $order->status }}</td>
                        <td class="p-3">{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d-m-Y H:i') }}</td>
                        <td class="p-3">{{ $order->items_count }}</td>
                    </tr>
                @empty
                    <tr><td class="p-3" colspan="5">Geen orders gevonden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts>
