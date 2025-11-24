@php
    // Contactpersoon block
    // Use provided person or fallback to contact person or first person
    $person = $person ?? ($lead->hasContactPerson() ? $lead->contactPerson : $lead->persons->first());
    $isContactPerson = $isContactPerson ?? false;
    $title = $isContactPerson ? 'Contactpersoon' : 'Persoon';
@endphp

<!-- Person Block -->
<div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
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
{{--                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-neutral-bg hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 transition-colors"--}}
{{--                @click="$refs.contactPersonModal?.open()"--}}
{{--            >--}}
{{--                --}}
{{--                <span class="text-sm font-medium">Nieuwe contactpersoon</span>--}}
{{--            </button>--}}

            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">

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
            <div class="relative">
                <input
                    type="text"
                    class="w-full"
                    placeholder="Volledige naam"
                    value="{{ $person ? $person->name : '' }}"
                    readonly
                />
                <label class="">

                    <span>Naam contactpersoon</span>
                </label>
            </div>

            <!-- Relatie tot patiënt -->
            <div class="relative">
                <input
                    type="text"
                    class="w-full"
                    placeholder="Bijv. partner, ouder, kind"
                    value=""
                />
                <label class="">
                    <span>Relatie tot patiënt</span>
                </label>
            </div>

            <!-- Telefoonnummer -->
            <div class="relative">
                <input
                    type="tel"
                    class="w-full"
                    placeholder="06-12345678"
                    value="{{ $person && $person->phones ? (is_array($person->phones) && count($person->phones) > 0 ? $person->phones[0]['value'] ?? '' : '') : '' }}"
                    readonly
                />
                <label class="">

                    <span>Telefoonnummer</span>
                </label>
            </div>

            <!-- E-mailadres -->
            <div class="relative">
                <input
                    type="email"
                    class="w-full"
                    placeholder="email@voorbeeld.nl"
                    value="{{ $person && $person->emails ? (is_array($person->emails) && count($person->emails) > 0 ? $person->emails[0]['value'] ?? '' : '') : '' }}"
                    readonly
                />
                <label class="">

                    <span>E-mailadres</span>
                </label>
            </div>
        </div>

        <!-- Portal Account Actions -->
        @if ($person && bouncer()->hasPermission('contacts.persons.edit'))
            <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                @if (empty($person->keycloak_user_id))
                    <form
                        class="inline-flex"
                        method="POST"
                        action="{{ route('admin.contacts.persons.portal.create', $person->id) }}"
                        onsubmit="return confirm('Portal account aanmaken voor {{ $person->name }}?')"
                    >
                        @csrf
                        <button type="submit" class="secondary-button">
                            <i class="icon-login text-xs"></i>
                            Maak patiëntportaal account aan
                        </button>
                    </form>
                @else
                    <form
                        class="inline-flex"
                        method="POST"
                        action="{{ route('admin.contacts.persons.portal.delete', $person->id) }}"
                        onsubmit="return confirm('Portal account verwijderen voor {{ $person->name }}?')"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="secondary-button border border-error text-status-expired-text hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950 flex items-center gap-1">
                            <i class="icon-trash text-xs"></i>
                            Verwijder patiëntportaal account
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>

@include('admin::components.match-score')

