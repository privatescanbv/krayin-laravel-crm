<x-admin::layouts>
    <x-slot:title>
        {{ $title }}
    </x-slot>

    <div class="mb-4">
        <p class="text-2xl font-semibold dark:text-white">
            {{ $title }}
        </p>
    </div>

    <iframe
        src="{{ $embedUrl }}"
        class="h-[calc(100vh-9.5rem)] w-full rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        title="{{ $title }}"
        allow="fullscreen"
    ></iframe>
</x-admin::layouts>
