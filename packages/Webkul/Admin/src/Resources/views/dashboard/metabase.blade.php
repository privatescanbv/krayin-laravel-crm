<x-admin::layouts compact>
    <x-slot:title>
        {{ $title }}
    </x-slot>

    <div class="flex min-h-0 flex-1 flex-col px-4 pt-3">
        <div class="mb-3">
            <a
                href="{{ route('admin.dashboard.index') }}"
                class="mb-1 inline-flex items-center gap-1 text-sm text-brandColor hover:underline"
            >
                ← @lang('admin::app.dashboard.index.title')
            </a>
            <p class="text-2xl font-semibold dark:text-white">
                {{ $title }}
            </p>
        </div>

        <iframe
            src="{{ $embedUrl }}"
            class="min-h-0 w-full flex-1 border-0 bg-white dark:bg-gray-900"
            title="{{ $title }}"
            allow="fullscreen"
        ></iframe>
    </div>
</x-admin::layouts>
