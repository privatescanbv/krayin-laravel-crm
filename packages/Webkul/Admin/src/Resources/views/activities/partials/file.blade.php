@php
    /** @var \Webkul\Activity\Models\Activity $activity */
    $files = $activity->files ?? collect();
@endphp

<div class="flex w-full flex-1 flex-col gap-4 rounded-lg">
    <div class="box-shadow flex-1 min-w-0 rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Bestand</h3>

        @if ($files->isEmpty())
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Geen bestand gevonden bij deze activiteit.
            </div>
        @else
            <div class="space-y-3">
                @foreach ($files as $file)
                    <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="icon-file text-xl text-activity-file-text"></span>

                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"
                                         title="{{ $file->name ?: basename($file->path) }}">
                                        {{ $file->name ?: basename($file->path) }}
                                    </div>

                                    @if ($file->created_at)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Toegevoegd op {{ $file->created_at->format('d-m-Y H:i') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <a
                            class="secondary-button whitespace-nowrap"
                            href="{{ route('admin.activities.file_download', $file->id) }}"
                        >
                            Download
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

