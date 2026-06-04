@props([
    'entity',
    'entityType',
    'persons',
])
@php
    $anamnesisPerPerson = $entity->resolveAnamnesisPerPerson();
    $isOrder = $entityType === 'order';
    $isSales = $entityType === 'sales';
@endphp
<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Anamnese</h3>
        </div>
    </div>

    @if ($persons->count() > 0)
        @foreach ($persons as $person)
            @php
                $row = $anamnesisPerPerson->get($person->id, [
                    'person' => $person,
                    'anamnesis' => null,
                    'source' => 'lead',
                    'has_override' => false,
                ]);
                $personAnamnesis = $row['anamnesis'];
                $source = $row['source'];
                $hasOverride = $row['has_override'];
            @endphp

            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                {{-- Source badge and override controls --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $person->name }}</span>

                        @if ($isOrder)
                            @if ($source === 'order')
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                    Order-specifiek
                                </span>
                            @elseif ($source === 'sales')
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                    Overgenomen van Sales
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    Overgenomen van Lead
                                </span>
                            @endif
                        @elseif ($isSales)
                            @if ($source === 'sales')
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                    Sales-specifiek
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    Overgenomen van Lead
                                </span>
                            @endif
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        @php
                            $returnUrlAnamnese = strtok(url()->current(), '#') . '#anamnese';
                        @endphp
                        @if ($isOrder)
                            @if ($hasOverride)
                                <form method="POST" action="{{ route('admin.anamnesis.revert-override') }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="order_id" value="{{ $entity->id }}">
                                    <input type="hidden" name="person_id" value="{{ $person->id }}">
                                    <input type="hidden" name="return_url" value="{{ $returnUrlAnamnese }}">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                            onclick="return confirm('Weet u zeker dat u de order-specifieke anamnese wilt verwijderen en wilt terugvallen op de Sales/Lead anamnese?')"
                                    >
                                        <span class="icon-undo text-sm"></span>
                                        Terugzetten
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.anamnesis.override') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="order_id" value="{{ $entity->id }}">
                                    <input type="hidden" name="person_id" value="{{ $person->id }}">
                                    <input type="hidden" name="return_url" value="{{ $returnUrlAnamnese }}">
                                    @if ($personAnamnesis)
                                        <input type="hidden" name="source_anamnesis_id" value="{{ $personAnamnesis->id }}">
                                    @endif
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-md border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 shadow-sm transition hover:bg-blue-100 dark:border-blue-600 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/40"
                                            onclick="return confirm('Er wordt een nieuwe anamnese aangemaakt specifiek voor deze order. De bestaande anamnese van de Sales/Lead blijft ongewijzigd.')"
                                    >
                                        <span class="icon-edit text-sm"></span>
                                        Overschrijven voor Order
                                    </button>
                                </form>
                            @endif
                        @elseif ($isSales)
                            @if ($hasOverride)
                                <form method="POST" action="{{ route('admin.anamnesis.revert-override') }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="sales_id" value="{{ $entity->id }}">
                                    <input type="hidden" name="person_id" value="{{ $person->id }}">
                                    <input type="hidden" name="return_url" value="{{ $returnUrlAnamnese }}">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                            onclick="return confirm('Weet u zeker dat u de sales-specifieke anamnese wilt verwijderen en wilt terugvallen op de Lead anamnese?')"
                                    >
                                        <span class="icon-undo text-sm"></span>
                                        Terugzetten
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.anamnesis.override') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="sales_id" value="{{ $entity->id }}">
                                    <input type="hidden" name="person_id" value="{{ $person->id }}">
                                    <input type="hidden" name="return_url" value="{{ $returnUrlAnamnese }}">
                                    @if ($personAnamnesis)
                                        <input type="hidden" name="source_anamnesis_id" value="{{ $personAnamnesis->id }}">
                                    @endif
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-md border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 shadow-sm transition hover:bg-blue-100 dark:border-blue-600 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/40"
                                            onclick="return confirm('Er wordt een nieuwe anamnese aangemaakt specifiek voor deze sales. De bestaande anamnese van de Lead blijft ongewijzigd.')"
                                    >
                                        <span class="icon-edit text-sm"></span>
                                        Overschrijven voor Sales
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Anamnesis card content --}}
                @if ($personAnamnesis)
                    <div class="p-0">
                        <x-adminc::anamnesis.card :person="$person" :anamnesis="$personAnamnesis" />
                    </div>
                @else
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        Geen anamnese beschikbaar voor deze persoon.
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="rounded-lg border border-neutral-border bg-neutral-muted p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Geen personen gekoppeld aan deze entiteit.
        </div>
    @endif
</div>
