@php use Illuminate\Support\Carbon; @endphp
@props([
    'order',
])
@php
    $entity = $order;
    $entityName = 'order';
    $entityViewURL = route('admin.orders.view', $entity->id);
    $person = $order->salesLead->getContactPersonOrFirstPerson();
    $age = null;
    if(!is_null($person)) {
        $entity = $person;
        $entityViewURL = route('admin.contacts.persons.view', $entity->id);
    }
@endphp
<x-adminc::components.entity-card
        :entity="$entity"
        :entity-name="$entityName"
        :view-route="$entityViewURL"
        view-button-text="Bekijk order"
        :show-status-badge="true"
        :show-actions="$show_actions ?? true"
        :stage="$order->stage"
        :lost-reason="$order->lost_reason"
        :closed-at="$order->closed_at ?? null"
/>
