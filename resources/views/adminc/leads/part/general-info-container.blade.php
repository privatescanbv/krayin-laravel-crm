@props(['title', 'icon'])

<div class="flex flex-col gap-4">
    <div class="flex items-center gap-2 mb-2">
        <span class="{{ $icon }}"></span>
        <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">{{ $title }}</h4>
    </div>

    {{ $slot }}
</div>

