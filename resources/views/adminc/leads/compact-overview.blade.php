@php
// Get default email and phone
$defaultEmail = $lead->findDefaultEmail();
$defaultPhone = null;
if ($lead->phones && is_array($lead->phones) && count($lead->phones) > 0) {
$defaultPhone = $lead->phones[0]['value'] ?? null;
}

// Format date of birth
$dateOfBirth = $lead->date_of_birth ? $lead->date_of_birth->format('d-m-Y') : '';

// Get salutation label
$salutationLabel = $lead->salutation ? $lead->salutation->label() : '';
@endphp

{!! view_render_event('admin.leads.view.compact_overview.before', ['lead' => $lead]) !!}

<div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <div class="flex items-center gap-3">
            <span class="icon-menu text-xl text-gray-600 dark:text-gray-400"></span>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Lead gegevens</h3>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span class="icon-calendar text-base"></span>
            <span>Laatst bijgewerkt: {{ $lead->updated_at->format('d M Y') }}</span>
        </div>
    </div>

    <!-- Three Column Layout -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4">
        <!-- Column 1: IDENTITEIT -->
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="icon-contact text-xl text-blue-500"></span>
                <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">IDENTITEIT</h4>
            </div>

            <!-- Aanhef -->
            <x-adminc::components.field


                label="Aanhef"
                value="{{ $salutationLabel }}"
                readonly />

            <!-- Voornaam -->
            <x-adminc::components.field


                label="Voornaam"
                value="{{ $lead->first_name ?? '' }}"
                readonly />


            <x-adminc::components.field


                label="Tussenvoegsel"
                value="{{ $lead->lastname_prefix ?? '' }}"
                readonly />

            <x-adminc::components.field


                label="Achternaam"
                value="{{ $lead->last_name ?? '' }}"
                readonly />

            <x-adminc::components.field


                label="Geboortedatum"
                value="{{ $dateOfBirth }}"
                readonly />
        </div>

        <!-- Column 2: ADRESGEGEVENS -->
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="icon-location text-xl text-status-active-text"></span>
                <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">ADRESGEGEVENS</h4>
            </div>

            <!-- Straat en huisnummer -->
            <x-adminc::components.field


                label="Straat en huisnummer"
                value="{{ $lead->address ? ($lead->address->street . ' ' . $lead->address->house_number . ($lead->address->house_number_suffix ? '-' . $lead->address->house_number_suffix : '')) : '' }}"
                readonly />

            <!-- Postcode -->
            <x-adminc::components.field


                label="Postcode"
                value="{{ $lead->address->postal_code ?? '' }}"
                readonly />

            <!-- Woonplaats -->
            <x-adminc::components.field


                label="Woonplaats"
                value="{{ $lead->address->city ?? '' }}"
                readonly />

            <!-- Land -->
            <x-adminc::components.field


                label="Land"
                value="{{ $lead->address->country ?? '' }}"
                readonly />
        </div>

        <!-- Column 3: CONTACT & IDENTIFICATIE -->
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="icon-call text-xl text-purple-500"></span>
                <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">CONTACT & IDENTIFICATIE</h4>
            </div>

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
                value="{{ $lead->national_identification_number ?? '' }}"
                placeholder="BSN nummer"
                readonly />
        </div>
    </div>
</div>

{!! view_render_event('admin.leads.view.compact_overview.after', ['lead' => $lead]) !!}
