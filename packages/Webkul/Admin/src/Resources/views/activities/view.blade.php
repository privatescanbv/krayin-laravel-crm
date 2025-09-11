<x-admin::layouts>
    <x-slot:title>
        {{ $activity->title ?: __('admin::app.activities.view.title') }}
    </x-slot>

    <div class="relative flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel (sticky, like lead view) -->
        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="activities.view"
                        :entity="$activity"
                    />
                </div>

                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-bold dark:text-white">
                        {{ $activity->title ?: __('admin::app.activities.edit.title') }}
                    </h3>
                    @if (bouncer()->hasPermission('activities.delete'))
                        <form method="POST" action="{{ route('admin.activities.delete', $activity->id) }}" onsubmit="return confirm('Weet je zeker dat je deze activiteit wilt verwijderen?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex items-center gap-1 text-red-600 hover:text-red-700 text-sm">
                                <span class="icon-delete"></span>
                                <span>Verwijderen</span>
                            </button>
                        </form>
                    @endif
                </div>

                <!-- Status bar (reusable) -->
                <div class="mt-2">
                    @include('admin::components.activities.status-bar', ['activity' => $activity, 'hide_help' => true])
                </div>

                <!-- Actions (same as lead, except file add) executed on related lead via popup -->
                <div id="activity-view-actions" class="mt-2 flex flex-wrap gap-2">
                    @if(!$activity->is_done)
                        <button
                            type="submit"
                            form="activity-complete-form"
                            class="secondary-button"
                        >
                            Afronden
                        </button>
                    @endif
                    @if ($activity->lead && bouncer()->hasPermission('mail.compose'))
                        <x-admin::activities.actions.mail :entity="$activity->lead" entity-control-name="lead_id" />
                    @endif

                    @if ($activity->lead && bouncer()->hasPermission('activities.create'))
                        <x-admin::activities.actions.note :entity="$activity->lead" entity-control-name="lead_id" />
                        <x-admin::activities.actions.activity :entity="$activity->lead" entity-control-name="lead_id" />
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Acties worden uitgevoerd op de gekoppelde lead.
                </div>
            </div>

            <!-- Compact details -->
            <div class="p-4 text-sm text-gray-700 dark:text-gray-300">
                <div class="mb-2">
                    <span class="font-medium">@lang('admin::app.activities.edit.type'):</span>
                    <span>{{ __("admin::app.activities.edit." . ($activity->type?->value ?? $activity->type)) }}</span>
                </div>
                <div class="mb-2">
                    <span class="font-medium">@lang('admin::app.activities.edit.schedule_from'):</span>
                    <span>{{ $activity->schedule_from }}</span>
                </div>
                <div class="mb-2">
                    <span class="font-medium">@lang('admin::app.activities.edit.schedule_to'):</span>
                    <span>{{ $activity->schedule_to }}</span>
                </div>
                @if($activity->lead)
                    <div class="mb-2">
                        <span class="font-medium">Lead:</span>
                        <a href="{{ route('admin.leads.view', $activity->lead->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                            {{ $activity->lead->name }}
                        </a>
                    </div>

                    @if($activity->lead->phones && is_array($activity->lead->phones) && count($activity->lead->phones) > 0)
                        @php
                            $defaultPhone = collect($activity->lead->phones)->firstWhere('is_default', true)
                                             ?? collect($activity->lead->phones)->first();
                            $otherPhones = collect($activity->lead->phones)->reject(function($phone) use ($defaultPhone) {
                                return $defaultPhone && isset($defaultPhone['value']) && ($phone['value'] ?? null) === ($defaultPhone['value'] ?? null);
                            });
                        @endphp
                        <div class="mt-2 space-y-1">
                            <div class="text-sm">
                                <span class="font-medium">Telefoons:</span>
                            </div>
                            @if($defaultPhone)
                                <div class="text-sm">
                                    <span class="font-semibold">{{ $defaultPhone['value'] ?? '' }}</span>
                                    <span class="ml-2 text-xs text-gray-500">(default)</span>
                                </div>
                            @endif
                            @foreach($otherPhones as $phone)
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $phone['value'] ?? '' }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>

            <!-- Footer with creation and modification dates -->
            <div class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
                <div class="flex justify-between">
                    <span>@lang('admin::app.common.created-at'):</span>
                    <span>{{ $activity->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>@lang('admin::app.common.updated-at'):</span>
                    <span>{{ $activity->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <div class="flex gap-2.5 max-lg:flex-wrap-reverse">
                <!-- Main content -->
                <div class="box-shadow flex-1 gap-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 max-lg:flex-auto">
                    @if ($activity->comment)
                        <div class="prose dark:prose-invert max-w-none">
                            {!! nl2br(e($activity->comment)) !!}
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">@lang('admin::app.common.no-data-available')</p>
                    @endif

                    @if($activity->emails && $activity->emails->count() > 0)
                        <div class="mt-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Gekoppelde E-mails</h3>
                            <div class="space-y-3">
                                @foreach($activity->emails as $email)
                                    <div class="flex items-start gap-3 p-3 rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                        <div class="flex-shrink-0">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                                                <span class="icon-mail text-sm"></span>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                        <a href="{{ route('admin.mail.view', ['route' => 'inbox', 'id' => $email->id]) }}" target="_blank" class="hover:underline" title="E-mail bekijken">
                                                            {{ $email->subject ?: 'Geen onderwerp' }}
                                                        </a>
                                                    </h4>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        {{ $email->created_at->format('d-m-Y H:i') }}
                                                    </p>
                                                </div>
                                                <a href="{{ route('admin.mail.view', ['route' => 'inbox', 'id' => $email->id]) }}" target="_blank" class="flex-shrink-0 ml-2 flex h-6 w-6 items-center justify-center rounded-md text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300" title="E-mail bekijken">
                                                    <span class="icon-right-arrow text-xs"></span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Right side column: Call status manager (like edit) -->
                <div class="w-[360px] max-w-full gap-2 max-lg:w-full">
                    @if ($activity->type === \App\Enums\ActivityType::CALL)
                        @include('admin::components.activities.call-status', ['activity' => $activity, 'callStatuses' => $callStatuses ?? []])
                    @endif
                </div>
            </div>
        </div>
    </div>
    @pushOnce('scripts')
        <script>
            (function(){
                var container = document.getElementById('activity-view-actions');
                if (!container) return;
                var shown = false;
                container.addEventListener('click', function(e){
                    if (shown) return;
                    shown = true;
                    try {
                        alert('Let op: acties worden uitgevoerd op de gekoppelde lead.');
                    } catch(_) {}
                }, { capture: true });
            })();
        </script>
    @endPushOnce

    <!-- Hidden form used by Afronden button in view -->
    <form id="activity-complete-form" action="{{ route('admin.activities.update', $activity->id) }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="_method" value="PUT" />
        <input type="hidden" name="is_done" value="1" />
        <input type="hidden" name="status" value="done" />
    </form>
</x-admin::layouts>

