{!! view_render_event('admin.leads.organization.before') !!}

<!-- Lead Organization Section -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.leads.common.organization.title')
    </x-admin::form.control-group.label>

    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
        <i>Koppel een organisatie voor facturatie doeleinden (optioneel)</i>
    </p>

    <!-- Organization Lookup -->
    <div class="mb-4">
        <x-admin::lookup
            src="{{ route('admin.contacts.organizations.search') }}"
            name="organization_id"
            label="Naam"
            value="{{ json_encode($organization) }}"
            placeholder="Zoek organisatie..."
            :can-add-new="false"
        />
        <x-admin::form.control-group.error control-name="organization_id" />
    </div>

    <!-- Add New Organization Button -->
    <div class="mb-4">
        <button
            type="button"
            id="add-organization-btn"
            class="secondary-button"
            onclick="toggleOrganizationForm()"
        >
            <i class="icon-plus text-xs mr-1"></i>
            Nieuwe organisatie toevoegen
        </button>
    </div>

    <!-- Collapsible Organization Form -->
    <div id="organization-form" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-gray-800 dark:border-gray-700">
        <div class="mb-4">
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">
                Nieuwe organisatie aanmaken
            </h4>
        </div>

        <!-- Organization Name -->
        <div class="mb-4">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    Organisatienaam <span class="text-red-500">*</span>
                </x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="text"
                    name="new_organization_name"
                    id="new_organization_name"
                    placeholder="Naam van de organisatie"
                    required
                />
                <x-admin::form.control-group.error control-name="new_organization_name" />
            </x-admin::form.control-group>
        </div>

        <!-- Organization Address -->
        <div class="mb-4">
            <div class="flex flex-col gap-1">
                <p class="text-base font-semibold dark:text-white">
                    Adresgegevens <span class="text-red-500">*</span>
                </p>
            </div>

            <!-- Address Lookup Panel -->
            <div class="bg-white border border-gray-200 rounded-lg p-4 mt-2 dark:bg-gray-700 dark:border-gray-600">
                <div class="flex flex-wrap md:flex-nowrap gap-3">
                    <!-- Postal Code -->
                    <div class="flex-1 min-w-[150px]">
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>Postcode</x-admin::form.control-group.label>
                            <x-admin::form.control-group.control
                                type="text"
                                name="new_organization_address[postal_code]"
                                id="new_org_address_postal_code"
                                placeholder="1234 AB"
                                required
                            />
                            <x-admin::form.control-group.error control-name="new_organization_address.postal_code"/>
                        </x-admin::form.control-group>
                    </div>

                    <!-- House Number -->
                    <div class="flex-1 min-w-[150px]">
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>Huisnummer</x-admin::form.control-group.label>
                            <x-admin::form.control-group.control
                                type="text"
                                name="new_organization_address[house_number]"
                                id="new_org_address_house_number"
                                placeholder="123"
                                required
                            />
                            <x-admin::form.control-group.error control-name="new_organization_address.house_number"/>
                        </x-admin::form.control-group>
                    </div>

                    <!-- Lookup button -->
                    <div class="flex-shrink-0 flex flex-col justify-end">
                        <div class="mb-4 flex items-end h-full">
                            <button type="button" id="new-org-address-lookup-btn"
                                    class="address-lookup-button px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 min-w-[120px]">
                                Adres opzoeken
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Details -->
            <div class="grid grid-cols-2 gap-4 mt-4">
                <!-- Street -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Straat
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="text"
                        name="new_organization_address[street]"
                        id="new_org_address_street"
                        placeholder="Straatnaam"
                        required
                    />
                    <x-admin::form.control-group.error control-name="new_organization_address.street"/>
                </x-admin::form.control-group>

                <!-- House Number Suffix -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Toevoeging
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="text"
                        name="new_organization_address[house_number_suffix]"
                        id="new_org_address_house_number_suffix"
                        placeholder="A, 1e verdieping, etc."
                    />
                    <x-admin::form.control-group.error control-name="new_organization_address.house_number_suffix"/>
                </x-admin::form.control-group>

                <!-- City -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Stad
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="text"
                        name="new_organization_address[city]"
                        id="new_org_address_city"
                        placeholder="Amsterdam"
                        required
                    />
                    <x-admin::form.control-group.error control-name="new_organization_address.city"/>
                </x-admin::form.control-group>

                <!-- State -->
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Provincie
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="text"
                        name="new_organization_address[state]"
                        id="new_org_address_state"
                        placeholder="Noord-Holland"
                        required
                    />
                    <x-admin::form.control-group.error control-name="new_organization_address.state"/>
                </x-admin::form.control-group>

                <!-- Country -->
                <x-admin::form.control-group class="col-span-2">
                    <x-admin::form.control-group.label>
                        Land
                    </x-admin::form.control-group.label>
                    <x-admin::form.control-group.control
                        type="text"
                        name="new_organization_address[country]"
                        id="new_org_address_country"
                        placeholder="Nederland"
                        value="Nederland"
                        required
                    />
                    <x-admin::form.control-group.error control-name="new_organization_address.country"/>
                </x-admin::form.control-group>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2">
            <button
                type="button"
                id="save-organization-btn"
                class="primary-button"
                onclick="saveNewOrganization()"
            >
                <i class="icon-check text-xs mr-1"></i>
                Organisatie opslaan
            </button>
            <button
                type="button"
                class="secondary-button"
                onclick="cancelOrganizationForm()"
            >
                Annuleren
            </button>
        </div>
    </div>

    <!-- Hidden field for selected organization ID -->
    <input type="hidden" name="organization_id" id="selected_organization_id" value="{{ $organization?->id ?? '' }}" />
