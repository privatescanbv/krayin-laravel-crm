{{-- {!! view_render_event('admin.leads.create.personal_fields.form_controls.before') !!} --}}

@php
    // Check if person fields may be edited (no linked persons)
    // Only check for linked persons if entity is a Lead model (has mayEditPersonFields method)
    $mayEditPersonFields = true; // Default to true for create forms
    if (isset($entity) && method_exists($entity, 'mayEditPersonFields')) {
        $mayEditPersonFields = $entity->mayEditPersonFields();
    }
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
        <x-admin::form.control-group class="w-40">
            @php
                $current = old('salutation', $entity?->salutation?->value);
                if ($current === null || $current === '') {
                    $current = $entity?->salutation?->value;
                }
            @endphp
            <x-admin::form.control-group.control
                type="select"
                name="salutation"
                value="{{ $current }}"
                label="{{ __('Aanhef') }}"
                :disabled="!$mayEditPersonFields"
                :readonly="!$mayEditPersonFields"
            >
                <option value="">{{ __('Selecteer aanhef') }}</option>

                @foreach (App\Enums\PersonSalutation::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </x-admin::form.control-group.control>
                        <x-admin::form.control-group.label>
                Aanhef
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.error control-name="salutation"/>

        </x-admin::form.control-group>
    </div>

    <!-- Initials and First Name Row -->
    <div class="flex gap-4">
        <!-- Initials -->
        <x-admin::form.control-group class="w-20">
                        <x-admin::form.control-group.control
                type="text"
                name="initials"
                value="{{ $entity?->initials ?? '' }}"
                label="{{ __('Initialen') }}"
                placeholder="J.A."
                :readonly="!$mayEditPersonFields"
            />
            <x-admin::form.control-group.label>
                Initialen
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.error control-name="initials"/>

        </x-admin::form.control-group>

        <!-- First Name -->
        <x-admin::form.control-group class="flex-1">


            <x-admin::form.control-group.control
                type="text"
                name="first_name"
                value="{{ $entity?->first_name ?? '' }}"
                label="{{ __('Voornaam') }}"
                placeholder="Voornaam"
                rules="required"
                :readonly="!$mayEditPersonFields"
            />
            <x-admin::form.control-group.label class="required">
                Voornaam
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.error control-name="first_name"/>

        </x-admin::form.control-group>
    </div>

    <!-- Last Name Row -->
    <div class="flex gap-4">
        <!-- Last Name Prefix -->
        <x-admin::form.control-group class="w-25">
            <x-admin::form.control-group.control
                type="text"
                name="lastname_prefix"
                value="{{ $entity?->lastname_prefix ?? '' }}"
                label="{{ __('Tussenvoegsel') }}"
                placeholder="van, de, den, etc."
                class="w-24"
                :readonly="!$mayEditPersonFields"
            />
            <x-admin::form.control-group.label>
                Tussenvoegsel
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.error control-name="lastname_prefix"/>

        </x-admin::form.control-group>

        <!-- Last Name -->
        <x-admin::form.control-group class="flex-1">


            <x-admin::form.control-group.control
                type="text"
                name="last_name"
                value="{{ $entity?->last_name ?? '' }}"
                label="@lang('admin::app.leads.merge.field-last-name-birth')"
                placeholder="Achternaam"
                rules="required"
                :readonly="!$mayEditPersonFields"
            />
            <x-admin::form.control-group.label class="required">
                @lang('admin::app.leads.merge.field-last-name-birth')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.error control-name="last_name"/>

        </x-admin::form.control-group>
    </div>


    <!-- Married Name Row -->
    <div class="flex gap-4">
        <!-- Married Name Prefix -->
        <x-admin::form.control-group class="w-25">
            <x-admin::form.control-group.control
                type="text"
                name="married_name_prefix"
                value="{{ $entity?->married_name_prefix ?? '' }}"
                label="{{ __('Married name prefix') }}"
                placeholder="van, de, den, etc."
                class="w-24"
                :readonly="!$mayEditPersonFields"
            />
            <x-admin::form.control-group.label>
                Tussenvoegsel
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.error control-name="married_name_prefix"/>

        </x-admin::form.control-group>

        <!-- Married Name -->
        <x-admin::form.control-group class="flex-1">
            <x-admin::form.control-group.control
                type="text"
                name="married_name"
                value="{{ $entity?->married_name ?? '' }}"
                label="{{ __('Married name') }}"
                :readonly="!$mayEditPersonFields"
            />
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.merge.field-last-name-married')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.error control-name="married_name"/>

        </x-admin::form.control-group>
    </div>


    <!-- Date of Birth -->
    <x-admin::form.control-group>
        <x-admin::form.control-group.control
            type="date"
            name="date_of_birth"
            value="{{ $entity && $entity->date_of_birth ? $entity->date_of_birth->format('Y-m-d') : '' }}"
            label="{{ __('Geboortedatum') }}"
            :readonly="!$mayEditPersonFields"
        />
        <x-admin::form.control-group.label>
            Geboortedatum
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.error control-name="date_of_birth"/>

    </x-admin::form.control-group>

    <!-- Gender -->
    <x-admin::form.control-group>


        @php
            $currentGender = old('gender');
            if ($currentGender === null || $currentGender === '') {
                $currentGender = $entity?->gender?->value;
            }
        @endphp
        <x-admin::form.control-group.control
            type="select"
            name="gender"
            value="{{ $currentGender }}"
            label="{{ __('Geslacht') }}"
            :disabled="!$mayEditPersonFields"
            :readonly="!$mayEditPersonFields"
        >
            <option value="">{{ __('Selecteer geslacht') }}</option>

            @foreach (App\Enums\PersonGender::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </x-admin::form.control-group.control>
        <x-admin::form.control-group.label>
            Geslacht
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.error control-name="gender"/>

    </x-admin::form.control-group>

    <!-- Portal activation toggle -->
    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            Patiëntportaal actief
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="switch"
            name="is_active"
            value="1"
            :checked="(bool) old('is_active', $entity?->is_active ?? false)"
            label="Actief"
            :disabled="!$mayEditPersonFields"
            :readonly="!$mayEditPersonFields"
        />

        <x-admin::form.control-group.error control-name="is_active"/>
    </x-admin::form.control-group>

    <!-- Portal password -->
    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            Patiëntportaal wachtwoord
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="password"
            name="password"
            value=""
            label="Wachtwoord"
            placeholder="Laat leeg om niet te wijzigen"
            autocomplete="new-password"
            :readonly="!$mayEditPersonFields"
        />

        <x-admin::form.control-group.error control-name="password"/>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Vul een nieuw wachtwoord in om het portaalaccount bij te werken. Laat leeg om het huidige wachtwoord te behouden.
        </p>
    </x-admin::form.control-group>
</div>

{{-- {!! view_render_event('admin.leads.create.personal_fields.form_controls.after') !!} --}}
