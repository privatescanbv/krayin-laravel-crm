@props([
    'src' => '',
    'name' => '',
    'label' => 'Related Products',
    'searchPlaceholder' => 'Search products...',
    'value' => [],
    'excludeId' => null,
])

@php
    // Convert value array to current value and label for entity selector
    $currentValue = null;
    $currentLabel = null;
    
    if (!empty($value) && is_array($value)) {
        if (count($value) === 1) {
            // Single selection
            $item = $value[0];
            $currentValue = $item['id'] ?? null;
            $currentLabel = $item['name'] ?? null;
        } else {
            // Multiple selection - for now, show first item
            $item = $value[0] ?? null;
            $currentValue = $item['id'] ?? null;
            $currentLabel = $item['name'] ?? null;
        }
    }
@endphp

<x-adminc::components.entity-selector
    :name="$name"
    :label="$label"
    :placeholder="$searchPlaceholder"
    :search-route="$src"
    :current-value="$currentValue"
    :current-label="$currentLabel"
    :exclude-id="$excludeId"
    :multiple="true"
/>