</x-admin::form.control-group>

{!! view_render_event('admin.leads.organization.after') !!}

@pushOnce('scripts')
<script>
function toggleOrganizationForm() {
    const form = document.getElementById('organization-form');
    const btn = document.getElementById('add-organization-btn');
    
    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        btn.innerHTML = '<i class="icon-minus text-xs mr-1"></i>Nieuwe organisatie formulier verbergen';
    } else {
        form.classList.add('hidden');
        btn.innerHTML = '<i class="icon-plus text-xs mr-1"></i>Nieuwe organisatie toevoegen';
        // Clear form when hiding
        clearOrganizationForm();
    }
}

function cancelOrganizationForm() {
    const form = document.getElementById('organization-form');
    form.classList.add('hidden');
    document.getElementById('add-organization-btn').innerHTML = '<i class="icon-plus text-xs mr-1"></i>Nieuwe organisatie toevoegen';
    clearOrganizationForm();
}

function clearOrganizationForm() {
    const nameField = document.getElementById('new_organization_name');
    const postcodeField = document.getElementById('new_org_address_postal_code');
    const houseNumberField = document.getElementById('new_org_address_house_number');
    const streetField = document.getElementById('new_org_address_street');
    const suffixField = document.getElementById('new_org_address_house_number_suffix');
    const cityField = document.getElementById('new_org_address_city');
    const stateField = document.getElementById('new_org_address_state');
    const countryField = document.getElementById('new_org_address_country');
    
    if (nameField) nameField.value = '';
    if (postcodeField) postcodeField.value = '';
    if (houseNumberField) houseNumberField.value = '';
    if (streetField) streetField.value = '';
    if (suffixField) suffixField.value = '';
    if (cityField) cityField.value = '';
    if (stateField) stateField.value = '';
    if (countryField) countryField.value = 'Nederland';
}

async function saveNewOrganization() {
    const nameField = document.getElementById('new_organization_name');
    const postalCodeField = document.getElementById('new_org_address_postal_code');
    const houseNumberField = document.getElementById('new_org_address_house_number');
    const streetField = document.getElementById('new_org_address_street');
    const cityField = document.getElementById('new_org_address_city');
    const stateField = document.getElementById('new_org_address_state');
    const countryField = document.getElementById('new_org_address_country');
    const suffixField = document.getElementById('new_org_address_house_number_suffix');
    
    const name = nameField ? nameField.value.trim() : '';
    const postalCode = postalCodeField ? postalCodeField.value.trim() : '';
    const houseNumber = houseNumberField ? houseNumberField.value.trim() : '';
    const street = streetField ? streetField.value.trim() : '';
    const city = cityField ? cityField.value.trim() : '';
    const state = stateField ? stateField.value.trim() : '';
    const country = countryField ? countryField.value.trim() : '';

    // Basic validation
    if (!name || !postalCode || !houseNumber || !street || !city || !state || !country) {
        alert('Vul alle verplichte velden in.');
        return;
    }

    const saveBtn = document.getElementById('save-organization-btn');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="icon-spinner text-xs mr-1"></i>Opslaan...';

    try {
        const formData = new FormData();
        formData.append('name', name);
        formData.append('address[postal_code]', postalCode);
        formData.append('address[house_number]', houseNumber);
        formData.append('address[street]', street);
        formData.append('address[house_number_suffix]', suffixField ? suffixField.value.trim() : '');
        formData.append('address[city]', city);
        formData.append('address[state]', state);
        formData.append('address[country]', country);

        const response = await fetch('{{ route("admin.contacts.organizations.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const result = await response.json();

        if (response.ok && result.data) {
            // Update the organization lookup with the new organization
            const organizationLookup = document.querySelector('[name="organization_id"]');
            if (organizationLookup) {
                // Set the value to the new organization
                document.getElementById('selected_organization_id').value = result.data.id;
                
                // Update the lookup display (this depends on the lookup component implementation)
                const lookupInput = organizationLookup.closest('.lookup-container')?.querySelector('input[type="text"]');
                if (lookupInput) {
                    lookupInput.value = result.data.name;
                }
            }

            // Hide the form and clear it
            cancelOrganizationForm();
            
            // Show success message
            if (window.$emitter) {
                window.$emitter.emit('add-flash', {
                    type: 'success',
                    message: 'Organisatie succesvol aangemaakt!'
                });
            } else {
                alert('Organisatie succesvol aangemaakt!');
            }
        } else {
            throw new Error(result.message || 'Er is een fout opgetreden bij het aanmaken van de organisatie.');
        }
    } catch (error) {
        console.error('Error saving organization:', error);
        alert('Fout bij opslaan: ' + error.message);
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}

// Address lookup for new organization
document.addEventListener('DOMContentLoaded', function() {
    const lookupBtn = document.getElementById('new-org-address-lookup-btn');
    if (lookupBtn) {
        lookupBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Use the correct IDs for the new organization form
            const postcode = document.getElementById('new_org_address_postal_code');
            const huisnummer = document.getElementById('new_org_address_house_number');
            const street = document.getElementById('new_org_address_street');
            const city = document.getElementById('new_org_address_city');
            const state = document.getElementById('new_org_address_state');

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
                            street.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        if (city) {
                            city.value = data.city || '';
                            city.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        if (state) {
                            state.value = data.state || '';
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
});
</script>
@endPushOnce
