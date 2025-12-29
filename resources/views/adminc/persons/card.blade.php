<x-adminc::components.entity-card
    :entity="$person"
    entity-name="person"
    :view-route="route('admin.contacts.persons.view', $person->id)"
    view-button-text="Bekijk persoon"
    :show-status-badge="false"
    :show-actions="$show_actions ?? true"
/>
