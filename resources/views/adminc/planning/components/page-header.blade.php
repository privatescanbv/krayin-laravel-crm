@props([
    'title',
    'subtitle' => null,
    'actions' => []
])

<div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
    <div class="flex flex-col gap-1">
        <div class="text-xl font-bold">{{ $title }}</div>
        @if ($subtitle)
            <div class="text-sm text-gray-600 dark:text-gray-400">{{ $subtitle }}</div>
        @endif
    </div>
    @if (count($actions) > 0)
        <div class="flex items-center gap-2">
            @foreach ($actions as $action)
                <a href="{{ $action['url'] }}" class="secondary-button">{{ $action['label'] }}</a>
            @endforeach
        </div>
    @endif
</div>
