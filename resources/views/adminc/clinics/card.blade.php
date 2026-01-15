@php use Illuminate\Support\Carbon; @endphp
@props([
    'clinic',
])
@php
    $entity = $clinic;
    $entityName = 'clinic';
    $entityViewURL = route('admin.clinics.view', $clinic->id);
    $entity = $clinic;
@endphp
<x-adminc::components.entity-card
    :entity="$entity"
    :entity-name="$entityName"
    :view-route="$entityViewURL"
    view-button-text="Bekijk clinic"
    :show-status-badge="false"
    :status-badge-text="'Geen status'"
    :show-actions="$show_actions ?? true"
/>
