@props(['order'])

<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Order</h3>

            <div class="direction-row flex items-center gap-4">
                @if (bouncer()->hasPermission('orders.edit'))
                    <a href="{{ route('admin.orders.edit', $order->id) }}"
                       class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                        <span class="icon-edit text-base"></span><span>Bewerk order</span>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Stages Navigation -->
    <!-- Stages Navigation -->
    @include('admin::leads.view.stages',[
        'overridePipeline' => $order->stage->pipeline,
        'overrideStage' => $order->stage,
        'overrideUpdateUrl' => route('admin.orders.stage.update', $order->id),
        'order' => $order,
    ])

    <!-- Order Details Card -->
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <span class="icon-menu text-xl text-gray-600 dark:text-gray-400"></span>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Order gegevens</h3>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="icon-calendar text-base"></span>
                <span>Laatst bijgewerkt: {{ $order->updated_at->format('d M Y') }}</span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Ordernummer</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ $order->order_number ?: '-' }}
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Status</span>
                @if($order->stage)
                    <span class="inline-flex w-fit items-center px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                        {{ $order->stage->name }}
                    </span>
                @else
                    <span class="text-gray-400">-</span>
                @endif
            </div>
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Totaalprijs</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    @if($order->total_price)
                        &euro; {{ number_format($order->total_price, 2, ',', '.') }}
                    @else
                        -
                    @endif
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Eerste onderzoek</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ $order->first_examination_at ? $order->first_examination_at->format('d-m-Y H:i') : '-' }}
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Gecombineerde order</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ $order->combine_order ? 'Ja' : 'Nee' }}
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Betaling klant</span>
                @php $paymentStatus = $order->paymentStatus(); @endphp
                <span class="inline-flex w-fit items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $paymentStatus->badgeClass() }}">
                    {{ $paymentStatus->label() }}
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Totale inkoopprijs</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    &euro; {{ number_format($order->totalPurchasePrice(), 2, ',', '.') }}
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Inkoop status</span>
                @php $purchaseStatus = $order->purchaseStatus(); @endphp
                <span class="inline-flex w-fit items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $purchaseStatus->badgeClass() }}">
                    {{ $purchaseStatus->label() }}
                </span>
            </div>
        </div>
    </div>

    <!-- Sales Lead Card -->
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h4 class="mb-4 font-semibold text-gray-800 dark:text-white">
            Gekoppelde Sales & Lead
        </h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Sales --}}
            <div class="flex items-center gap-3">
                <span class="icon-sales text-xl text-gray-500 dark:text-gray-400"></span>

                <div class="flex flex-col leading-tight">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Sales
                </span>

                    <a href="{{ route('admin.sales-leads.view', $order->salesLead->id) }}"
                       class="font-medium text-brandColor hover:underline">
                        {{ $order->salesLead->name }}
                    </a>
                </div>
            </div>

            {{-- Lead --}}
            <div class="flex items-center gap-3">
                <span class="icon-leads text-xl text-gray-500 dark:text-gray-400"></span>

                <div class="flex flex-col leading-tight">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Lead
                </span>

                    <a href="{{ route('admin.leads.view', $order->salesLead->lead->id) }}"
                       class="font-medium text-brandColor hover:underline">
                        {{ $order->salesLead->lead->name }}
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Order Items Card -->
    @if($order->orderItems && $order->orderItems->count() > 0)
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h4 class="mb-4 font-semibold text-gray-800 dark:text-white">Order Items</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-2 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                            <th class="px-2 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Persoon</th>
                            <th class="px-2 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Aantal</th>
                            <th class="px-2 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Prijs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->orderItems as $item)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-2 py-2 text-gray-800 dark:text-white">
                                    {{ $item->getProductName() ?: '-' }}
                                </td>
                                <td class="px-2 py-2 text-gray-800 dark:text-white">
                                    {{ $item->person?->name ?? '-' }}
                                </td>
                                <td class="px-2 py-2 text-right text-gray-800 dark:text-white">
                                    {{ $item->quantity }}
                                </td>
                                <td class="px-2 py-2 text-right text-gray-800 dark:text-white">
                                    &euro; {{ number_format($item->total_price ?? 0, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Persons Card -->
    @if($order->salesLead && $order->salesLead->persons && $order->salesLead->persons->count() > 0)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-2">
            @foreach ($order->salesLead->persons as $person)
                @include('adminc::persons.person', ['person' => $person])
            @endforeach
        </div>
    @endif

</div>
