<x-admin::layouts>
    <x-slot:title>
        {{ $title }}
    </x-slot>

    <div class="mb-4">
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

    <div class="h-[calc(100vh-9.5rem)] w-full overflow-auto rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-800 dark:bg-gray-900">
        <metabase-dashboard
            token="{{ $token }}"
            with-title="true"
            with-downloads="true"
            @if ($initialParams !== [])
                initial-parameters='@json($initialParams)'
            @endif
        ></metabase-dashboard>
    </div>

    @pushOnce('scripts')
        <script>
            function defineMetabaseConfig(config) {
                window.metabaseConfig = config;
            }

            defineMetabaseConfig({
                theme: { preset: 'light' },
                isGuest: true,
                instanceUrl: @json($instanceUrl),
            });
        </script>
        <script
            defer
            src="{{ $instanceUrl }}/app/embed.js"
        ></script>
    @endPushOnce
</x-admin::layouts>
