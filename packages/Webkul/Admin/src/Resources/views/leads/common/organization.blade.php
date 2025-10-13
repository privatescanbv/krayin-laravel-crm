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
            value="{{ $organization ? json_encode($organization) : '' }}"
            placeholder="Zoek organisatie..."
            :can-add-new="false"
        />
        <x-admin::form.control-group.error control-name="organization_id" />
        
        <!-- Selected Organization Info -->
        <div id="selected-organization-info" class="mt-2 p-2 bg-green-100 border border-green-300 rounded text-sm text-green-800" style="display: none;">
            <i class="icon-check-circle"></i> <span id="selected-org-name">Geen organisatie geselecteerd</span>
        </div>
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

            <!-- Address Component -->
            <div class="mt-2">
                @include('admin::components.address', [
                    'id' => 'new_org_address',
                    'namePrefix' => 'new_organization_address',
                    'entity' => null,
                    'hideTitle' => true
                ])
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
const ORGANIZATION_STORE_URL = '{{ route("admin.contacts.organizations.store") }}';

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
    
    // Hide selected organization info
    const selectedOrgInfo = document.getElementById('selected-organization-info');
    if (selectedOrgInfo) {
        selectedOrgInfo.style.display = 'none';
    }
}

// Show selected organization info when organization is selected via lookup
function showSelectedOrganization(orgName) {
    console.log('DEBUG: showSelectedOrganization called with:', orgName);
    const selectedOrgInfo = document.getElementById('selected-organization-info');
    console.log('DEBUG: selectedOrgInfo found:', !!selectedOrgInfo);
    if (selectedOrgInfo && orgName) {
        selectedOrgInfo.innerHTML = '<i class="icon-check-circle"></i> ' + orgName;
        selectedOrgInfo.style.display = 'block';
        console.log('DEBUG: Organization info displayed');
    } else {
        console.log('DEBUG: Could not display organization info');
    }
}

async function saveNewOrganization() {
    console.log('DEBUG: saveNewOrganization called');
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

        // Get CSRF token safely
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '';
        
        
        // Add CSRF token to form data as well
        if (csrfToken) {
            formData.append('_token', csrfToken);
        }

        const response = await fetch(ORGANIZATION_STORE_URL, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        const result = await response.json();

        if (response.ok && result.data) {
            // Update the organization lookup with the new organization
            const organizationLookup = document.querySelector('[name="organization_id"]');
            if (organizationLookup) {
                // Set the value to the new organization
                const selectedOrgId = document.getElementById('selected_organization_id');
                if (selectedOrgId && result.data.id) {
                    selectedOrgId.value = result.data.id;
                }

                // Update the lookup display
                const lookupInput = organizationLookup.closest('.lookup-container')?.querySelector('input[type="text"]');
                if (lookupInput && result.data.name) {
                    lookupInput.value = result.data.name;
                    // Trigger change event to update any Vue components
                    lookupInput.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // Also try to update the hidden input directly
                if (organizationLookup && result.data.id) {
                    organizationLookup.value = result.data.id;
                    organizationLookup.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // Show selected organization info
                console.log('DEBUG: Trying to show selected organization info');
                const selectedOrgInfo = document.getElementById('selected-organization-info');
                console.log('DEBUG: selectedOrgInfo found:', !!selectedOrgInfo);
                console.log('DEBUG: result.data.name:', result.data.name);
                if (selectedOrgInfo && result.data.name) {
                    // Update the content directly
                    selectedOrgInfo.innerHTML = '<i class="icon-check-circle"></i> ' + result.data.name;
                    selectedOrgInfo.style.display = 'block';
                    console.log('DEBUG: Organization info should now be visible');
                    console.log('DEBUG: Element display style:', selectedOrgInfo.style.display);
                } else {
                    console.log('DEBUG: Could not show organization info - missing elements or data');
                }
            }

            // Show success message
            if (window.$emitter) {
                window.$emitter.emit('add-flash', {
                    type: 'success',
                    message: 'Organisatie succesvol aangemaakt en geselecteerd!'
                });
            }

            // Hide the form but don't clear the organization info
            const organizationForm = document.getElementById('new-organization-form');
            if (organizationForm) {
                organizationForm.style.display = 'none';
            }
            
            // Also hide the "Add New Organization" button
            const addButton = document.getElementById('add-organization-btn');
            if (addButton) {
                addButton.style.display = 'none';
            }
            
            // Clear form fields but keep organization info visible
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
            
            // Debug: Check if organization info is still visible
            setTimeout(() => {
                const selectedOrgInfo = document.getElementById('selected-organization-info');
                if (selectedOrgInfo) {
                    console.log('DEBUG: After form clear - Element display style:', selectedOrgInfo.style.display);
                    console.log('DEBUG: After form clear - Element computed style:', window.getComputedStyle(selectedOrgInfo).display);
                    console.log('DEBUG: After form clear - Element content:', selectedOrgInfo.innerHTML);
                }
            }, 100);
        } else {
            throw new Error(result.message || 'Er is een fout opgetreden bij het aanmaken van de organisatie.');
        }
    } catch (error) {
        alert('Fout bij opslaan: ' + error.message);
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}

// Address lookup for new organization
document.addEventListener('DOMContentLoaded', function() {
    const lookupBtn = document.getElementById('new_org_address-lookup-btn');
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

// Debug: Check if HTML elements exist
console.log('DEBUG: HTML elements check:');
console.log('DEBUG: selected-organization-info:', document.getElementById('selected-organization-info'));
console.log('DEBUG: selected-org-name:', document.getElementById('selected-org-name'));

// Test: Make element visible for debugging
setTimeout(() => {
    const testElement = document.getElementById('selected-organization-info');
    if (testElement) {
        testElement.style.display = 'block';
        testElement.innerHTML = '<i class="icon-check-circle"></i> TEST ORGANISATIE';
        console.log('DEBUG: Test element made visible');
    }
}, 2000);
</script>
@endPushOnce
