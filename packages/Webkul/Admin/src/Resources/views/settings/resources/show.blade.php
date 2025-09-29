<x-admin::layouts>
    <x-slot:title>
        {{ $resource->name }} — @lang('admin::app.settings.resources.show.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.resources.view" :entity="$resource" />

                <div class="text-xl font-bold dark:text-gray-300">
                    {{ $resource->name }}
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a class="secondary-button" href="{{ route('admin.settings.resources.edit', $resource->id) }}">@lang('admin::app.edit')</a>
                <a class="secondary-button" href="{{ route('admin.settings.resources.shifts.index', $resource->id) }}">@lang('admin::app.settings.resources.show.manage-shifts')</a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-2 text-base font-semibold">@lang('admin::app.settings.resources.show.details')</div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <div><strong>@lang('admin::app.settings.resources.index.create.name'):</strong> {{ $resource->name }}</div>
                    <div><strong>@lang('admin::app.settings.resources.index.create.resource_type'):</strong> {{ optional($resource->resourceType)->name }}</div>
                    <div><strong>Clinic:</strong> {{ optional($resource->clinic)->name }}</div>
                </div>
            </div>

            <div class="lg:col-span-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 text-base font-semibold">@lang('admin::app.settings.resources.show.schedule')</div>
                <div class="relative w-full overflow-x-auto">
                    <div class="min-w-[640px]">
                        @if (empty($periodSummaries))
                            <div class="mb-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <div>Geen roosterinformatie beschikbaar</div>
                            </div>
                        @else
                            @foreach ($periodSummaries as $period)
                                <div class="mb-4 rounded-md border border-gray-200 p-3 dark:border-gray-800">
                                    <div class="mb-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <div>Periode: {{ $period['label'] }}</div>
                                    </div>
                                    @include('admin::settings.resources.partials.weekly-summary', ['summary' => $period['summary']])
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin::layouts>


