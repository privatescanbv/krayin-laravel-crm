@php use Carbon\Carbon; @endphp
@props([
    'orderItems' => []
])

<!-- Order Items Panel -->
<div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4">
    <h3 class="text-lg font-semibold mb-4">Orderitems</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($orderItems as $item)
            @php
                $statusValue = is_string($item->status) ? $item->status : ($item->status?->value ?? 'new');
                $statusLabel = is_object($item->status) && method_exists($item->status, 'label')
                    ? $item->status->label()
                    : ucfirst(str_replace('_', ' ', $statusValue));
                $canPlan = $item->isPlannable();
            @endphp
            <div
                class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 {{ $canPlan ? 'bg-activity-note-bg dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-gray-800' }}">
                <div class="flex justify-between items-start mb-2">
                    <h4 class="font-medium text-sm">{{ $item->product->fullName ?? 'Onbekend product' }}</h4>
                    <span
                        class="text-xs px-2 py-1 rounded-full {{ $statusValue === 'planned' ? 'bg-green-100 text-green-800' : 'bg-neutral-bg text-gray-800' }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                    {{ $item->person?->name ?? 'Geen persoon toegewezen' }} &mdash; Aantal: {{ $item->quantity }}</div>
                @if ($item->resourceOrderItems && $item->resourceOrderItems->count() > 0)
                    <div class="text-xs text-gray-700 dark:text-gray-300">
                        <div class="font-medium mb-1">Ingepland:</div>
                        @foreach ($item->resourceOrderItems as $booking)
                            <div class="mb-1">
                                <strong>{{ $booking->resource?->name ?? 'Onbekend' }}</strong><br>
                                {{ Carbon::parse($booking->from)->format('d-m-Y H:i') }}
                                - {{ Carbon::parse($booking->to)->format('H:i') }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($canPlan) Niet ingepland @else Niet planbaar @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
