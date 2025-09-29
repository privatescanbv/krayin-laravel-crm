@php
    $dayNames = [
        1 => trans('admin::app.monday'),
        2 => trans('admin::app.tuesday'),
        3 => trans('admin::app.wednesday'),
        4 => trans('admin::app.thursday'),
        5 => trans('admin::app.friday'),
        6 => trans('admin::app.saturday'),
        7 => trans('admin::app.sunday'),
    ];
@endphp

<div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-800">
    @for ($day = 1; $day <= 7; $day++)
        @php
            $daySummary = $summary[$day] ?? ['available' => []];
            $available = $daySummary['available'] ?? [];
        @endphp
        <div class="py-3 text-sm">
            <div class="mb-2 font-semibold">{{ $dayNames[$day] }}</div>
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300">Beschikbaar</span>
                @if (count($available))
                    @foreach ($available as $range)
                        <span class="rounded border border-green-300 px-2 py-0.5 text-xs text-green-800 dark:border-green-700 dark:text-green-300">{{ $range['from'] }}–{{ $range['to'] }}</span>
                    @endforeach
                @else
                    <span class="text-xs text-gray-500">—</span>
                @endif
            </div>
            
        </div>
    @endfor
