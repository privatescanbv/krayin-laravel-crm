@props([
    'label' => '',
    'fields' => [],
    'type' => 'email', // email or phone
])

@php
    $fieldList = $fields ?? [];
    if (!is_array($fieldList)) {
        $fieldList = [];
    }

    // Filter out empty values
    $validFields = collect($fieldList)->filter(function($field) {
        if (is_array($field)) {
            return !empty($field['value']);
        }
        return !empty($field);
    });
@endphp

@if ($validFields->count() > 0)
    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
        <div class="label dark:text-white">
            {{ $label }}
        </div>
        <div class="font-medium dark:text-white">
            @foreach ($validFields as $field)
                @php
                    $value = is_array($field) ? ($field['value'] ?? '') : $field;
                    $fieldLabel = is_array($field) ? ($field['label'] ?? '') : '';
                    $isDefault = is_array($field) ? (!empty($field['is_default'])) : false;
                @endphp
                @if (!empty($value))
                    <div class="flex items-center gap-2">
                        @if ($type === 'email')
                            <a href="mailto:{{ $value }}" class="text-blue-600 hover:text-activity-task-text dark:text-blue-400">
                                {{ $value }}
                            </a>
                        @elseif ($type === 'phone')
                            <a href="tel:{{ $value }}" class="text-blue-600 hover:text-activity-task-text dark:text-blue-400">
                                {{ $value }}
                            </a>
                        @else
                            <span>{{ $value }}</span>
                        @endif

                        @if (!empty($fieldLabel))
                            <span class="text-xs text-gray-500 dark:text-gray-400">({{ $fieldLabel }})</span>
                        @endif

                        @if ($isDefault)
                            <span class="text-xs rounded bg-blue-100 px-1.5 py-0.5 text-activity-task-text dark:bg-blue-900 dark:text-blue-200">
                                standaard
                            </span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
