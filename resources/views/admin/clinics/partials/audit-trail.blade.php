<div class="p-4">
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h4 class="mb-4 text-lg font-semibold dark:text-white">
            @lang('admin::app.settings.clinics.view.audit-trail.title')
        </h4>

        <div class="flex flex-col gap-4">
            <!-- Creation Info -->
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <h5 class="mb-3 font-semibold dark:text-white">
                    @lang('admin::app.settings.clinics.view.audit-trail.creation-info')
                </h5>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.audit-trail.created-at'):
                        </span>
                        <span class="dark:text-white">
                            {{ $clinic->created_at->format('d-m-Y H:i:s') }}
                        </span>
                    </div>
                    @if ($clinic->creator)
                        <div class="grid grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium text-gray-600 dark:text-gray-400">
                                @lang('admin::app.settings.clinics.view.audit-trail.created-by'):
                            </span>
                            <span class="dark:text-white">
                                {{ $clinic->creator->name }}
                                @if ($clinic->creator->email)
                                    ({{ $clinic->creator->email }})
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Last Update Info -->
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <h5 class="mb-3 font-semibold dark:text-white">
                    @lang('admin::app.settings.clinics.view.audit-trail.update-info')
                </h5>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.audit-trail.updated-at'):
                        </span>
                        <span class="dark:text-white">
                            {{ $clinic->updated_at->format('d-m-Y H:i:s') }}
                        </span>
                    </div>
                    @if ($clinic->updater)
                        <div class="grid grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium text-gray-600 dark:text-gray-400">
                                @lang('admin::app.settings.clinics.view.audit-trail.updated-by'):
                            </span>
                            <span class="dark:text-white">
                                {{ $clinic->updater->name }}
                                @if ($clinic->updater->email)
                                    ({{ $clinic->updater->email }})
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Change Summary -->
            @if ($clinic->created_at->diffInSeconds($clinic->updated_at) > 0)
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <div class="flex items-start gap-2">
                        <i class="icon-information mt-1 text-blue-600 dark:text-blue-400"></i>
                        <div class="text-sm">
                            <p class="font-medium text-blue-800 dark:text-blue-300">
                                @lang('admin::app.settings.clinics.view.audit-trail.change-summary')
                            </p>
                            <p class="mt-1 text-blue-700 dark:text-blue-400">
                                @lang('admin::app.settings.clinics.view.audit-trail.last-modified'):
                                {{ $clinic->updated_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Additional Info -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h5 class="mb-3 font-semibold dark:text-white">
                    @lang('admin::app.settings.clinics.view.audit-trail.additional-info')
                </h5>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.audit-trail.record-id'):
                        </span>
                        <span class="dark:text-white">
                            {{ $clinic->id }}
                        </span>
                    </div>
                    @if ($clinic->external_id)
                        <div class="grid grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium text-gray-600 dark:text-gray-400">
                                @lang('admin::app.settings.clinics.view.audit-trail.external-id'):
                            </span>
                            <span class="dark:text-white">
                                {{ $clinic->external_id }}
                            </span>
                        </div>
                    @endif
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.audit-trail.total-updates'):
                        </span>
                        <span class="dark:text-white">
                            @if ($clinic->created_at->eq($clinic->updated_at))
                                0 (@lang('admin::app.settings.clinics.view.audit-trail.never-updated'))
                            @else
                                @lang('admin::app.settings.clinics.view.audit-trail.modified')
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>