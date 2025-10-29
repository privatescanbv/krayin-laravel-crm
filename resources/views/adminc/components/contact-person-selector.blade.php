@props([
    'name' => 'contact_person_id',
    'label' => 'Contactpersoon',
    'placeholder' => 'Selecteer contactpersoon...',
    'currentValue' => null,
    'currentLabel' => null,
    'canAddNew' => true,
])

<v-entity-selector
    :name="$name"
    :label="$label"
    :placeholder="$placeholder"
    :search-route="route('admin.contacts.persons.search')"
    :items="$currentValue ? [['id' => $currentValue, 'name' => $currentLabel ?: '']] : []"
    :can-add-new="$canAddNew"
    :multiple="false"
/>
