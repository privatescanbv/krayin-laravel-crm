@php use Illuminate\Support\Carbon; @endphp
@props([
    'lead',
])
@php
    $entity = $lead;
    $entityName = 'lead';
    $entityViewURL = route('admin.leads.view', $lead->id);
    $person = $lead->getContactPersonOrFirstPerson();
    $age = null;
    if(!is_null($person)) {
        $entity = $person;
        $entityName = 'person';
        $entityViewURL = route('admin.contacts.persons.view', $entity->id);
    }
@endphp
<x-adminc::components.entity-card
        :entity="$entity"
        :entity-name="$entityName"
        :view-route="$entityViewURL"
        view-button-text="Bekijk lead"
        :show-status-badge="true"
        :status-badge-text="$lead->stage->name ?? 'Geen status'"
        :show-actions="$show_actions ?? true"
/>
