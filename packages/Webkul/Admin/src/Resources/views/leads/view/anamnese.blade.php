@props([
    'anamneses',
    'persons',
    'lead',
])
<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Anamnese</h3>

            <div class="direction-row flex items-center gap-4">

                {{-- EVENTUEEL ACTIONS --}}

            </div>
        </div>
    </div>

    @if ($persons->count() > 0)
        @foreach ($persons as $person)
            @php
                /** @var \Illuminate\Support\Collection $anamneses */
                $personAnamnesis = $anamneses->firstWhere('person_id', $person->id);
                $returnUrlAnamnese = strtok(url()->current(), '#') . '#anamnese';
            @endphp
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $person->name }}</span>
                </div>
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <x-adminc::anamnesis.forms-overview
                        :entity="$lead"
                        entityType="lead"
                        :person="$person"
                        :effectiveAnamnesis="$personAnamnesis"
                        :personHasPortalAccount="!empty($person->keycloak_user_id)"
                        :returnUrl="$returnUrlAnamnese"
                    />
                </div>
                <x-adminc::anamnesis.card :person="$person" :anamnesis="$personAnamnesis" />
            </div>
        @endforeach
    @else
        <div class="rounded-lg border border-neutral-border bg-neutral-muted p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Geen personen met anamneses gekoppeld aan deze entiteit.
        </div>
    @endif
</div>

