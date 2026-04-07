@php use App\Enums\ActivityType; @endphp
<div class="flex w-full flex-1 flex-col gap-4 rounded-lg">
    <div class="flex gap-2.5 max-lg:flex-wrap-reverse">
        <!-- Main content -->
        <div
            class="box-shadow flex-1 min-w-0 gap-2 rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900 max-lg:flex-auto">

            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Gekoppelde
                E-mails</h3>
            <div class="space-y-3">
                @forelse($activity->emails as $email)
                    <div
                        class="flex items-start gap-3 p-3 rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex-shrink-0">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-activity-note-text dark:bg-blue-900 dark:text-blue-300">
                                <span class="icon-mail text-sm"></span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                        <a href="{{ route('admin.mail.view', ['route' => 'inbox', 'id' => $email->id]) }}"
                                           target="_blank" class="hover:underline"
                                           title="E-mail bekijken">
                                            {{ $email->subject ?: 'Geen onderwerp' }}
                                            @if ((int)($email->is_read) === 0)
                                                <span class="inline-block h-1.5 w-1.5 rounded-full bg-sky-600 align-middle ml-1 dark:bg-white"></span>
                                            @endif
                                        </a>
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $email->created_at->format('d-m-Y H:i') }}
                                    </p>
                                </div>
                                <a href="{{ route('admin.mail.view', ['route' => 'inbox', 'id' => $email->id]) }}"
                                   target="_blank"
                                   class="flex-shrink-0 ml-2 flex h-6 w-6 items-center justify-center rounded-md text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                   title="E-mail bekijken">
                                    <span class="icon-right-arrow text-xs"></span>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Geen gekoppelde e-mails.</p>
                @endforelse
            </div>
        </div>

        <!-- Right side column: Call status manager (like edit) -->
        <div class="w-[360px] shrink-0 max-w-full gap-2 max-lg:w-full">
            @if ($activity->type === ActivityType::CALL)
                @include('admin::components.activities.call-status', ['activity' => $activity, 'callStatuses' => $callStatuses ?? []])
            @endif
        </div>
    </div>
</div>

