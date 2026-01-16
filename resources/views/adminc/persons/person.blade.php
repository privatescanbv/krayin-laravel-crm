@props([
    'person',
    'lead' => null
])
@php
    // Person block used in lead detail view
    use Illuminate\Database\Eloquent\ModelNotFoundException;

    $person = $person ?? ($lead->hasContactPerson() ? $lead->contactPerson : $lead->persons->first());
    $isContactPerson = $isContactPerson ?? false;
    $title = $isContactPerson ? 'Contactpersoon' : 'Persoon';
@endphp

<!-- Person Block -->
<div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
    <!-- Header -->
    <div
        class="{{ $isContactPerson ? 'bg-blue-100 dark:bg-blue-900/30  border-blue-500' : 'bg-orange-50 dark:bg-orange-900/20  border-orange-500' }} px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h4 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h4>
        </div>
    </div>

    <!-- Content -->
    <div class="p-4 space-y-4">
        <!-- Action Button and Status -->
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>
                    {{ $person->name }} gekoppeld
                </span>
            </div>
        </div>

        @if (!is_null($lead))
            <!-- Match Score -->
            <div>
                <v-match-score person-id="{{ $person->id }}" lead-id="{{ $lead->id }}"></v-match-score>
            </div>
        @endif

        <!-- Input Fields -->
        <div class="space-y-4">
            <x-adminc::components.field
                type="text"
                class="w-full"
                placeholder="Volledige naam"
                label="Naam contactpersoon"
                value="{{ $person ? $person->name : '' }}"
                readonly
            />

            <x-adminc::components.field
                type="tel"
                class="w-full"
                placeholder="06-12345678"
                label="Telefoonnummer"
                value="{{ $person && $person->phones ? (is_array($person->phones) && count($person->phones) > 0 ? $person->phones[0]['value'] ?? '' : '') : '' }}"
                readonly
            />

            <x-adminc::components.field
                type="email"
                class="w-full"
                placeholder="email@voorbeeld.nl"
                label="E-mailadres"
                value="{{ $person && $person->emails ? (is_array($person->emails) && count($person->emails) > 0 ? $person->emails[0]['value'] ?? '' : '') : '' }}"
                readonly
            />
        </div>

        <!-- Portal / mail actions -->
        @if ($person && bouncer()->hasPermission('contacts.persons.edit'))
            @php
                $defaultEmail = null;
                if ($person->emails && is_array($person->emails) && count($person->emails) > 0) {
                    $defaultEmail = collect($person->emails)->firstWhere('is_default', true) ?? $person->emails[0] ?? null;
                }

                $hasPortalAccount = !empty($person->keycloak_user_id);
                $canSendInfoMail = $defaultEmail && $hasPortalAccount;
            @endphp

            <div class="pt-4 border-t border-gray-200 dark:border-gray-800 flex items-center gap-3 flex-wrap">
                @if (!is_null($lead))
                <x-adminc::persons.person-lead-actions
                    :person="$person"
                    :entity="$lead"
                    :entity-id="$lead->id"
                    :is-lead="true"
                    :is-sales-lead="false"
                    :show-sync-link="true"
                    :show-anamnesis="true"
                    :detach-route="null"
                />
                @else
                    <x-adminc::persons.person-lead-actions
                        :person="$person"
                        :entity="$person"
                        :entity-id="$person->id"
                        :is-lead="false"
                        :is-sales-lead="false"
                        :show-sync-link="false"
                        :show-anamnesis="false"
                        :detach-route="null"
                    />
                @endif
            </div>
        @endif
    </div>
</div>

@include('admin::components.match-score')

