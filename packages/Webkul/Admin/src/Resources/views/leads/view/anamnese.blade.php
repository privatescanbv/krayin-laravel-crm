<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Anamnese</h3>

            <div class="direction-row flex items-center gap-4">

                {{-- EVENTUEEL ACTIONS --}}

            </div>
        </div>
    </div>

    @if ($lead->persons->count() > 0)
        @foreach ($lead->persons as $person)
            @php
                /** @var \Illuminate\Support\Collection $leadAnamnesis */
                $leadAnamnesis = $lead->anamnesis;
                $personAnamnesis = $leadAnamnesis->firstWhere('person_id', $person->id);
            @endphp
            <x-adminc::anamnesis.card :person="$person" :anamnesis="$personAnamnesis" />
        @endforeach
    @else
        <div class="rounded-lg border border-neutral-border bg-neutral-muted p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Geen personen met anamneses gekoppeld aan deze lead.
        </div>
    @endif
</div>

