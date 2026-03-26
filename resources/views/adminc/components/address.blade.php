{!! view_render_event('admin.address.before') !!}

@props([
    'entity' => null,
    /**
     * Optional explicit Address model (overrides $entity?->address)
     */
    'address' => null,
    /**
     * Prefix for form input names. Example: "visit_address" -> visit_address[postal_code]
     */
    'namePrefix' => null,
    /**
     * Prefix for validation error keys / old() keys. Example: "visit_address" -> visit_address.postal_code
     * Defaults to $namePrefix.
     */
    'errorNamePrefix' => null,
    /**
     * Unique id used for DOM element ids and lookup button.
     */
    'id' => null,
    'hideTitle' => false,
    'readonly' => false,
])

@php
    $addressId = $id ?? 'address';
    $readonlyAttributes = isset($readonly) && $readonly ? ['readonly' => 'readonly', 'disabled' => 'disabled'] : [];

    $resolvedNamePrefix = $namePrefix ?: 'address';
    $resolvedErrorNamePrefix = $errorNamePrefix ?: $resolvedNamePrefix;

    $resolvedAddress = $address ?? $entity?->address;
@endphp

<div class="flex flex-col gap-4">
    @if (!isset($hideTitle) || !$hideTitle)
    <div class="flex items-center justify-between gap-2">
        <p class="text-base font-semibold dark:text-white">
            Adresgegevens
        </p>
        @if ((!isset($readonly) || !$readonly) && $resolvedAddress !== null)
        <button
            type="button"
            id="{{ $addressId }}-clear-btn"
            onclick="clearAddressFields('{{ $addressId }}')"
            class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors"
        >
            <span class="icon-delete text-sm"></span>
            Adres wissen
        </button>
        @endif
    </div>
    @elseif ((!isset($readonly) || !$readonly) && $resolvedAddress !== null)
    {{-- Title hidden but clear button still needed (e.g. clinic form with its own section header) --}}
    <div class="flex justify-end">
        <button
            type="button"
            id="{{ $addressId }}-clear-btn"
            onclick="clearAddressFields('{{ $addressId }}')"
            class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors"
        >
            <span class="icon-delete text-sm"></span>
            Adres wissen
        </button>
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

    {{-- Notice shown after "Wis adres" is clicked; hidden by default --}}
    <div
        id="{{ $addressId }}-cleared-notice"
        style="display:none;"
        class="flex items-center justify-between gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-900/20"
    >
        <div class="flex items-center gap-2 text-sm text-red-700 dark:text-red-400">
            <span class="icon-delete"></span>
            Het adres wordt verwijderd wanneer je opslaat.
        </div>
        <button
            type="button"
            onclick="undoClearAddress('{{ $addressId }}')"
            class="text-xs font-medium text-red-700 underline hover:no-underline dark:text-red-400"
        >
            Ongedaan maken
        </button>
    </div>

    {{-- Hidden flag: JS sets this to "1" when the user clicks "Wis adres". The backend uses it to delete the address. --}}
    <input type="hidden" name="{{ $resolvedNamePrefix }}[_clear]" value="0" id="{{ $addressId }}_clear_flag">

    {{-- Wrapper div: visually hidden when user clears the address (Vue controls the inputs so we use the flag above instead) --}}
    <div id="{{ $addressId }}-fields-wrapper" class="flex flex-col gap-4">

    <!-- Address Lookup Panel -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-wrap md:flex-nowrap gap-3">
            <!-- Postal Code -->
            <div class="flex-1 min-w-[150px]">
                <x-adminc::components.field
                    type="text"
                    name="{{ $resolvedNamePrefix }}[postal_code]"
                    error-name="{{ $resolvedErrorNamePrefix }}.postal_code"
                    label="Postcode"
                    value="{{ old($resolvedErrorNamePrefix.'.postal_code', $resolvedAddress?->postal_code ?? '') }}"
                    placeholder="1234 AB"
                    id="{{ $addressId }}_postal_code"
                    :readonly="isset($readonly) && $readonly"
                />
            </div>

            <!-- House Number -->
            <div class="flex-1 min-w-[150px]">
                <x-adminc::components.field
                    type="text"
                    name="{{ $resolvedNamePrefix }}[house_number]"
                    error-name="{{ $resolvedErrorNamePrefix }}.house_number"
                    label="Huisnummer"
                    value="{{ old($resolvedErrorNamePrefix.'.house_number', $resolvedAddress?->house_number ?? '') }}"
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
            name="{{ $resolvedNamePrefix }}[street]"
            error-name="{{ $resolvedErrorNamePrefix }}.street"
            label="Straat"
            value="{{ old($resolvedErrorNamePrefix.'.street', $resolvedAddress?->street ?? '') }}"
            placeholder="Straatnaam"
            id="{{ $addressId }}_street"
            :readonly="isset($readonly) && $readonly"
        />

        <!-- House Number Suffix -->
        <x-adminc::components.field
            type="text"
            name="{{ $resolvedNamePrefix }}[house_number_suffix]"
            error-name="{{ $resolvedErrorNamePrefix }}.house_number_suffix"
            label="Toevoeging"
            value="{{ old($resolvedErrorNamePrefix.'.house_number_suffix', $resolvedAddress?->house_number_suffix ?? '') }}"
            placeholder="A, 1e verdieping, etc."
            id="{{ $addressId }}_house_number_suffix"
            :readonly="isset($readonly) && $readonly"
        />

        <!-- City -->
        <x-adminc::components.field
            type="text"
            name="{{ $resolvedNamePrefix }}[city]"
            error-name="{{ $resolvedErrorNamePrefix }}.city"
            label="Stad"
            value="{{ old($resolvedErrorNamePrefix.'.city', $resolvedAddress?->city ?? '') }}"
            placeholder="Amsterdam"
            id="{{ $addressId }}_city"
            :readonly="isset($readonly) && $readonly"
        />

        <!-- State -->
        <x-adminc::components.field
            type="text"
            name="{{ $resolvedNamePrefix }}[state]"
            error-name="{{ $resolvedErrorNamePrefix }}.state"
            label="Provincie"
            value="{{ old($resolvedErrorNamePrefix.'.state', $resolvedAddress?->state ?? '') }}"
            placeholder="Noord-Holland"
            id="{{ $addressId }}_state"
            :readonly="isset($readonly) && $readonly"
        />

        <!-- Country -->
        <x-adminc::components.field
            class="col-span-2"
            type="text"
            name="{{ $resolvedNamePrefix }}[country]"
            error-name="{{ $resolvedErrorNamePrefix }}.country"
            label="Land"
            value="{{ old($resolvedErrorNamePrefix.'.country', $resolvedAddress?->country ?? '') }}"
            placeholder="Nederland"
            id="{{ $addressId }}_country"
            :readonly="isset($readonly) && $readonly"
        />
    </div>

    <v-address-preview
        :address='@json($resolvedAddress ?? new stdClass())'
    ></v-address-preview>

    </div>{{-- end #{{ $addressId }}-fields-wrapper --}}
</div>

{!! view_render_event('admin.address.after') !!}

@pushOnce('scripts')

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
        // Global config store (multiple address components per page)
        window.addressComponents = window.addressComponents || {};

        function clearAddressFields(addressId) {
            // Set the hidden flag — the backend will use this to delete the address.
            // We do NOT try to clear Vue-managed inputs directly; Vue would restore them on the next render.
            const flag = document.getElementById(addressId + '_clear_flag');
            if (flag) flag.value = '1';

            // Hide the form section, show the notice.
            const wrapper = document.getElementById(addressId + '-fields-wrapper');
            if (wrapper) wrapper.style.display = 'none';

            const notice = document.getElementById(addressId + '-cleared-notice');
            if (notice) notice.style.display = '';

            const clearBtn = document.getElementById(addressId + '-clear-btn');
            if (clearBtn) clearBtn.style.display = 'none';
        }

        function undoClearAddress(addressId) {
            // Reset the flag so the backend keeps the address.
            const flag = document.getElementById(addressId + '_clear_flag');
            if (flag) flag.value = '0';

            // Restore the form section.
            const wrapper = document.getElementById(addressId + '-fields-wrapper');
            if (wrapper) wrapper.style.display = '';

            const notice = document.getElementById(addressId + '-cleared-notice');
            if (notice) notice.style.display = 'none';

            const clearBtn = document.getElementById(addressId + '-clear-btn');
            if (clearBtn) clearBtn.style.display = '';
        }

        // Function to initialize address lookup button
        function initializeAddressLookupButton(addressId) {
            const buttonId = addressId + '-lookup-btn';

            const button = document.querySelector('#' + buttonId);

            if (button && !button.hasAttribute('data-lookup-initialized')) {
                button.setAttribute('data-lookup-initialized', 'true');
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const lookupBtn = e.currentTarget;

                    // Ensure config exists (fallback)
                    if (!window.addressComponents[addressId]) {
                        window.addressComponents[addressId] = {
                            id: addressId,
                            postalCodeId: addressId + '_postal_code',
                            houseNumberId: addressId + '_house_number',
                            streetId: addressId + '_street',
                            cityId: addressId + '_city',
                            stateId: addressId + '_state',
                        };
                    }

                    const addressConfig = window.addressComponents[addressId];

                    // IDs are unique per component, so global lookup is safe and more reliable than closest()-scoping.
                    const postcode = document.getElementById(addressConfig.postalCodeId);
                    const huisnummer = document.getElementById(addressConfig.houseNumberId);
                    const street = document.getElementById(addressConfig.streetId);
                    const city = document.getElementById(addressConfig.cityId);
                    const state = document.getElementById(addressConfig.stateId);

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

        function initializeAllAddressLookupButtons() {
            const allAddressButtons = document.querySelectorAll('button[id$="-lookup-btn"]');

            allAddressButtons.forEach(function (button) {
                if (button.hasAttribute('data-lookup-initialized')) {
                    return;
                }

                const buttonId = button.id;
                const addressId = buttonId.replace('-lookup-btn', '');
                initializeAddressLookupButton(addressId);
            });
        }

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for all elements to be loaded
            setTimeout(function() {
                initializeAllAddressLookupButtons();
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
            initializeAllAddressLookupButtons();
        }, 1000);
    </script>
@endPushOnce
