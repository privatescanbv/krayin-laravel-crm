@props(['clinic'])

<div class="flex w-full flex-col gap-4 rounded-lg">
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">AFB verzendhistorie</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Overzicht van batch- en individuele AFB verzendingen inclusief status, mail en bijlagen.
        </p>
    </div>

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        @php
            $dispatches = $clinic->afbDispatches->sortByDesc('created_at')->values();
        @endphp

        @if ($dispatches->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Nog geen AFB verzendingen geregistreerd voor deze kliniek.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-2 py-2 text-left">Datum/tijd</th>
                        <th class="px-2 py-2 text-left">Type</th>
                        <th class="px-2 py-2 text-left">Afdeling</th>
                        <th class="px-2 py-2 text-left">Status</th>
                        <th class="px-2 py-2 text-left">Orders / patienten</th>
                        <th class="px-2 py-2 text-left">Mail log</th>
                        <th class="px-2 py-2 text-left">Bijlagen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($dispatches as $dispatch)
                        <tr class="align-top border-b border-gray-100 dark:border-gray-800">
                            <td class="px-2 py-2 whitespace-nowrap">
                                {{ $dispatch->sent_at?->format('d-m-Y H:i') ?? $dispatch->created_at?->format('d-m-Y H:i') ?? '-' }}
                            </td>
                            <td class="px-2 py-2">
                                {{ $dispatch->type?->value ?? '-' }}
                            </td>
                            <td class="px-2 py-2">
                                {{ $dispatch->clinicDepartment->name ?? '-' }}
                            </td>
                            <td class="px-2 py-2">
                                @if (($dispatch->status?->value ?? null) === 'success')
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                        success
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
                                        failed
                                    </span>
                                    @if ($dispatch->error_message)
                                        <div class="mt-1 text-xs text-red-600">{{ $dispatch->error_message }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if ($dispatch->items->isEmpty())
                                    -
                                @else
                                    <ul class="space-y-1">
                                        @foreach ($dispatch->items as $item)
                                            <li>
                                                <a href="{{ route('admin.orders.view', $item->order_id) }}" class="text-brandColor hover:underline">
                                                    {{ $item->order?->order_number ?: '#'.$item->order_id }}
                                                </a>
                                                <span class="text-gray-500"> - {{ $item->patient_name ?: 'onbekend' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if ($dispatch->email_id)
                                    <a class="text-brandColor hover:underline"
                                       href="{{ route('admin.mail.view', ['id' => $dispatch->email_id, 'route' => 'sent']) }}">
                                        Bekijk mail
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if ($dispatch->items->isEmpty())
                                    -
                                @else
                                    <ul class="space-y-1">
                                        @foreach ($dispatch->items as $item)
                                            <li>
                                                <a class="text-brandColor hover:underline"
                                                   href="{{ route('admin.clinics.afb_dispatch_orders.download', ['id' => $clinic->id, 'dispatchOrderId' => $item->id]) }}">
                                                    {{ $item->file_name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
