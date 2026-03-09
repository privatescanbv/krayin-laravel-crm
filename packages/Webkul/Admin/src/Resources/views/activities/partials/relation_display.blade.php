@props(['activity'])

@php
    $relations = [];

    // Person (via person_id FK)
    if ($activity->person) {
        $person = $activity->person;
        $relations[] = [
            'type'  => 'person',
            'label' => 'Persoon',
            'icon'  => 'icon-user',
            'route' => route('admin.contacts.persons.view', $person->id),
            'name'  => $person->name ?: '#' . $person->id,
            'class' => 'bg-slate-100 text-slate-800 border-slate-200
                        dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700',
        ];
    }

    // Lead
    if ($activity->lead) {
        $relations[] = [
            'type'  => 'lead',
            'label' => 'Lead',
            'icon'  => 'icon-lead',
            'route' => route('admin.leads.view', $activity->lead->id),
            'name'  => $activity->lead->name ?: '#' . $activity->lead->id,
            'class' => 'bg-blue-100 text-blue-800 border-blue-200
                        dark:bg-blue-900 dark:text-blue-200 dark:border-blue-800',
        ];
    }

    // Sales lead
    if ($activity->salesLead) {
        $relations[] = [
            'type'  => 'sales',
            'label' => 'Sales',
            'icon'  => 'icon-sales-lead',
            'route' => route('admin.sales-leads.view', $activity->salesLead->id),
            'name'  => $activity->salesLead->name ?: '#' . $activity->salesLead->id,
            'class' => 'bg-green-100 text-green-800 border-green-200
                        dark:bg-green-900 dark:text-green-200 dark:border-green-800',
        ];
    }

    // Order
    if ($activity->order) {
        $relations[] = [
            'type'  => 'order',
            'label' => 'Order',
            'icon'  => 'icon-order',
            'route' => route('admin.orders.view', $activity->order->id),
            'name'  => $activity->order->title ?: '#' . $activity->order->id,
            'class' => 'bg-teal-100 text-teal-800 border-teal-200
                        dark:bg-teal-900 dark:text-teal-200 dark:border-teal-800',
        ];
    }

    // Clinic
    if ($activity->clinic) {
        $relations[] = [
            'type'  => 'clinic',
            'label' => 'Kliniek',
            'icon'  => 'icon-clinic',
            'route' => route('admin.clinics.view', $activity->clinic->id),
            'name'  => $activity->clinic->name ?: '#' . $activity->clinic->id,
            'class' => 'bg-purple-100 text-purple-800 border-purple-200
                        dark:bg-purple-900 dark:text-purple-200 dark:border-purple-800',
        ];
    }
@endphp

<div class="flex flex-wrap gap-2 text-xs">
    @forelse ($relations as $rel)
        <a href="{{ $rel['route'] }}"
           class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 font-medium transition-colors hover:opacity-80 {{ $rel['class'] }}">
            <span class="{{ $rel['icon'] }} text-sm"></span>
            <span class="text-[10px] uppercase tracking-wide opacity-70">{{ $rel['label'] }}</span>
            <span>{{ $rel['name'] }}</span>
        </a>
    @empty
        <span class="text-gray-400 italic dark:text-gray-500">Geen relaties</span>
    @endforelse
</div>
