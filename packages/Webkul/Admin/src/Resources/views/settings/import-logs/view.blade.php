<x-admin::layouts>
    <x-slot:title>
        Import Log #{{ $importLog->id }}
    </x-slot:title>

    <div class="flex gap-4 max-lg:flex-wrap">
        <div class="max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <div class="flex justify-start max-lg:hidden">
                        <div class="flex items-center gap-x-3.5">
                            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-300">
                                Settings
                            </a>
                            <span class="text-gray-400">/</span>
                            <a href="{{ route('admin.settings.import-logs.index') }}" class="text-gray-600 dark:text-gray-300">
                                Import Logs
                            </a>
                            <span class="text-gray-400">/</span>
                            <span class="text-gray-800 dark:text-white">#{{ $importLog->id }}</span>
                        </div>
                    </div>
                </div>

                <div class="mb-2 flex flex-col gap-0.5">
                    <h3 class="break-words text-lg font-bold dark:text-white">
                        Import Log #{{ $importLog->id }}
                    </h3>
                </div>
            </div>

            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Level:</span>
                    <span class="text-sm font-semibold
                        @if($importLog->level === 'error') text-status-expired-text dark:text-red-400
                        @elseif($importLog->level === 'warning') text-yellow-600 dark:text-yellow-400
                        @else text-activity-note-text dark:text-blue-400
                        @endif">
                        {{ strtoupper($importLog->level) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Import Run:</span>
                    <a href="{{ route('admin.settings.import-runs.view', $importLog->import_run_id) }}"
                       class="text-sm text-activity-note-text hover:underline dark:text-blue-400">
                        #{{ $importLog->import_run_id }}
                    </a>
                </div>
                @if($importLog->record_id)
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Record ID:</span>
                        <span class="text-sm text-gray-800 dark:text-white">{{ $importLog->record_id }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Created At:</span>
                    <span class="text-sm text-gray-800 dark:text-white">{{ $importLog->created_at->format('d-m-Y H:i:s') }}</span>
                </div>
            </div>

            <div class="flex w-full flex-col gap-2 p-4">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-white">Message</h4>
                <p class="text-sm text-gray-800 dark:text-white">{{ $importLog->message }}</p>
            </div>

            @if($importLog->context)
                <div class="flex w-full flex-col gap-2 border-t border-gray-200 p-4 dark:border-gray-800">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white">Context</h4>
                    <pre class="text-xs p-2 bg-neutral-bg dark:bg-gray-800 rounded overflow-x-auto">{{ json_encode($importLog->context, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </div>
    </div>
</x-admin::layouts>
