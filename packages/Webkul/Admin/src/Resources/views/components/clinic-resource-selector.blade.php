@php use App\Models\Resource; @endphp
@props([
    'clinics' => [],
    'selectedClinics' => [],
    'selectedResources' => [],
])

<x-admin::form.control-group>
    <x-admin::form.control-group.label class="required">
        @lang('admin::app.settings.clinics.index.title')
    </x-admin::form.control-group.label>

    <select
        id="clinics-select"
        name="clinics[]"
        multiple
        class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
        data-initial-clinics="{{ json_encode($selectedClinics) }}"
    >
        @foreach ($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected(collect($selectedClinics)->contains($clinic->id))>
                {{ $clinic->name }}
            </option>
        @endforeach
    </select>

    <x-admin::form.control-group.error control-name="clinics" />
</x-admin::form.control-group>

<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.settings.resources.index.title')
    </x-admin::form.control-group.label>

    @php
        $preselectedResources = collect();
        $initialResourceOptions = collect();
        try {
            $selectedClinicIds = collect($selectedClinics)->filter()->all();
            $selectedResourceIds = collect($selectedResources)->filter()->all();

            if (!empty($selectedClinicIds)) {
                // Load all resources for the selected clinics for initial render
                $initialResourceOptions = Resource::whereIn('clinic_id', $selectedClinicIds)
                    ->orderBy('name')
                    ->get(['id','name']);
            } else {
                // If no clinics pre-selected, render all resources initially so the user can pick
                $initialResourceOptions = Resource::orderBy('name')->get(['id','name']);
            }

            if (!empty($selectedResourceIds)) {
                // Ensure selected ones are present and can be pre-selected
                $preselectedResources = Resource::whereIn('id', $selectedResourceIds)
                    ->orderBy('name')
                    ->get(['id','name']);
            }
        } catch (\Throwable $e) {
            $preselectedResources = collect();
            $initialResourceOptions = collect();
        }
    @endphp

    <select
        id="resources-select"
        name="resources[]"
        multiple
        
        class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 disabled:opacity-50 disabled:cursor-not-allowed"
        data-initial-resources="{{ json_encode($selectedResources) }}"
    >
        @if ($initialResourceOptions->isNotEmpty())
            @foreach ($initialResourceOptions as $res)
                <option value="{{ $res->id }}" @selected(collect($selectedResources)->contains($res->id))>{{ $res->name }}</option>
            @endforeach
        @else
            @foreach ($preselectedResources as $res)
                <option value="{{ $res->id }}" selected>{{ $res->name }}</option>
            @endforeach
        @endif
    </select>

    <p id="resources-hint" class="mt-1 text-xs text-gray-600 dark:text-gray-400" style="display: none;">
        Gefilterd op gekozen kliniek(en)
    </p>

    <x-admin::form.control-group.error control-name="resources" />
</x-admin::form.control-group>

@pushOnce('scripts')
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
            const initialSelectedAttr = resourcesSelect ? resourcesSelect.getAttribute('data-initial-resources') : '[]';
            let initialSelected = [];
            try {
                initialSelected = initialSelectedAttr ? JSON.parse(initialSelectedAttr) : [];
            } catch (e) {
                initialSelected = [];
            }

            if (!clinicsSelect || !resourcesSelect || !resourcesHint) {
                return;
            }

            const selectedClinics = Array.from(clinicsSelect.selectedOptions).map(opt => opt.value);
            
            if (selectedClinics.length === 0) {
                resourcesSelect.disabled = true;
                resourcesSelect.innerHTML = '';
                resourcesHint.style.display = 'none';
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
                            const isSelected =
                                currentlySelected.includes(resource.id.toString()) ||
                                currentlySelected.includes(resource.id) ||
                                initialSelected.includes(resource.id) ||
                                initialSelected.includes(resource.id.toString());
                            if (isSelected) {
                                option.selected = true;
                            }
                            resourcesSelect.appendChild(option);
                        });
                        resourcesSelect.disabled = false;
                        // Clear initial selected after first application to avoid re-applying on subsequent changes
                        resourcesSelect.setAttribute('data-initial-resources', '[]');
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
            if (!clinicsSelect) return;

            // Ensure initially-linked clinics are selected in the UI
            const initialClinicsAttr = clinicsSelect.getAttribute('data-initial-clinics') || '[]';
            let initialClinics = [];
            try { initialClinics = JSON.parse(initialClinicsAttr) || []; } catch (e) { initialClinics = []; }

            if (initialClinics.length > 0) {
                const values = initialClinics.map(v => v.toString());
                Array.from(clinicsSelect.options).forEach(opt => {
                    if (values.includes(opt.value)) {
                        opt.selected = true;
                    }
                });
            }

            // Always attempt initial load so resources populate
            loadResourcesForClinics();
        });
    </script>
@endPushOnce
