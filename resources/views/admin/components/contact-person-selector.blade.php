@props([
    'name' => 'contact_person_id',
    'label' => 'Contactpersoon',
    'placeholder' => 'Selecteer contactpersoon...',
    'currentValue' => null,
    'currentLabel' => null,
    'canAddNew' => true,
])

<x-adminc::components.entity-selector
    :name="$name"
    :label="$label"
    :placeholder="$placeholder"
    :search-route="route('admin.contacts.persons.search')"
    :current-value="$currentValue"
    :current-label="$currentLabel"
    :can-add-new="$canAddNew"
/>
