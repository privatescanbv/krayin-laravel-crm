<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.folders.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <!-- breadcrumbs -->
                <x-admin::breadcrumbs
                    name="settings.folders"
                />

                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.settings.folders.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('settings.folders.create'))
                    <a
                        href="{{ route('admin.settings.folders.create') }}"
                        class="primary-button"
                    >
                        @lang('admin::app.settings.folders.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="p-4">
                @if ($folders->count() > 0)
                    <div class="space-y-2">
                        @foreach ($folders as $folder)
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-2">
                                        <i class="icon-folder text-2xl text-gray-600 dark:text-gray-300"></i>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $folder->name }}</span>
                                    </div>
                                    @if ($folder->children->count() > 0)
                                        <div class="ml-4 space-y-1">
                                            @foreach ($folder->children as $child)
                                                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                                                    <div class="flex items-center gap-2">
                                                        <i class="icon-folder text-lg"></i>
                                                        <span>{{ $child->name }}</span>
                                                    </div>
                                                    @if (!$child->is_deletable)
                                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded dark:bg-gray-700 dark:text-gray-300">
                                                            @lang('admin::app.settings.folders.index.not-deletable')
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $folder->emails->count() }} emails
                                    </span>
                                    
                                    @if (!$folder->is_deletable)
                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded dark:bg-gray-700 dark:text-gray-300">
                                            @lang('admin::app.settings.folders.index.not-deletable')
                                        </span>
                                    @endif

                                    <div class="flex items-center gap-1">
                                        @if (bouncer()->hasPermission('settings.folders.edit'))
                                            <a
                                                href="{{ route('admin.settings.folders.edit', $folder->id) }}"
                                                class="icon-edit text-2xl text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400"
                                                title="@lang('admin::app.settings.folders.index.edit')"
                                            ></a>
                                        @endif

                                        @if (bouncer()->hasPermission('settings.folders.delete') && $folder->is_deletable)
                                            <button
                                                type="button"
                                                class="icon-delete text-2xl text-gray-600 hover:text-red-600 dark:text-gray-300 dark:hover:text-red-400"
                                                title="@lang('admin::app.settings.folders.index.delete')"
                                                @click="confirmDelete('{{ route('admin.settings.folders.delete', $folder->id) }}')"
                                            ></button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12">
                        <i class="icon-folder text-6xl text-gray-300 dark:text-gray-600"></i>
                        <p class="mt-4 text-lg font-medium text-gray-500 dark:text-gray-400">
                            @lang('admin::app.settings.folders.index.empty-state.title')
                        </p>
                        <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
                            @lang('admin::app.settings.folders.index.empty-state.description')
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @pushOnce('scripts')
        <script>
            function confirmDelete(url) {
                if (confirm('@lang('admin::app.settings.folders.index.delete-confirmation')')) {
                    fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.message) {
                            window.location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }
            }
        </script>
    @endPushOnce
</x-admin::layouts>