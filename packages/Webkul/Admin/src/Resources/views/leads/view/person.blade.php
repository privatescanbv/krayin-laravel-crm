@php
    // Contactpersoon block
    // Use provided person or fallback to contact person or first person
    $person = $person ?? ($lead->hasContactPerson() ? $lead->contactPerson : $lead->persons->first());
    $isContactPerson = $isContactPerson ?? false;
    $title = $isContactPerson ? 'Contactpersoon' : 'Persoon';
@endphp

<!-- Person Block -->
<div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
    <!-- Header -->
    <div class="{{ $isContactPerson ? 'bg-blue-100 dark:bg-blue-900/30 border-l-4 border-blue-500' : 'bg-orange-50 dark:bg-orange-900/20 border-l-4 border-orange-500' }} px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h4 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h4>
        </div>
    </div>

    <!-- Content -->
    <div class="p-4 space-y-4">
        <!-- Action Button and Status -->
        <div class="flex items-center justify-between gap-4">
{{--            <button--}}
{{--                type="button"--}}
{{--                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 transition-colors"--}}
{{--                @click="$refs.contactPersonModal?.open()"--}}
{{--            >--}}
{{--                <span class="icon-add text-lg"></span>--}}
{{--                <span class="text-sm font-medium">Nieuwe contactpersoon</span>--}}
{{--            </button>--}}

            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                <span>
                    {{ $person->name }} gekoppeld
                </span>
            </div>
        </div>

        <!-- Match Score -->
        <div>
            <v-match-score person-id="{{ $person->id }}" lead-id="{{ $lead->id }}"></v-match-score>
        </div>

        <!-- Input Fields -->
        <div class="space-y-4">
            <!-- Naam contactpersoon -->
            <div>
                <label class="flex items-center gap-2 mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <span class="icon-contact text-lg"></span>
                    <span>Naam contactpersoon</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brandColor focus:border-transparent dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    placeholder="Volledige naam"
                    value="{{ $person ? $person->name : '' }}"
                    readonly
                />
            </div>

            <!-- Relatie tot patiënt -->
            <div>
                <label class="flex items-center gap-2 mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <div class="relative">
                        <span class="icon-contact text-lg"></span>
                        <span class="icon-contact text-lg absolute -bottom-1 -right-1 text-xs opacity-75"></span>
                    </div>
                    <span>Relatie tot patiënt</span>
                </label>
                <input
                    type="text"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brandColor focus:border-transparent dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    placeholder="Bijv. partner, ouder, kind"
                    value=""
                />
            </div>

            <!-- Telefoonnummer -->
            <div>
                <label class="flex items-center gap-2 mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <span class="icon-call text-lg"></span>
                    <span>Telefoonnummer</span>
                </label>
                <input
                    type="tel"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brandColor focus:border-transparent dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    placeholder="06-12345678"
                    value="{{ $person && $person->phones ? (is_array($person->phones) && count($person->phones) > 0 ? $person->phones[0]['value'] ?? '' : '') : '' }}"
                    readonly
                />
            </div>

            <!-- E-mailadres -->
            <div>
                <label class="flex items-center gap-2 mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <span class="icon-mail text-lg"></span>
                    <span>E-mailadres</span>
                </label>
                <input
                    type="email"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brandColor focus:border-transparent dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    placeholder="email@voorbeeld.nl"
                    value="{{ $person && $person->emails ? (is_array($person->emails) && count($person->emails) > 0 ? $person->emails[0]['value'] ?? '' : '') : '' }}"
                    readonly
                />
            </div>
        </div>
    </div>
</div>

@include('admin::components.match-score')

