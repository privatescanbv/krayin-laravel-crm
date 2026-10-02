<x-admin::layouts>
    <x-slot:title>
        Samenvoegen ongedaan maken - {{ $person->name }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header -->
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.contacts.persons.view', $person->id) }}" class="icon-arrow-left text-2xl"></a>
            <h1 class="text-xl font-bold dark:text-white">Samenvoegen ongedaan maken</h1>
        </div>

        <div class="box-shadow flex flex-col gap-6 rounded-lg border border-gray-200 bg-white p-6 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <!-- Both persons -->
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([['Wordt hersteld', $mergedPerson], ['Blijft bestaan', $person]] as [$label, $shown])
                    <div class="rounded border border-gray-200 p-4 dark:border-gray-700">
                        <p class="mb-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="font-semibold">{{ $shown->name }} (#{{ $shown->id }})</p>
                        <p>Geboortedatum: {{ $shown->date_of_birth?->format('d-m-Y') ?? '-' }}</p>
                        <p>E-mail: {{ collect($shown->emails)->pluck('value')->implode(', ') ?: '-' }}</p>
                        <p>Telefoon: {{ collect($shown->phones)->pluck('value')->implode(', ') ?: '-' }}</p>
                    </div>
                @endforeach
            </div>

            <div>
                <h2 class="mb-1 font-semibold">Wat er gebeurt</h2>
                <ul class="list-disc pl-5">
                    <li>{{ $mergedPerson->name }} komt terug als aparte persoon.</li>
                    <li>{{ $mergedPerson->name }} en {{ $person->name }} worden gemarkeerd als "geen duplicaat".</li>
                    <li>De actie wordt vastgelegd in het Wijzigingslogboek van beide personen.</li>
                </ul>
            </div>

            <div class="rounded border border-orange-200 bg-orange-50 p-4 text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200">
                <h2 class="mb-1 font-semibold">Wat je daarna met de hand doet</h2>
                <p class="mb-1">Gekoppelde gegevens blijven bij {{ $person->name }}. Zet terug wat van {{ $mergedPerson->name }} is:</p>
                <ul class="list-disc pl-5">
                    <li>contactpersoon op leads en sales</li>
                    <li>anamneses en orderregels</li>
                    <li>activiteiten en e-mails</li>
                    <li>e-mailadres, telefoonnummer en eventueel adres van {{ $mergedPerson->name }} weghalen bij {{ $person->name }}</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('admin.contacts.persons.duplicates.unmerge', $person->id) }}" class="flex justify-end gap-3">
                @csrf

                <input type="hidden" name="entity_id" value="{{ $mergedPerson->id }}">

                <a href="{{ route('admin.contacts.persons.view', $person->id) }}" class="secondary-button">
                    Annuleren
                </a>

                <button type="submit" class="primary-button">
                    Ja, samenvoegen ongedaan maken
                </button>
            </form>
        </div>
    </div>
</x-admin::layouts>
