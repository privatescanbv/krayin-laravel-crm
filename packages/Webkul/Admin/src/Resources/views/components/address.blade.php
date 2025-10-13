{!! view_render_event('admin.address.before') !!}

@php
    $addressId = $id ?? 'address';
@endphp

<div class="flex flex-col gap-4">
    @if(!isset($hideTitle) || !$hideTitle)
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold dark:text-white">
            Adresgegevens
        </p>
    </div>
    @endif

    <!-- Address Lookup Panel -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-wrap md:flex-nowrap gap-3">
            <!-- Postal Code -->
            <div class="flex-1 min-w-[150px]">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>Postcode</x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="text"
                        name="{{ $namePrefix ?? 'address' }}[postal_code]"
                        :value="old('address.postal_code', $entity?->address?->postal_code ?? '')"
                        placeholder="1234 AB"
                        id="{{ $addressId }}_postal_code"
                    />
                    <x-admin::form.control-group.error control-name="address.postal_code"/>
                </x-admin::form.control-group>
            </div>

            <!-- House Number -->
            <div class="flex-1 min-w-[150px]">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>Huisnummer</x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="text"
                        name="{{ $namePrefix ?? 'address' }}[house_number]"
                        :value="old('address.house_number', $entity?->address?->house_number ?? '')"
                        placeholder="123"
                        id="{{ $addressId }}_house_number"
                    />
                    <x-admin::form.control-group.error control-name="address.house_number"/>
                </x-admin::form.control-group>
            </div>

            <!-- Lookup button -->
            <div class="flex-shrink-0 flex flex-col justify-end">
                <div class="mb-4 flex items-end h-full">
                <button type="button" id="{{ $addressId }}-lookup-btn"
                        class="address-lookup-button px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 min-w-[120px]">
                    Adres opzoeken
                </button>
                </div>
            </div>
        </div>
    </div>


    <!-- Address Details -->
    <div class="grid grid-cols-2 gap-4">
        <!-- Street -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Straat
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="{{ $namePrefix ?? 'address' }}[street]"
                :value="old('address.street', $entity?->address?->street ?? '')"
                placeholder="Straatnaam"
                id="{{ $addressId }}_street"
            />

            <x-admin::form.control-group.error control-name="address.street"/>
        </x-admin::form.control-group>

        <!-- House Number Suffix -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Toevoeging
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="{{ $namePrefix ?? 'address' }}[house_number_suffix]"
                :value="old('address.house_number_suffix', $entity?->address?->house_number_suffix ?? '')"
                placeholder="A, 1e verdieping, etc."
                id="{{ $addressId }}_house_number_suffix"
            />

            <x-admin::form.control-group.error control-name="address.house_number_suffix"/>
        </x-admin::form.control-group>

        <!-- City -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Stad
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="{{ $namePrefix ?? 'address' }}[city]"
                :value="old('address.city', $entity?->address?->city ?? '')"
                placeholder="Amsterdam"
                id="{{ $addressId }}_city"
            />

            <x-admin::form.control-group.error control-name="address.city"/>
        </x-admin::form.control-group>

        <!-- State -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Provincie
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="{{ $namePrefix ?? 'address' }}[state]"
                :value="old('address.state', $entity?->address?->state ?? '')"
                placeholder="Noord-Holland"
                id="{{ $addressId }}_state"
            />

            <x-admin::form.control-group.error control-name="address.state"/>
        </x-admin::form.control-group>

        <!-- Country -->
        <x-admin::form.control-group class="col-span-2">
            <x-admin::form.control-group.label>
                Land
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="{{ $namePrefix ?? 'address' }}[country]"
                :value="old('address.country', $entity?->address?->country ?? 'Nederland')"
                placeholder="Nederland"
                id="{{ $addressId }}_country"
            />

            <x-admin::form.control-group.error control-name="address.country"/>
        </x-admin::form.control-group>
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
                },
                mounted() {
                    this.registerAddressLookupButtons();
                },

                methods: {
                    // Registreer knoppen
                    registerAddressLookupButtons() {
                        const buttons = document.querySelectorAll('.address-lookup-button');

                        buttons.forEach((lookupBtn) => {
                            if (lookupBtn.hasAttribute('data-lookup-initialized')) {
                                console.log('Button already initialized');
                                return;
                            }

                            console.log('Initializing button:', lookupBtn.id);
                            lookupBtn.setAttribute('data-lookup-initialized', 'true');
                            lookupBtn.addEventListener('click', this.handleAddressLookup);
                        });
                    },

                    handleAddressLookup(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const lookupBtn = e.target;
                        const addressId = '{{ $addressId }}';
                        const addressConfig = window.addressComponents[addressId];
                        
                        if (!addressConfig) {
                            console.error('Address component config not found for:', addressId);
                            return;
                        }
                        
                        const postcode = document.querySelector('#' + addressConfig.postalCodeId);
                        const huisnummer = document.querySelector('#' + addressConfig.houseNumberId);
                        const street = document.querySelector('#' + addressConfig.streetId);
                        const city = document.querySelector('#' + addressConfig.cityId);
                        const state = document.querySelector('#' + addressConfig.stateId);

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
                                console.log('Lookup response:', data);
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
                    }
                }

            });
        </script>
    @endverbatim
@endPushOnce
