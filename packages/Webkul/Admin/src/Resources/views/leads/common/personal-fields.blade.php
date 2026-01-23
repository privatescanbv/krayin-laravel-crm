{{-- {!! view_render_event('admin.leads.create.personal_fields.form_controls.before') !!} --}}

@php
    // Check if person fields may be edited (no linked persons)
    // Only check for linked persons if entity is a Lead model (has mayEditPersonFields method)
    $mayEditPersonFields = true; // Default to true for create forms
    if (isset($entity) && method_exists($entity, 'mayEditPersonFields')) {
        $mayEditPersonFields = $entity->mayEditPersonFields();
    }

    // By default show portal fields, but allow includes to disable them (e.g. create lead)
    $showPortalFields = $showPortalFields ?? true;
    $readonlyAttributes = !$mayEditPersonFields ? ['readonly' => 'readonly', 'disabled' => 'disabled'] : [];
@endphp

<div class="flex flex-col gap-4">
    @if(!$mayEditPersonFields)
        <div class="mb-4 p-3 bg-status-on_hold-bg border border-status-on_hold-border rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <span class="text-sm text-status-on_hold-text font-medium">
                    Persoonsgegevens zijn alleen-lezen omdat er contactpersonen gekoppeld zijn aan deze lead.
                </span>
            </div>
        </div>
    @endif

    <!-- Salutation -->
    <div class="flex gap-4">
        @php
            $current = old('salutation', $entity?->salutation?->value);
            if ($current === null || $current === '') {
                $current = $entity?->salutation?->value;
            }
        @endphp
        <x-adminc::components.field
            class="w-40"
            type="select"
            name="salutation"
            :label="__('Aanhef')"
            value="{{ $current }}"
            :disabled="!$mayEditPersonFields"
            :readonly="!$mayEditPersonFields"
            focus
        >
            <option value="">{{ __('Selecteer aanhef') }}</option>

            @foreach (App\Enums\PersonSalutation::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </x-adminc::components.field>
    </div>

    <!-- Initials and First Name Row -->
    <div class="flex gap-4">
        <!-- Initials -->
        <x-adminc::components.field
            class="w-20"
            type="text"
            name="initials"
            :label="__('Initialen')"
            value="{{ $entity?->initials ?? '' }}"
            placeholder="J.A."
            :readonly="!$mayEditPersonFields"
        />

        <!-- First Name -->
        <x-adminc::components.field
            class="flex-1"
            type="text"
            name="first_name"
            :label="__('Voornaam')"
            value="{{ $entity?->first_name ?? '' }}"
            placeholder="Voornaam"
            rules="required"
            :readonly="!$mayEditPersonFields"
        />
    </div>

    <!-- Last Name Row -->
    <div class="flex gap-4">
        <!-- Last Name Prefix -->
        <x-adminc::components.field
            class="w-25"
            type="text"
            name="lastname_prefix"
            :label="__('Tussenvoegsel')"
            value="{{ $entity?->lastname_prefix ?? '' }}"
            placeholder="van, de, den, etc."
            :readonly="!$mayEditPersonFields"
        />

        <!-- Last Name -->
        <x-adminc::components.field
            class="flex-1"
            type="text"
            name="last_name"
            :label="__('admin::app.leads.merge.field-last-name-birth')"
            value="{{ $entity?->last_name ?? '' }}"
            placeholder="Achternaam"
            rules="required"
            :readonly="!$mayEditPersonFields"
        />
    </div>


    <!-- Married Name Row -->
    <div class="flex gap-4">
        <!-- Married Name Prefix -->
        <x-adminc::components.field
            class="w-25"
            type="text"
            name="married_name_prefix"
            :label="__('Tussenvoegsel')"
            value="{{ $entity?->married_name_prefix ?? '' }}"
            placeholder="van, de, den, etc."
            :readonly="!$mayEditPersonFields"
        />

        <!-- Married Name -->
        <x-adminc::components.field
            class="flex-1"
            type="text"
            name="married_name"
            label="Aangetrouwde achternaam"
            placeholder="Aangetrouwde achternaam"
            value="{{ $entity?->married_name ?? '' }}"
            :readonly="!$mayEditPersonFields"
        />
    </div>


    <!-- Date of Birth -->
    <x-adminc::components.field
        type="date"
        name="date_of_birth"
        :label="__('Geboortedatum')"
        value="{{ $entity && $entity->date_of_birth ? $entity->date_of_birth->format('Y-m-d') : '' }}"
        :readonly="!$mayEditPersonFields"
    />

    <!-- Gender -->
    @php
        $currentGender = old('gender');
        if ($currentGender === null || $currentGender === '') {
            $currentGender = $entity?->gender?->value;
        }
    @endphp
    <x-adminc::components.field
        type="select"
        name="gender"
        :label="__('Geslacht')"
        value="{{ $currentGender }}"
        :disabled="!$mayEditPersonFields"
        :readonly="!$mayEditPersonFields"
    >
        <option value="">{{ __('Selecteer geslacht') }}</option>

        @foreach (App\Enums\PersonGender::cases() as $case)
            <option value="{{ $case->value }}">{{ $case->label() }}</option>
        @endforeach
    </x-adminc::components.field>

    <!-- Burgerservicenummer (BSN) -->
    <x-adminc::components.field
        type="text"
        name="national_identification_number"
        label="Burgerservicenummer (BSN)"
        value="{{ $entity?->national_identification_number ?? '' }}"
        placeholder="BSN nummer"
        :disabled="!$mayEditPersonFields"
        :readonly="!$mayEditPersonFields"
    />
    @if($showPortalFields)
        <!-- Portal activation toggle -->
        <x-adminc::components.field
            type="switch"
            name="is_active"
            value="1"
            :checked="(bool) old('is_active', $entity?->is_active ?? false)"
            label="Patiëntportaal actief"
            :disabled="!$mayEditPersonFields"
            :readonly="!$mayEditPersonFields"
        />

        <!-- Portal password -->
        <x-adminc::components.field
            type="password"
            name="password"
            label="Patiëntportaal wachtwoord"
            value=""
            placeholder="Laat leeg om niet te wijzigen"
            :readonly="!$mayEditPersonFields"
        />

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Vul een nieuw wachtwoord in om het portaalaccount bij te werken. Laat leeg om het huidige wachtwoord te behouden.
        </p>
    @endif
</div>

{{-- {!! view_render_event('admin.leads.create.personal_fields.form_controls.after') !!} --}}
