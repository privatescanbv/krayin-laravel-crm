@php
    $checksUrl = route('admin.orders.edit', $order->id) . '#checks';
    $allDone   = $totalChecks > 0 && $completedChecks === $totalChecks;
    $hasChecks = $totalChecks > 0;
    $pct       = $hasChecks ? round($completedChecks / $totalChecks * 100) : 0;
@endphp

{{-- Checks widget --}}
<div class="border-b border-gray-200 p-4 dark:border-gray-800">
    <div class="flex items-center justify-between gap-2 mb-3">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Checks</h4>
        @if ($hasChecks)
            <span class="text-xs font-medium px-2 py-0.5 rounded-full
                {{ $allDone ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $completedChecks }}/{{ $totalChecks }}
            </span>
        @endif
    </div>

    @if ($hasChecks)
        {{-- Progress bar --}}
        <div class="mb-3 h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
            <div class="h-2 rounded-full transition-all duration-300
                {{ $allDone ? 'bg-green-500' : 'bg-brandColor' }}"
                 style="width: {{ $pct }}%"></div>
        </div>

        {{-- Individual checks (read-only summary) --}}
        <ul class="mb-3 space-y-1.5 max-h-48 overflow-y-auto">
            @foreach ($order->orderChecks as $check)
                <li class="flex items-center gap-2 text-xs {{ $check->done ? 'text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-200' }}">
                    <span class="{{ $check->done ? 'icon-check text-green-500' : 'icon-radio-normal text-gray-400' }} text-sm shrink-0"></span>
                    <span class="{{ $check->done ? 'line-through' : '' }} truncate">{{ $check->name }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">Geen checks toegevoegd.</p>
    @endif

    @if (bouncer()->hasPermission('orders.edit'))
        <a href="{{ $checksUrl }}"
           class="inline-flex items-center gap-1 text-xs font-medium text-brandColor hover:underline">
            <span class="icon-edit text-sm"></span>
            {{ $hasChecks ? 'Checks beheren' : 'Checks toevoegen' }}
        </a>
    @endif
</div>
