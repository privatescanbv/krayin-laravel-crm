{!! view_render_event('admin.address.before') !!}

@php
    $addressId = $id ?? 'address';
    $readonlyAttributes = isset($readonly) && $readonly ? ['readonly' => 'readonly', 'disabled' => 'disabled'] : [];
@endphp

<div class="flex flex-col gap-4">
    @if (!isset($hideTitle) || !$hideTitle)
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold dark:text-white">
            Adresgegevens
        </p>
    </div>
    @endif

    @if (isset($readonly) && $readonly)
        <div class="mb-4 p-3 bg-status-on_hold-bg border border-status-on_hold-border rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <span class="text-sm text-status-on_hold-text font-medium">
                    Adresgegevens zijn alleen-lezen omdat er contactpersonen gekoppeld zijn aan deze lead.
                </span>
            </div>
        </div>
    @endif

    <!-- Address Lookup Panel -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-wrap md:flex-nowrap gap-3">
            <!-- Postal Code -->
            <div class="flex-1 min-w-[150px]">
                <x-adminc::components.field
                    type="text"
                    name="{{ $namePrefix ?? 'address' }}[postal_code]"
                    error-name="address.postal_code"
                    label="Postcode"
                    value="{{ old('address.postal_code', $entity?->address?->postal_code ?? '') }}"
                    placeholder="1234 AB"
                    id="{{ $addressId }}_postal_code"
                    :readonly="isset($readonly) && $readonly"
                />
            </div>

            <!-- House Number -->
            <div class="flex-1 min-w-[150px]">
                <x-adminc::components.field
                    type="text"
                    name="{{ $namePrefix ?? 'address' }}[house_number]"
                    error-name="address.house_number"
                    label="Huisnummer"
                    value="{{ old('address.house_number', $entity?->address?->house_number ?? '') }}"
                    placeholder="123"
                    id="{{ $addressId }}_house_number"
                    :disabled="isset($readonly) && $readonly"
                    :readonly="isset($readonly) && $readonly"
                />
            </div>

            <!-- Lookup button -->
            @if (!isset($readonly) || !$readonly)
            <div class="flex-shrink-0 flex flex-col justify-end">
                <div class="mb-4 flex items-end h-full">
                    <button
                        type="button"
                        id="{{ $addressId }}-lookup-btn"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 min-w-[120px]"
                    >
                        Adres opzoeken
                    </button>
                </div>
            </div>
            @endif
        </div>
    </div>


    <!-- Address Details -->
    <div class="grid grid-cols-2 gap-4">
        <!-- Street -->
        <x-adminc::components.field
            type="text"
            name="{{ $namePrefix ?? 'address' }}[street]"
            error-name="address.street"
            label="Straat"
            value="{{ old('address.street', $entity?->address?->street ?? '') }}"
            placeholder="Straatnaam"
            id="{{ $addressId }}_street"
            :readonly="isset($readonly) && $readonly"
        />

        <!-- House Number Suffix -->
        <x-adminc::components.field
            type="text"
            name="{{ $namePrefix ?? 'address' }}[house_number_suffix]"
            error-name="address.house_number_suffix"
            label="Toevoeging"
            value="{{ old('address.house_number_suffix', $entity?->address?->house_number_suffix ?? '') }}"
            placeholder="A, 1e verdieping, etc."
            id="{{ $addressId }}_house_number_suffix"
            :readonly="isset($readonly) && $readonly"
        />

        <!-- City -->
        <x-adminc::components.field
            type="text"
            name="{{ $namePrefix ?? 'address' }}[city]"
            error-name="address.city"
            label="Stad"
            value="{{ old('address.city', $entity?->address?->city ?? '') }}"
            placeholder="Amsterdam"
            id="{{ $addressId }}_city"
            :readonly="isset($readonly) && $readonly"
        />

        <!-- State -->
        <x-adminc::components.field
            type="text"
            name="{{ $namePrefix ?? 'address' }}[state]"
            error-name="address.state"
            label="Provincie"
            value="{{ old('address.state', $entity?->address?->state ?? '') }}"
            placeholder="Noord-Holland"
            id="{{ $addressId }}_state"
            :readonly="isset($readonly) && $readonly"
        />

        <!-- Country -->
        <x-adminc::components.field
            class="col-span-2"
            type="text"
            name="{{ $namePrefix ?? 'address' }}[country]"
            error-name="address.country"
            label="Land"
            value="{{ old('address.country', $entity?->address?->country ?? '') }}"
            placeholder="Nederland"
            id="{{ $addressId }}_country"
            :readonly="isset($readonly) && $readonly"
        />
    </div>

    <v-address-preview
        :address='@json($entity?->address ?? new stdClass())'
    ></v-address-preview>
</div>

{!! view_render_event('admin.address.after') !!}

