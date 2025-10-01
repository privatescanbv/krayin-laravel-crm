<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.partner_products.index.edit.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.partner_products.update', $partner_products->id)" method="POST">
        @method('PUT')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.partner_products.edit" :entity="$partner_products" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.partner_products.index.edit.title')
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
                            value="{{ old('name', $partner_products->name) }}"
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
                            value="{{ old('currency', $partner_products->currency) }}"
                            rules="required"
                            :label="trans('admin::app.settings.partner_products.index.create.currency')"
                        >
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency['code'] }}" @selected(old('currency', $partner_products->currency) === $currency['code'])>{{ $currency['label'] }}</option>
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
                            value="{{ old('sales_price', number_format($partner_products->sales_price, 2, ',', '')) }}"
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
                            :checked="old('active', $partner_products->active)"
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
                        value="{{ old('description', $partner_products->description) }}"
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
                        value="{{ old('discount_info', $partner_products->discount_info) }}"
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
                        value="{{ old('resource_type_id', $partner_products->resource_type_id) }}"
                        rules="required|numeric"
                        :label="trans('admin::app.settings.partner_products.index.create.resource_type')"
                    >
                        <option value="">@lang('admin::app.select')</option>
                        @foreach ($resourceTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('resource_type_id', $partner_products->resource_type_id) == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </x-admin::form.control-group.control>

                    <x-admin::form.control-group.error control-name="resource_type_id" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.clinics.index.title')
                    </x-admin::form.control-group.label>

                    @php
                        $selectedClinics = old('clinics', $partner_products->clinics->pluck('id')->toArray());
                    @endphp
                    <select
                        id="clinics-select"
                        name="clinics[]"
                        multiple
                        class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    >
                        @foreach ($clinics as $clinic)
                            <option value="{{ $clinic->id }}" @selected(in_array($clinic->id, $selectedClinics))>{{ $clinic->name }}</option>
                        @endforeach
                    </select>

                    <x-admin::form.control-group.error control-name="clinics" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.resources.index.title')
                    </x-admin::form.control-group.label>

                    @php
                        $selectedResources = old('resources', $partner_products->resources->pluck('id')->toArray());
                    @endphp
                    <select
                        id="resources-select"
                        name="resources[]"
                        multiple
                        class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 disabled:opacity-50 disabled:cursor-not-allowed"
                        data-initial-resources="{{ json_encode($selectedResources) }}"
                    >
                        @foreach ($resources as $resource)
                            <option value="{{ $resource->id }}" @selected(in_array($resource->id, $selectedResources))>{{ $resource->name }}</option>
                        @endforeach
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
                        value="{{ old('partner_name', $partner_products->partner_name) }}"
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
                        value="{{ old('clinic_description', $partner_products->clinic_description) }}"
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
                        value="{{ old('duration', $partner_products->duration) }}"

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
                    :value="$partner_products->relatedProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->toArray()"
                    :exclude-id="$partner_products->id"
                />
            </div>
        </div>
    </x-admin::form>

    @push('scripts')
        <script type="module">
            // Use event delegation to handle changes even if DOM is replaced by Vue
            document.addEventListener('change', function(e) {
                if (e.target.id === 'clinics-select' || e.target.matches('select[name="clinics[]"]')) {
                    loadResourcesForClinics();
                }
            }, true);

            // Also listen for clicks on the select (for multi-select)
            document.addEventListener('click', function(e) {
                if (e.target.id === 'clinics-select' || e.target.matches('select[name="clinics[]"]')) {
                    setTimeout(loadResourcesForClinics, 100);
                }
            }, true);

            function loadResourcesForClinics() {
                const clinicsSelect = document.getElementById('clinics-select');
                const resourcesSelect = document.getElementById('resources-select');
                const resourcesHint = document.getElementById('resources-hint');

                if (!clinicsSelect || !resourcesSelect || !resourcesHint) {
                    console.error('Could not find one or more select elements.');
                    return;
                }


                    const selectedClinics = Array.from(clinicsSelect.selectedOptions).map(opt => opt.value);

                    if (selectedClinics.length === 0) {
                        resourcesSelect.disabled = true;
                        resourcesSelect.innerHTML = '';
                        resourcesHint.style.display = 'none';
                        console.log('No clinics selected, resources select disabled.');
                        return;
                    }

                const currentlySelected = Array.from(resourcesSelect.selectedOptions).map(opt => opt.value);
                const params = new URLSearchParams();
                selectedClinics.forEach(id => params.append('clinic_ids[]', id));

                    fetch('{{ route("admin.settings.resources.filter_by_clinics") }}?' + params.toString())
                        .then(response => response.json())
                        .then(data => {
                            resourcesSelect.innerHTML = '';
                            resourcesHint.style.display = 'block';

                            if (data.data && data.data.length > 0) {
                                data.data.forEach(resource => {
                                    const option = document.createElement('option');
                                    option.value = resource.id;
                                    option.textContent = resource.name;
                                    if (currentlySelected.includes(resource.id.toString()) || currentlySelected.includes(resource.id)) {
                                        option.selected = true;
                                    }
                                    resourcesSelect.appendChild(option);
                                });
                                resourcesSelect.disabled = false;
                            } else {
                                const option = document.createElement('option');
                                option.disabled = true;
                                option.textContent = 'Geen resources beschikbaar voor deze kliniek(en)';
                                resourcesSelect.appendChild(option);
                                resourcesSelect.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error loading resources:', error);
                            resourcesSelect.disabled = false;
                        });
                }

            // Initial load
            document.addEventListener('DOMContentLoaded', function() {

                const clinicsSelect = document.getElementById('clinics-select');
                if (clinicsSelect && clinicsSelect.selectedOptions.length > 0) {
                    loadResourcesForClinics();
                }
            });
        </script>
    @endpush
</x-admin::layouts>

