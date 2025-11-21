<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.folders.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
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

        <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            @if ($folders->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    @lang('admin::app.settings.folders.index.name')
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    @lang('admin::app.settings.folders.index.parent')
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    @lang('admin::app.settings.folders.index.emails')
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    @lang('admin::app.settings.folders.index.actions')
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($folders as $folder)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="icon-folder text-2xl text-gray-600 dark:text-gray-300 mr-3"></i>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $folder->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                        @if ($folder->parent)
                                            <span class="flex items-center">
                                                <i class="icon-folder text-sm text-gray-400 mr-2"></i>
                                                {{ $folder->parent->name }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                        {{ $folder->emails->count() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            @if (bouncer()->hasPermission('settings.folders.edit'))
                                                <a
                                                    href="{{ route('admin.settings.folders.edit', $folder->id) }}"
                                                    class="text-activity-note-text hover:text-activity-task-text dark:text-blue-400 dark:hover:text-blue-300"
                                                    title="@lang('admin::app.settings.folders.index.edit')"
                                                >
                                                    <i class="icon-edit text-xl"></i>
                                                </a>
                                            @endif

                                            @if (bouncer()->hasPermission('settings.folders.delete') && $folder->is_deletable)
                                                <button
                                                    type="button"
                                                    class="text-status-expired-text hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                    title="@lang('admin::app.settings.folders.index.delete')"
                                                    @click="confirmDelete('{{ route('admin.settings.folders.delete', $folder->id) }}')"
                                                >
                                                    <i class="icon-delete text-xl"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
