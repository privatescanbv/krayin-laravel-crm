@props(['clinic'])

@php
    use App\Enums\Inkoop\InkoopInvoiceParser;
    use App\Enums\Inkoop\InkoopInvoiceStatus;
    use App\Models\Inkoop\InkoopInvoice;
    use App\Models\Inkoop\InkoopPerson;

    $invoices = InkoopInvoice::where('clinic_id', $clinic->id)
        ->orderByRaw("CASE
            WHEN status = ? THEN 0
            WHEN status = ? THEN 1
            ELSE 2
        END", [InkoopInvoiceStatus::OPEN->value, InkoopInvoiceStatus::PROCESSING->value])
        ->orderByDesc('reference_date')
        ->orderByDesc('created_at')
        ->get();

    $personPercentageByInvoice = [];
    foreach ($invoices as $invoice) {
        $personPercentageByInvoice[$invoice->id] = InkoopPerson::calculatePercentageHasCRMRelation($invoice->id);
    }
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">

    {{-- Header --}}
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Inkoop afletteren</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Verwerk inkoopfacturen van leveranciers en koppel deze aan CRM orderregels.
                </p>
            </div>

            @if (bouncer()->hasPermission('activities.create'))
                <a
                    href="{{ route('admin.inkoop.clinics.upload', $clinic->id) }}"
                    class="primary-button flex shrink-0 items-center gap-1"
                >
                    <span class="icon-upload text-base"></span>
                    Nieuwe factuur uploaden
                </a>
            @endif
        </div>
    </div>

    {{-- Invoice list --}}
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">

        @if ($invoices->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nog geen inkoopfacturen verwerkt voor deze kliniek.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-2 py-2">Status</th>
                            <th class="px-2 py-2">Ref. datum</th>
                            <th class="px-2 py-2">Naam</th>
                            <th class="px-2 py-2 text-right">Patiënten</th>
                            <th class="px-2 py-2 text-right">Regels</th>
                            <th class="px-2 py-2">Leverancier</th>
                            <th class="px-2 py-2">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr class="border-b border-gray-100 align-middle dark:border-gray-800">

                                {{-- Status --}}
                                <td class="px-2 py-3 whitespace-nowrap">
                                    @if ($invoice->status === InkoopInvoiceStatus::CLOSED)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            <span class="icon-done text-sm"></span>
                                            {{ $invoice->status->label() }}
                                        </span>
                                    @elseif ($invoice->status === InkoopInvoiceStatus::PROCESSING)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                            <span class="icon-activity text-sm"></span>
                                            {{ $invoice->status->label() }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-600">
                                            <span class="icon-time text-sm"></span>
                                            {{ $invoice->status->label() }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Ref. datum --}}
                                <td class="px-2 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                    {{ $invoice->reference_date?->format('d-m-Y') ?? '-' }}
                                </td>

                                {{-- Naam --}}
                                <td class="max-w-[200px] truncate px-2 py-3 text-gray-900 dark:text-white">
                                    {{ $invoice->name ?? $invoice->filename }}
                                </td>

                                {{-- Patiënten % --}}
                                <td class="px-2 py-3 text-right whitespace-nowrap">
                                    @php $pct = $personPercentageByInvoice[$invoice->id] ?? 0; @endphp
                                    <span class="{{ $pct >= 100 ? 'text-green-600' : ($pct > 0 ? 'text-yellow-600' : 'text-gray-400') }} font-medium">
                                        {{ $pct }}%
                                    </span>
                                </td>

                                {{-- Factuurregels % --}}
                                <td class="px-2 py-3 text-right whitespace-nowrap">
                                    @php $itemPct = $invoice->calculateResolvedInvoiceItemsPercentage(); @endphp
                                    <span class="{{ $itemPct >= 100 ? 'text-green-600' : ($itemPct > 0 ? 'text-yellow-600' : 'text-gray-400') }} font-medium">
                                        {{ $itemPct }}%
                                    </span>
                                </td>

                                {{-- Leverancier --}}
                                <td class="px-2 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                    {{ $invoice->parser?->label() ?? '-' }}
                                </td>

                                {{-- Acties --}}
                                <td class="px-2 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if ($invoice->status !== InkoopInvoiceStatus::CLOSED)
                                            <a
                                                href="{{ route('admin.inkoop.step0', ['invoice' => $invoice->id]) }}"
                                                class="secondary-button py-1 text-xs"
                                            >
                                                Verwerken
                                            </a>
                                        @else
                                            <a
                                                href="{{ route('admin.inkoop.step3', ['invoice' => $invoice->id]) }}"
                                                class="secondary-button py-1 text-xs"
                                            >
                                                Bekijken
                                            </a>
                                        @endif

                                        <form
                                            method="POST"
                                            action="{{ route('admin.inkoop.delete', $invoice->id) }}"
                                            onsubmit="return confirm('Weet je zeker dat je deze factuur wilt verwijderen?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="cursor-pointer rounded-md p-1.5 text-xl text-gray-500 transition-all hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 icon-delete"
                                                title="Verwijderen"
                                            ></button>
                                        </form>
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
