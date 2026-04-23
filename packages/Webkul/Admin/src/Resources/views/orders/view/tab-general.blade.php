@php use App\Enums\Departments; use App\Enums\PipelineStage; @endphp
@props([
    'order',
    'afbNeedsManualBanner' => false,
    'afbHasBatchSuccess' => false,
    'afbSendUrl' => null,
    'avbDispatchReadiness' => ['is_ready' => false, 'is_late' => false, 'is_all_sent' => false, 'needs_manual_send' => false, 'planned_at' => null, 'reasons' => []],
])

<div class="flex w-full flex-col gap-4 rounded-lg">

    @if ($afbNeedsManualBanner && $afbSendUrl && bouncer()->hasPermission('orders.edit'))
        <div
            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="space-y-2">
                    <p class="font-semibold">AFB: handmatige verzending nodig</p>
                    <p class="text-amber-900/90 dark:text-amber-100/90">
                        Het eerste onderzoek staat binnen 24 uur
                        @if ($order->first_examination_at)
                            ({{ $order->first_examination_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }}).
                        @else
                            .
                        @endif
                        De gebruikelijke batch voor onderzoeken op een bepaalde dag wordt de dag ervóór om 06:00
                        verstuurd;
                        binnen dit venster moet u de AFB nu zelf versturen naar de kliniek.
                    </p>
                    @if ($afbHasBatchSuccess)
                        <p class="text-xs text-amber-800 dark:text-amber-200/90">
                            Er is al een succesvolle batch-verzending voor deze order geregistreerd; controleer of een
                            extra individuele verzending nog nodig is.
                        </p>
                    @else
                        <p class="text-xs text-amber-800 dark:text-amber-200/90">
                            Er is nog geen succesvolle batch-AFB voor deze order geregistreerd voor de betreffende
                            afdeling(en).
                        </p>
                    @endif
                </div>
                <v-order-afb-send-button send-url="{{ $afbSendUrl }}"></v-order-afb-send-button>
            </div>
        </div>
    @endif

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Order</h3>

            <div class="direction-row flex items-center gap-4">
                @if (bouncer()->hasPermission('orders.edit'))
                    <a href="{{ route('admin.planning.monitor.order', ['orderId' => $order->id]) }}"
                       class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                        Resource Planner
                    </a>
                @endif
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
                <span class="text-gray-500 dark:text-gray-400">Titel</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ $order->title ?: '-' }}
                </span>
            </div>
            {{-- Totaalprijs links, Betaling klant rechts --}}
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
                <span class="text-gray-500 dark:text-gray-400">Betaling klant</span>
                @php $paymentStatus = $order->paymentStatus(); @endphp
                <span
                    class="inline-flex w-fit items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $paymentStatus->badgeClass() }}">
                    {{ $paymentStatus->label() }}
                </span>
            </div>
            {{-- Inkoopprijs links, Inkoop status rechts --}}
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Totale inkoopprijs</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    &euro; {{ number_format($order->totalPurchasePrice(), 2, ',', '.') }}
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Inkoop status</span>
                @php $purchaseStatus = $order->purchaseStatus(); @endphp
                <span
                    class="inline-flex w-fit items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $purchaseStatus->badgeClass() }}">
                    {{ $purchaseStatus->label() }}
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
                <span class="text-gray-500 dark:text-gray-400">Factuurnummer</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ $order->invoice_number ?: '-' }}
                </span>
            </div>

            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Zakelijk</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ $order->is_business ? 'Ja' : 'Nee' }}
                </span>
            </div>

            @if($order->is_business)
            <div class="flex flex-col col-span-2">
                <span class="text-gray-500 dark:text-gray-400">Organisatie</span>
                @if($order->organization)
                    <a href="{{ route('admin.contacts.organizations.view', $order->organization->id) }}"
                       class="font-medium text-brandColor hover:underline">
                        {{ $order->organization->name }}
                    </a>
                @else
                    <span class="font-medium text-red-600 dark:text-red-400">Niet ingesteld</span>
                @endif
            </div>
            @endif

            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400">Afdeling</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ $order->isHerniapoli() ? Departments::HERNIA->value : Departments::PRIVATESCAN->value }}
                </span>
            </div>
        </div>
    </div>

    <!-- AFB Status Card -->
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">AFB status</h3>

        {{-- AFB dispatch status --}}
        @php
            $avbReady   = $avbDispatchReadiness['is_ready'];
            $avbPlanned = $avbDispatchReadiness['planned_at'];
            $avbReasons = $avbDispatchReadiness['reasons'];
            $afbNeedsManualSending = $avbDispatchReadiness['needs_manual_send'];
            $afbAllSent = $avbDispatchReadiness['is_all_sent'];
            $isHerniapoli = $avbDispatchReadiness['is_herniapoli'] ?? false;
            $isPostExecution = in_array((int) $order->pipeline_stage_id, PipelineStage::getStageIdsAtOrAfterExecution(), true);
        @endphp
        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
            @if ($isHerniapoli)
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                    Niet van toepassing — Herniapoli orders ontvangen geen AFB
                </span>
            @elseif ($afbNeedsManualSending)
                <span
                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                    ⚠ Klaar voor dispatch (handmatig verzenden vereist)
                </span>
            @elseif ($afbAllSent)
                <span
                    class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                    ✓ Verzonden
                </span>
            @elseif ($avbReady)
                <span
                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                    ✓ Klaar voor dispatch
                </span>
                @if ($avbPlanned)
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Batch gepland op {{ $avbPlanned->format('d-m-Y') }} om {{ $avbPlanned->format('H:i') }}
                    </span>
                @endif
            @elseif (!$isPostExecution)
                <span
                    class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    ✗ Niet klaar voor dispatch
                </span>
                @if (!empty($avbReasons))
                    <ul class="mt-0.5 list-inside list-disc text-xs text-gray-500 dark:text-gray-400">
                        @foreach ($avbReasons as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>

        {{-- AFB status per afdeling --}}
        @if($bookedDepartments->isNotEmpty())
            <div class="border-t border-gray-100 pt-3 dark:border-gray-800">
                <div class="space-y-2 text-sm">
                    @foreach($bookedDepartments as $department)
                        @php $dispatch = $afbSentPerDepartment->get($department->id); @endphp
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <a href="{{ route('admin.clinics.view', $department->clinic_id) }}"
                                   class="text-gray-700 hover:underline dark:text-gray-300 shrink-0">{{ $department->clinic?->name }}</a>
                                <span class="text-gray-400">›</span>
                                <a href="{{ route('admin.clinic_departments.edit', $department->id) }}"
                                   class="text-gray-500 hover:underline dark:text-gray-400 truncate">{{ $department->name }}</a>
                            </div>
                            @if($dispatch)
                                <div class="flex items-center gap-2 shrink-0">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        ✓ Verzonden {{ $dispatch->sent_at->format('d-m-Y H:i') }}
                                    </span>
                                    <a href="{{ route('admin.clinic-guide.afb-pdf.view', ['personDocumentId' => $dispatch->id]) }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">
                                        Bekijk formulier
                                    </a>
                                </div>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400 shrink-0">
                                    Nog niet verzonden
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
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
                        {{ $order->salesLead->persons->first()?->name ?? $order->salesLead->name }}
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
                        {{ $order->salesLead->persons->first()?->name ?? $order->salesLead->lead->name }}
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
                        <th class="px-2 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($order->orderItems as $item)
                        @php
                            $statusClasses = $item->status?->badgeClass() ?? 'bg-neutral-bg text-gray-800';
                        @endphp
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
                            <td class="px-2 py-2 text-center">
                                @if($item->status)
                                    <span
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $statusClasses }}">
                                            {{ $item->status->label() }}
                                        </span>
                                @else
                                    <span class="text-gray-400 text-xs">-</span>
                                @endif
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
