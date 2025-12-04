<div class="flex w-full flex-col gap-4 rounded-lg">
    @if ($lead->persons->count() > 0)
        @foreach ($lead->persons as $person)
            @php
                /** @var \Illuminate\Support\Collection $leadAnamnesis */
                $leadAnamnesis = $lead->anamnesis;
                $personAnamnesis = $leadAnamnesis->firstWhere('person_id', $person->id);
            @endphp

            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="mb-3">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $person->name }}
                    </h4>
                </div>

                <div>
                    <x-adminc::anamnesis.card :anamnesis="$personAnamnesis" />
                </div>
            </div>
        @endforeach
    @else
        <div class="rounded-lg border border-neutral-border bg-neutral-muted p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Geen personen met anamneses gekoppeld aan deze lead.
        </div>
    @endif
</div>

