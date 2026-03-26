@props([
    'clinic' => null,
])

<!-- Basic Information Section -->
<div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <x-adminc::components.field
        type="switch"
        name="is_active"
        value="1"
        :checked="(bool) old('is_active', $clinic->is_active ?? true)"
        :label="trans('admin::app.settings.clinics.index.create.is_active')"
    />

    <x-adminc::components.field
        type="text"
        name="name"
        value="{{ old('name', $clinic->name ?? '') }}"
        rules="required|min:1|max:100"
        :label="trans('admin::app.settings.clinics.index.create.name')"
        :placeholder="trans('admin::app.settings.clinics.index.create.name')"
    />

    <x-adminc::components.field
        type="textarea"
        name="description"
        value="{{ old('description', $clinic->description ?? '') }}"
        rules="max:2000"
        label="Omschrijving"
        placeholder="Optionele omschrijving van de kliniek"
    />

    <x-adminc::components.field
        type="text"
        name="registration_form_clinic_name"
        value="{{ old('registration_form_clinic_name', $clinic->registration_form_clinic_name ?? '') }}"
        rules="max:255"
        label="AFB naam kliniek"
        placeholder="AFB naam kliniek"
    />

    <x-adminc::components.field
        type="text"
        name="website_url"
        value="{{ old('website_url', $clinic->website_url ?? '') }}"
        rules="url|max:255"
        label="Website URL"
        placeholder="https://www.voorbeeld.nl"
    />

    <x-adminc::components.field
        type="textarea"
        name="order_confirmation_note"
        value="{{ old('order_confirmation_note', $clinic->order_confirmation_note ?? '') }}"
        rules="max:1000"
        label="Opmerking orderbevestiging"
        placeholder="Informatie waar patiënt zich kan melden"
    />

    <!-- Emails -->
    @php
        $__emailsVal = old('emails', $clinic->emails ?? []);
        if (!is_array($__emailsVal)) { $__emailsVal = []; }
    @endphp
    @include('admin::leads.common.sections.emails', ['name' => 'emails', 'value' => $__emailsVal, 'widthClass' => 'w-full'])

    <!-- Phones -->
    @php
        $__phonesVal = old('phones', $clinic->phones ?? []);
        if (!is_array($__phonesVal)) { $__phonesVal = []; }
    @endphp
    @include('admin::leads.common.sections.phones', ['name' => 'phones', 'value' => $__phonesVal, 'widthClass' => 'w-full'])
</div>

<!-- Address Section -->
<div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="mb-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
            @lang('admin::app.contacts.organizations.create.address')
        </h3>
    </div>

    @php
        $isPostalSameAsVisit = (bool) old(
            'is_postal_address_same_as_visit_address',
            $clinic->is_postal_address_same_as_visit_address ?? true
        );
    @endphp

    <x-adminc::components.field
        type="switch"
        name="is_postal_address_same_as_visit_address"
        value="1"
        :checked="$isPostalSameAsVisit"
        :label="trans('admin::app.settings.clinics.addresses.postal-same-as-visit')"
    />

    <div class="mt-4">
        <h4 class="mb-2 text-base font-semibold dark:text-white">
            @lang('admin::app.settings.clinics.addresses.visit-address')
        </h4>

        <x-adminc::components.address
            :address="$clinic?->visitAddress"
            name-prefix="visit_address"
            error-name-prefix="visit_address"
            id="visit_address"
            :hide-title="true"
        />
    </div>

    <div
        id="postal-address-section"
{{--        class="mt-6 {{ $isPostalSameAsVisit ? 'hidden' : '' }}"--}}
        class="mt-6"
    >
        <h4 class="mb-2 text-base font-semibold dark:text-white">
            @lang('admin::app.settings.clinics.addresses.postal-address')
        </h4>

        <x-adminc::components.address
            :address="$clinic?->postalAddress"
            name-prefix="postal_address"
            error-name-prefix="postal_address"
            id="postal_address"
            :hide-title="true"
        />
    </div>
</div>

@pushOnce('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const postalSection = document.getElementById('postal-address-section');

            if (!postalSection) {
                return;
            }

            const syncVisibility = () => {
                const sameCheckbox = document.getElementById('is_postal_address_same_as_visit_address');
                const postalSection2 = document.getElementById('postal-address-section');
                // If Vue hasn't mounted the switch yet, don't force-hide.
                if (!sameCheckbox) {
                    return;
                }
                const sync = () => {
                    if (sameCheckbox.checked) {
                        postalSection2.style.setProperty('display', 'none', 'important');
                    } else {
                        postalSection2.style.setProperty('display', 'block', 'important');
                    }
                };
                sync();
            };

            // Vue can re-render the switch; listen broadly and resync after user interaction.
            const resyncSoon = () => {
                syncVisibility();
            };

            document.addEventListener('change', function (e) {
                if (e.target && e.target.id === 'is_postal_address_same_as_visit_address') {
                    resyncSoon();
                }
            });

            // Initial sync (and a short delayed sync) for Vue mount timing.
            syncVisibility();
        });
    </script>
@endPushOnce
