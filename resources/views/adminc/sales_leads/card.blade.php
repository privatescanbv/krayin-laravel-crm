@php use Illuminate\Support\Carbon; @endphp
@props([
    'sales',
])
@php
    $entity = $sales;
    $entityName = 'sales';
    $entityViewURL = route('admin.sales-leads.view', $entity->id);
    $person = $entity->getContactPersonOrFirstPerson();
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
        :show-actions="$show_actions ?? true"
        :stage="$sales->stage"
        :lost-reason="$sales->lost_reason"
/>
