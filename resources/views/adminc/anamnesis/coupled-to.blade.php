@props(['anamnesis'])

@php
    $couplings = collect([
        ['type' => 'Order', 'label' => $anamnesis->order?->title ?: $anamnesis->order?->name, 'url' => $anamnesis->order_id ? route('admin.orders.view', $anamnesis->order_id).'#anamnese' : null, 'id' => $anamnesis->order_id, 'primary' => $anamnesis->source_level === 'order'],
        ['type' => 'Sales', 'label' => $anamnesis->sales?->name, 'url' => $anamnesis->sales_id ? route('admin.sales-leads.view', $anamnesis->sales_id).'#anamnese' : null, 'id' => $anamnesis->sales_id, 'primary' => $anamnesis->source_level === 'sales'],
        ['type' => 'Lead', 'label' => $anamnesis->lead?->name, 'url' => $anamnesis->lead_id ? route('admin.leads.view', $anamnesis->lead_id).'#anamnese' : null, 'id' => $anamnesis->lead_id, 'primary' => $anamnesis->source_level === 'lead'],
    ])->filter(fn ($c) => $c['url']);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5 text-xs']) }}>
    <span class="font-semibold text-gray-500 dark:text-gray-400">Gekoppeld aan:</span>
    @forelse ($couplings as $c)
        <a href="{{ $c['url'] }}"
           class="inline-flex items-center gap-1 rounded border px-2 py-0.5 hover:underline {{ $c['primary'] ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200' : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
            <span class="font-semibold uppercase tracking-wide">{{ $c['type'] }}</span>
            <span>{{ $c['label'] ?: '#'.$c['id'] }}</span>
            @if ($c['primary'])<span class="text-green-600 dark:text-green-400">&bull;</span>@endif
        </a>
    @empty
        <span class="text-gray-400">niet gekoppeld</span>
    @endforelse
</div>
