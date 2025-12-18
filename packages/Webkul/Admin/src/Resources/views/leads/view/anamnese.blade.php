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

            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $person->name }}
                    </h4>

                                        <a href="{{ route('admin.leads.sync-anamnesis-to-older-update', $person->id) }}"
                       class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                        Synchroniseer met oudere Anamneses
                    </a>

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

