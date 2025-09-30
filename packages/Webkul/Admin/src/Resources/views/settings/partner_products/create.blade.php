<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.partner_products.index.create.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.partner_products.store')" method="POST">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.partner_products.create" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.partner_products.index.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.partner_products.index.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.partner_products.index.create.name')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            name="name"
                            rules="required|min:1|max:255"
                            :label="trans('admin::app.settings.partner_products.index.create.name')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.name')"
                        />

                        <x-admin::form.control-group.error control-name="name" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.partner_products.index.create.currency')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="currency"
                            value="{{ old('currency', $defaultCurrency) }}"
                            rules="required"
                            :label="trans('admin::app.settings.partner_products.index.create.currency')"
                        >
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency['code'] }}" @selected(old('currency', $defaultCurrency) === $currency['code'])>{{ $currency['label'] }}</option>
                            @endforeach
                        </x-admin::form.control-group.control>

                        <x-admin::form.control-group.error control-name="currency" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.partner_products.index.create.sales_price')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="price"
                            name="sales_price"
                            rules="required"
                            :label="trans('admin::app.settings.partner_products.index.create.sales_price')"
                            :placeholder="trans('admin::app.settings.partner_products.index.create.sales_price')"
                        />

                        <x-admin::form.control-group.error control-name="sales_price" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.active')
                        </x-admin::form.control-group.label>

                        <input type="hidden" name="active" value="0" />
                        <x-admin::form.control-group.control
                            type="checkbox"
                            name="active"
                            value="1"
                            :label="trans('admin::app.settings.partner_products.index.create.active')"
                            :checked="old('active', 1)"
                        />

                        <x-admin::form.control-group.error control-name="active" />
                    </x-admin::form.control-group>
                </div>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="description"
                        :label="trans('admin::app.settings.partner_products.index.create.description')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.description')"
                    />

                    <x-admin::form.control-group.error control-name="description" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.discount_info')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="discount_info"
                        :label="trans('admin::app.settings.partner_products.index.create.discount_info')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.discount_info')"
                    />

                    <x-admin::form.control-group.error control-name="discount_info" />
                </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.partner_products.index.create.resource_type')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="resource_type_id"
                            rules="required|numeric"
                            :label="trans('admin::app.settings.partner_products.index.create.resource_type')"
                        >
                            <option value="">@lang('admin::app.select')</option>
                            @foreach ($resourceTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </x-admin::form.control-group.control>

                        <x-admin::form.control-group.error control-name="resource_type_id" />
                    </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.clinics.index.title')
                    </x-admin::form.control-group.label>

                    <select
                        id="clinics-select"
                        name="clinics[]"
                        multiple
                        class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    >
                        @foreach ($clinics as $clinic)
                            <option value="{{ $clinic->id }}" @selected(collect(old('clinics', []))->contains($clinic->id))>{{ $clinic->name }}</option>
                        @endforeach
                    </select>

                    <x-admin::form.control-group.error control-name="clinics" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.resources.index.title')
                    </x-admin::form.control-group.label>

                    <select
                        id="resources-select"
                        name="resources[]"
                        multiple
                        disabled
                        class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                    </select>

                    <p id="resources-hint" class="mt-1 text-xs text-gray-600 dark:text-gray-400" style="display: none;">
                        Gefilterd op gekozen kliniek(en)
                    </p>

                    <x-admin::form.control-group.error control-name="resources" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.settings.partner_products.index.create.partner_name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="partner_name"
                        rules="required|min:1|max:100"
                        :label="trans('admin::app.settings.partner_products.index.create.partner_name')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.partner_name')"
                    />

                    <x-admin::form.control-group.error control-name="partner_name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.clinic_description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="clinic_description"
                        :label="trans('admin::app.settings.partner_products.index.create.clinic_description')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.clinic_description')"
                    />

                    <x-admin::form.control-group.error control-name="clinic_description" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.partner_products.index.create.duration')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="number"
                        name="duration"
                        
                        :label="trans('admin::app.settings.partner_products.index.create.duration')"
                        :placeholder="trans('admin::app.settings.partner_products.index.create.duration')"
                    />

                    <x-admin::form.control-group.error control-name="duration" />
                </x-admin::form.control-group>

                <x-admin::partner-product-lookup
                    :src="route('admin.settings.partner_products.search')"
                    name="related_products"
                    :label="trans('admin::app.settings.partner_products.index.create.related_products')"
                    :search-placeholder="trans('admin::app.settings.partner_products.index.create.search_related_products')"
                    :value="[]"
                />
            </div>
        </div>
    </x-admin::form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const clinicsSelect = document.getElementById('clinics-select');
                const resourcesSelect = document.getElementById('resources-select');
                const resourcesHint = document.getElementById('resources-hint');

                if (!clinicsSelect || !resourcesSelect || !resourcesHint) {
                    console.error('Required elements not found');
                    return;
                }

                console.log('Clinics select initialized:', clinicsSelect);

                let lastSelectedClinics = '';
                let isLoading = false;

                function getSelectedClinicsKey() {
                    return Array.from(clinicsSelect.selectedOptions)
                        .map(opt => opt.value)
                        .sort()
                        .join(',');
                }

                function loadResources() {
                    if (isLoading) {
                        console.log('Already loading, skipping...');
                        return;
                    }

                    const selectedClinics = Array.from(clinicsSelect.selectedOptions).map(opt => opt.value);
                    
                    console.log('loadResources called, selected clinics:', selectedClinics);
                    
                    if (selectedClinics.length === 0) {
                        resourcesSelect.disabled = true;
                        resourcesSelect.innerHTML = '';
                        resourcesHint.style.display = 'none';
                        return;
                    }

                    isLoading = true;

                    // Store currently selected resources
                    const currentlySelected = Array.from(resourcesSelect.selectedOptions).map(opt => opt.value);

                    // Fetch filtered resources
                    const params = new URLSearchParams();
                    selectedClinics.forEach(id => params.append('clinic_ids[]', id));

                    const url = '{{ route("admin.settings.resources.filter_by_clinics") }}?' + params.toString();
                    console.log('Fetching from:', url);

                    fetch(url)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Received resources:', data.data);
                            
                            resourcesSelect.innerHTML = '';
                            resourcesHint.style.display = 'block';
                            
                            if (data.data && data.data.length > 0) {
                                data.data.forEach(resource => {
                                    const option = document.createElement('option');
                                    option.value = resource.id;
                                    option.textContent = resource.name;
                                    
                                    // Reselect if it was previously selected and is still valid
                                    if (currentlySelected.includes(resource.id.toString())) {
                                        option.selected = true;
                                    }
                                    
                                    resourcesSelect.appendChild(option);
                                });
                                resourcesSelect.disabled = false;
                            } else {
                                // If no resources available, show message but still enable the field
                                const option = document.createElement('option');
                                option.disabled = true;
                                option.textContent = 'Geen resources beschikbaar voor deze kliniek(en)';
                                resourcesSelect.appendChild(option);
                                resourcesSelect.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching resources:', error);
                            resourcesSelect.innerHTML = '<option disabled>Fout bij laden van resources</option>';
                            resourcesSelect.disabled = false;
                        })
                        .finally(() => {
                            isLoading = false;
                        });
                }

                function checkForChanges() {
                    const currentKey = getSelectedClinicsKey();
                    if (currentKey !== lastSelectedClinics) {
                        console.log('Clinic selection changed from "' + lastSelectedClinics + '" to "' + currentKey + '"');
                        lastSelectedClinics = currentKey;
                        loadResources();
                    }
                }

                // Listen to multiple events with capture phase
                ['change', 'input', 'click', 'mouseup', 'keyup', 'blur', 'focus'].forEach(eventType => {
                    clinicsSelect.addEventListener(eventType, function(e) {
                        console.log(eventType + ' event triggered on clinics select');
                        setTimeout(checkForChanges, 50);
                    }, true); // Use capture phase
                });

                // Add listeners to the parent form as well
                const form = clinicsSelect.closest('form');
                if (form) {
                    console.log('Adding listeners to form');
                    form.addEventListener('click', function(e) {
                        if (e.target === clinicsSelect || e.target.closest('#clinics-select')) {
                            console.log('Click detected on or near clinics select via form');
                            setTimeout(checkForChanges, 100);
                        }
                    }, true);
                }
                
                // Polling as ultimate fallback - check every 500ms
                console.log('Starting polling interval');
                setInterval(function() {
                    checkForChanges();
                }, 500);
                
                // Also trigger on load if clinics are already selected
                lastSelectedClinics = getSelectedClinicsKey();
                if (clinicsSelect.selectedOptions.length > 0) {
                    loadResources();
                }
            });
        </script>
    @endpush
</x-admin::layouts>

