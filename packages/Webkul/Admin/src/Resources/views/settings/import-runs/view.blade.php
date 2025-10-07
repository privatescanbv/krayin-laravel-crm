<x-admin::layouts>
    <x-slot:title>
        Import Run #{{ $importRun->id }}
    </x-slot:title>

    <div class="flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        <div class="max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <div class="flex justify-start max-lg:hidden">
                        <div class="flex items-center gap-x-3.5">
                            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-300">
                                Settings
                            </a>
                            <span class="text-gray-400">/</span>
                            <a href="{{ route('admin.settings.import-runs.index') }}" class="text-gray-600 dark:text-gray-300">
                                Import Runs
                            </a>
                            <span class="text-gray-400">/</span>
                            <span class="text-gray-800 dark:text-white">#{{ $importRun->id }}</span>
                        </div>
                    </div>
                </div>

                <div class="mb-2 flex flex-col gap-0.5">
                    <h3 class="break-words text-lg font-bold dark:text-white">
                        Import Run #{{ $importRun->id }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $importRun->import_type }}</p>
                </div>
            </div>

            <!-- Details -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                    <span class="text-sm font-semibold 
                        @if($importRun->status === 'completed') text-green-600 dark:text-green-400
                        @elseif($importRun->status === 'failed') text-red-600 dark:text-red-400
                        @else text-yellow-600 dark:text-yellow-400
                        @endif">
                        {{ ucfirst($importRun->status) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Started At:</span>
                    <span class="text-sm text-gray-800 dark:text-white">{{ $importRun->started_at?->format('d-m-Y H:i:s') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Completed At:</span>
                    <span class="text-sm text-gray-800 dark:text-white">{{ $importRun->completed_at?->format('d-m-Y H:i:s') }}</span>
                </div>
            </div>

            <!-- Statistics -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-white">Statistics</h4>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Processed:</span>
                    <span class="text-sm text-gray-800 dark:text-white">{{ $importRun->records_processed }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Imported:</span>
                    <span class="text-sm text-green-600 dark:text-green-400">{{ $importRun->records_imported }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Skipped:</span>
                    <span class="text-sm text-yellow-600 dark:text-yellow-400">{{ $importRun->records_skipped }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Errors:</span>
                    <span class="text-sm text-red-600 dark:text-red-400">{{ $importRun->records_errored }}</span>
                </div>
            </div>

            <!-- Audit Trail Footer -->
            <div class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
                @if ($importRun->creator)
                    <div class="flex justify-between">
                        <span>Created By:</span>
                        <span>{{ $importRun->creator->name }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span>Created At:</span>
                    <span>{{ $importRun->created_at->format('d-m-Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Right Panel - Import Logs -->
        <div class="flex w-full flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold dark:text-white">Import Logs</h3>
            
            @if($importRun->importLogs->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-400">No logs found for this import run.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($importRun->importLogs as $log)
                        <div class="border border-gray-200 rounded-lg p-3 dark:border-gray-700
                            @if($log->level === 'error') bg-red-50 dark:bg-red-900/20
                            @elseif($log->level === 'warning') bg-yellow-50 dark:bg-yellow-900/20
                            @else bg-blue-50 dark:bg-blue-900/20
                            @endif">
                            <div class="flex items-start justify-between mb-2">
                                <span class="text-xs font-semibold px-2 py-1 rounded
                                    @if($log->level === 'error') bg-red-600 text-white
                                    @elseif($log->level === 'warning') bg-yellow-600 text-white
                                    @else bg-blue-600 text-white
                                    @endif">
                                    {{ strtoupper($log->level) }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $log->created_at->format('d-m-Y H:i:s') }}</span>
                            </div>
                            <p class="text-sm text-gray-800 dark:text-white mb-2">{{ $log->message }}</p>
                            @if($log->record_id)
                                <p class="text-xs text-gray-600 dark:text-gray-400">Record ID: {{ $log->record_id }}</p>
                            @endif
                            @if($log->context)
                                <details class="mt-2">
                                    <summary class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer">View Context</summary>
                                    <pre class="text-xs mt-2 p-2 bg-gray-100 dark:bg-gray-800 rounded overflow-x-auto">{{ json_encode($log->context, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-admin::layouts>