@pushOnce('scripts')
    <script>
        window.addressComponents = window.addressComponents || {};
        window.addressComponents['{{ $addressId }}'] = {
            id: '{{ $addressId }}',
            postalCodeId: '{{ $addressId }}_postal_code',
            houseNumberId: '{{ $addressId }}_house_number',
            streetId: '{{ $addressId }}_street',
            cityId: '{{ $addressId }}_city',
            stateId: '{{ $addressId }}_state'
        };
    </script>

    @verbatim
        <script type="text/x-template" id="v-address-preview-template">
            <div v-if="fullAddress" class="mt-4 p-3 bg-gray-50 rounded border" style="display: none;">
                <div class="text-sm font-medium text-gray-700 mb-1">Adres preview:</div>
                <div class="text-sm text-gray-600">{{ fullAddress }}</div>
            </div>
        </script>
    @endverbatim

    <script type="module">
        app.component('v-address-preview', {
            template: '#v-address-preview-template',

            props: ['address'],

            computed: {
                fullAddress() {
                    if (!this.address) return '';

                    const parts = [];

                    if (this.address.street && this.address.house_number) {
                        let streetPart = this.address.street + ' ' + this.address.house_number;
                        if (this.address.house_number_suffix) {
                            streetPart += ' ' + this.address.house_number_suffix;
                        }
                        parts.push(streetPart);
                    }

                    if (this.address.postal_code && this.address.city) {
                        parts.push(this.address.postal_code + ' ' + this.address.city);
                    }

                    if (this.address.state) {
                        parts.push(this.address.state);
                    }

                    if (this.address.country) {
                        parts.push(this.address.country);
                    }

                    return parts.join(', ');
                }
            }
        });
    </script>

    <script>
        // Function to initialize address lookup button
        function initializeAddressLookupButton(addressId) {
            const buttonId = addressId + '-lookup-btn';

            const button = document.querySelector('#' + buttonId);

            if (button && !button.hasAttribute('data-lookup-initialized')) {
                button.setAttribute('data-lookup-initialized', 'true');
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const lookupBtn = e.target;
                    const addressConfig = window.addressComponents[addressId];

                    if (!addressConfig) {
                        console.error('Address component config not found for:', addressId);
                        return;
                    }
                    // Only target fields within this specific address component
                    const addressContainer = lookupBtn.closest('.flex.flex-col.gap-4');
                    const postcode = addressContainer.querySelector('#' + addressConfig.postalCodeId);
                    const huisnummer = addressContainer.querySelector('#' + addressConfig.houseNumberId);
                    const street = addressContainer.querySelector('#' + addressConfig.streetId);
                    const city = addressContainer.querySelector('#' + addressConfig.cityId);
                    const state = addressContainer.querySelector('#' + addressConfig.stateId);

                    if (!postcode || !huisnummer) {
                        alert('Adresvelden niet gevonden');
                        return;
                    }

                    const postcodeValue = postcode.value.trim();
                    const huisnummerValue = huisnummer.value.trim();

                    if (!postcodeValue || !huisnummerValue) {
                        alert('Vul eerst postcode en huisnummer in.');
                        return;
                    }

                    lookupBtn.disabled = true;
                    lookupBtn.textContent = 'Zoeken...';

                    fetch('/admin/address/lookup?postcode=' + encodeURIComponent(postcodeValue) + '&huisnummer=' + encodeURIComponent(huisnummerValue), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {

                                if (street) {
                                    street.value = data.street || '';
                                    // Trigger input event to notify any listeners
                                    street.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                                if (city) {
                                    city.value = data.city || '';
                                    // Trigger input event to notify any listeners
                                    city.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                                if (state) {
                                    state.value = data.state || '';
                                    // Trigger input event to notify any listeners
                                    state.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            } else {
                                alert(data.message || 'Adres niet gevonden.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Fout bij opzoeken adres: ' + error.message);
                        })
                        .finally(() => {
                            lookupBtn.disabled = false;
                            lookupBtn.textContent = 'Adres opzoeken';
                        });
                });
            }
        }

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for all elements to be loaded
            setTimeout(function() {
                initializeAddressLookupButton('{{ $addressId }}');

                // Also try to initialize any other address buttons that might exist
                const allAddressButtons = document.querySelectorAll('button[id*="lookup-btn"]');
                allAddressButtons.forEach(function(button) {
                    if (!button.hasAttribute('data-lookup-initialized')) {
                        const buttonId = button.id;
                        const addressId = buttonId.replace('-lookup-btn', '');
                        initializeAddressLookupButton(addressId);
                    }
                });
            }, 100);
        });

        // Also initialize when the organization form is shown
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'add-organization-btn') {
                // Wait a bit for the form to be shown
                setTimeout(function() {
                    // Ensure the address component config exists
                    if (!window.addressComponents['new_org_address']) {
                        window.addressComponents['new_org_address'] = {
                            id: 'new_org_address',
                            postalCodeId: 'new_org_address_postal_code',
                            houseNumberId: 'new_org_address_house_number',
                            streetId: 'new_org_address_street',
                            cityId: 'new_org_address_city',
                            stateId: 'new_org_address_state'
                        };
                    }
                    initializeAddressLookupButton('new_org_address');
                }, 100);
            }
        });

        // Fallback: try to initialize all address buttons periodically
        setInterval(function() {
            const allAddressButtons = document.querySelectorAll('button[id*="lookup-btn"]');
            allAddressButtons.forEach(function(button) {
                if (!button.hasAttribute('data-lookup-initialized')) {
                    const buttonId = button.id;
                    const addressId = buttonId.replace('-lookup-btn', '');
                    console.log('Fallback: Found uninitialized button:', buttonId, 'for address:', addressId);

                    // Create config if it doesn't exist
                    if (!window.addressComponents[addressId]) {
                        window.addressComponents[addressId] = {
                            id: addressId,
                            postalCodeId: addressId + '_postal_code',
                            houseNumberId: addressId + '_house_number',
                            streetId: addressId + '_street',
                            cityId: addressId + '_city',
                            stateId: addressId + '_state'
                        };
                    }

                    initializeAddressLookupButton(addressId);
                }
            });
        }, 1000);
    </script>
@endPushOnce
