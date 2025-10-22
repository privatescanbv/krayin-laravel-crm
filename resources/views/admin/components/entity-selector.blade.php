@props([
    'name' => 'entity_id',
    'label' => 'Entiteit',
    'placeholder' => 'Selecteer entiteit...',
    'searchRoute' => null,
    'currentValue' => null,
    'currentLabel' => null,
    'canAddNew' => true
])

<div class="w-full">
    @if ($currentValue && $currentLabel)
        <div class="mb-2 p-2 bg-blue-50 border border-blue-200 rounded dark:bg-blue-900 dark:border-blue-700">
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <strong>{{ $currentLabel }}</strong>
            </p>
        </div>
    @endif

    <!-- Hidden input to preserve current value -->
    <input type="hidden" name="{{ $name }}" value="{{ old($name, $currentValue) }}">

    <!-- Use the admin lookup component but with minimal JavaScript -->
    <x-admin::lookup
        src="{{ $searchRoute }}"
        name="{{ $name }}_display"
        :label="$label"
        :placeholder="$placeholder"
        :can-add-new="$canAddNew"
    />
</div>
