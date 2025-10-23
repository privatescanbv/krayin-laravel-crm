<x-adminc::components.entity-card
    :entity="$lead"
    entity-name="lead"
    :view-route="route('admin.leads.view', $lead->id)"
    view-button-text="Bekijk lead"
    :show-status-badge="true"
    :status-badge-text="$lead->stage->name ?? 'Geen status'"
    :show-actions="$show_actions ?? true"
/>
