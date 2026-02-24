@props([
    'person'
])
@php
// Get default email and phone
$defaultEmail = $person->findDefaultEmail();
$defaultPhone = $person->findDefaultPhone();

// Format date of birth
$dateOfBirth = $person->date_of_birth ? $person->date_of_birth->format('d-m-Y') : '';

// Get salutation label
$salutationLabel = $person->salutation ? $person->salutation->label() : '';
@endphp

{!! view_render_event('admin.persons.view.compact_overview.before', ['person' => $person]) !!}

<div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <div class="flex items-center gap-3">
            <span class="icon-menu text-xl text-gray-600 dark:text-gray-400"></span>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">persoon gegevens</h3>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span class="icon-calendar text-base"></span>
            <span>Laatst bijgewerkt: {{ $person->updated_at->format('d M Y') }}</span>
        </div>
    </div>

    <!-- Three Column Layout -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4">
        <!-- Column 1: IDENTITEIT -->
        <x-adminc::leads.part.general-info-container
            title="IDENTITEIT"
            icon="icon-contact text-xl text-blue-500"
        >
            <div class="grid grid-cols-5 gap-3">
                <div class="col-span-2">
                    <x-adminc::components.field
                        label="Aanhef"
                        value="{{ $salutationLabel }}"
                        readonly />
                </div>
                <div class="col-span-3">
                    <x-adminc::components.field
                        label="Voornaam"
                        value="{{ $person->first_name ?? '' }}"
                        readonly />
                </div>
            </div>
            <div class="grid grid-cols-5 gap-3">
                <div class="col-span-2">
                    <x-adminc::components.field
                    label="Tussenvoegsel"
                    value="{{ $person->lastname_prefix ?? '' }}"
                    readonly />
                </div>
                <div class="col-span-3">
                    <x-adminc::components.field
                        label="Achternaam"
                        value="{{ $person->last_name ?? '' }}"
                        readonly />
                </div>
            </div>
            <div class="grid grid-cols-5 gap-3">
                <div class="col-span-2">
                    <x-adminc::components.field
                        label="Tussenvoegsel"
                        value="{{ $person->married_name_prefix ?? '' }}"
                        readonly />
                </div>
                <div class="col-span-3">
                    <x-adminc::components.field
                        label="Aangetrouwde achternaam"
                        value="{{ $person->married_name ?? '' }}"
                        readonly />
                    </div>
                </div>
            <x-adminc::components.field
                label="Geboortedatum"
                value="{{ $dateOfBirth }}"
                readonly />
        </x-adminc::leads.part.general-info-container>

        <!-- Column 2: ADRESGEGEVENS -->
        <x-adminc::leads.part.general-info-container
            title="ADRESGEGEVENS"
            icon="icon-location text-xl text-status-active-text"
        >
          <x-adminc::address.summarize_as_fields :address="$person->address"/>
        </x-adminc::leads.part.general-info-container>

        <!-- Column 3: CONTACT & IDENTIFICATIE -->
        <x-adminc::leads.part.general-info-container
            title="CONTACT & IDENTIFICATIE"
            icon="icon-call text-xl text-purple-500"
        >
            <!-- Telefoonnummer -->
            <x-adminc::components.field
                type="tel"
                label="Telefoonnummer"
                value="{{ $defaultPhone ?? '' }}"
                readonly />

            <!-- E-mailadres -->
            <x-adminc::components.field
                type="email"
                label="E-mailadres"
                value="{{ $defaultEmail ?? '' }}"
                readonly />

            <!-- Burgerservicenummer (BSN) -->
            <x-adminc::components.field
                type="text"
                label="Burgerservicenummer (BSN)"
                value="{{ $person->national_identification_number ?? '' }}"
                readonly />

            <!-- Voorkeurstaal -->
            <x-adminc::components.field
                type="text"
                label="Voorkeurstaal"
                value="{{ $person->preferred_language?->label() ?? '' }}"
                readonly />
        </x-adminc::leads.part.general-info-container>
    </div>
</div>

{!! view_render_event('admin.persons.view.compact_overview.after', ['person' => $person]) !!}
