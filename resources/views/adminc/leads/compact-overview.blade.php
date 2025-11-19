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
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-contact text-sm"></span>
                    <span>Aanhef</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $salutationLabel }}"
                    readonly
                />
            </div>

            <!-- Voornaam -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-contact text-sm"></span>
                    <span>Voornaam</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $lead->first_name ?? '' }}"
                    readonly
                />
            </div>

            <!-- Tussenvoegels -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-contact text-sm"></span>
                    <span>Tussenvoegels</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $lead->lastname_prefix ?? '' }}"
                    readonly
                />
            </div>

            <!-- Achternaam -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-contact text-sm"></span>
                    <span>Achternaam</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $lead->last_name ?? '' }}"
                    readonly
                />
            </div>

            <!-- Geboortedatum -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-contact text-sm"></span>
                    <span>Geboortedatum</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $dateOfBirth }}"
                    readonly
                />
            </div>
        </div>

        <!-- Column 2: ADRESGEGEVENS -->
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="icon-location text-xl text-green-500"></span>
                <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">ADRESGEGEVENS</h4>
            </div>

            <!-- Straat en huisnummer -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-location text-sm"></span>
                    <span>Straat en huisnummer</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $lead->address ? ($lead->address->street . ' ' . $lead->address->house_number . ($lead->address->house_number_suffix ? '-' . $lead->address->house_number_suffix : '')) : '' }}"
                    readonly
                />
            </div>

            <!-- Postcode -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-location text-sm"></span>
                    <span>Postcode</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $lead->address->postal_code ?? '' }}"
                    readonly
                />
            </div>

            <!-- Woonplaats -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-location text-sm"></span>
                    <span>Woonplaats</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $lead->address->city ?? '' }}"
                    readonly
                />
            </div>

            <!-- Land -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-location text-sm"></span>
                    <span>Land</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $lead->address->country ?? '' }}"
                    readonly
                />
            </div>
        </div>

        <!-- Column 3: CONTACT & IDENTIFICATIE -->
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="icon-call text-xl text-purple-500"></span>
                <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">CONTACT & IDENTIFICATIE</h4>
            </div>

            <!-- Telefoonnummer -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-call text-sm"></span>
                    <span>Telefoonnummer</span>
                </label>
                <input
                    type="tel"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $defaultPhone ?? '' }}"
                    readonly
                />
            </div>

            <!-- E-mailadres -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-mail text-sm"></span>
                    <span>E-mailadres</span>
                </label>
                <input
                    type="email"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value="{{ $defaultEmail ?? '' }}"
                    readonly
                />
            </div>

            <!-- Burgerservicenummer (BSN) -->
            <div>
                <label class="flex items-center gap-2 mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="icon-contact text-sm"></span>
                    <span>Burgerservicenummer (BSN)</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border bg-neutral-100 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    value=""
                    placeholder="BSN nummer"
                    readonly
                />
            </div>
        </div>
    </div>
</div>

{!! view_render_event('admin.leads.view.compact_overview.after', ['lead' => $lead]) !!}
