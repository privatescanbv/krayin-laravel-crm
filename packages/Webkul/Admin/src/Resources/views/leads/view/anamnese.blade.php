@php
    // Anamnesis view with harmonica per person
@endphp

<div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
    Anamnese informatie komt hier in een latere iteratie.

    Let op: Elke gekoppelde persoon heeft een Anamnese per lead. Dus een vorm van versie.
    Toon hier de juiste versie per persoon (Ontwerp is hier niet volledig in).

    Mag van mij naar eigen inzicht, wellicht harmonicatie met personen?
</div>

<div class="flex w-full flex-col gap-4 rounded-lg">
    @if($lead->persons->count() > 0)
        @foreach($lead->persons as $person)
            <x-admin::accordion class="select-none" :isActive="false">
                <x-slot:header class="!p-0">
                    <div class="flex w-full items-center justify-between p-4">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">{{ $person->name }}</h4>
                    </div>
                </x-slot>

                <x-slot:content class="!px-4 !pb-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        <!-- Anamnesis for this specific person -->
                        <x-adminc::anamnesis.card :anamnesis="$lead->findAnamnesisByPersonId($person->id)" />
                    </div>
                </x-slot>
            </x-admin::accordion>
        @endforeach
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Geen personen gekoppeld aan deze lead.
        </div>
    @endif
</div>

