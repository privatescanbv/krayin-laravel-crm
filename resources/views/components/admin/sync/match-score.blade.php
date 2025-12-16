@props(['score', 'title' => 'Match Score'])

<div class="box-shadow rounded bg-white dark:bg-gray-900 mb-4">
    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
        <h3 class="text-lg font-semibold dark:text-white">{{ $title }}</h3>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
            {{ $score['matching_fields'] }} van {{ $score['total_fields'] }} velden komen overeen
        </p>
    </div>
    <div class="p-4">
        <div class="flex items-center gap-4">
            <div class="w-32 h-4 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-300 {{ $score['percentage'] >= 80 ? 'bg-succes' : ($score['percentage'] >= 50 ? 'bg-status-on_hold-text' : 'bg-red-500') }}"
                     style="width: {{ $score['percentage'] }}%"></div>
            </div>
            <span class="text-lg font-medium {{ $score['percentage'] >= 80 ? 'text-status-active-text' : ($score['percentage'] >= 50 ? 'text-yellow-600' : 'text-status-expired-text') }}">
                {{ $score['percentage'] }}%
            </span>
        </div>
    </div>
</div>

