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
                        <div class="mb-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <div>Samenvatting van roosters (samengevoegd)</div>
                        </div>
                        <div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-800">
                            @php
                                $dayNames = [
                                    1 => trans('admin::app.monday'),
                                    2 => trans('admin::app.tuesday'),
                                    3 => trans('admin::app.wednesday'),
                                    4 => trans('admin::app.thursday'),
                                    5 => trans('admin::app.friday'),
                                    6 => trans('admin::app.saturday'),
                                    7 => trans('admin::app.sunday'),
                                ];
                            @endphp

                            @for($day = 1; $day <= 7; $day++)
                                @php
                                    $daySummary = $scheduleSummary[$day] ?? ['available' => [], 'unavailable' => []];
                                    $available = $daySummary['available'] ?? [];
                                    $unavailable = $daySummary['unavailable'] ?? [];
                                @endphp
                                <div class="py-3 text-sm">
                                    <div class="mb-2 font-semibold">{{ $dayNames[$day] }}</div>
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300">Beschikbaar</span>
                                        @if (count($available))
                                            @foreach($available as $range)
                                                <span class="rounded border border-green-300 px-2 py-0.5 text-xs text-green-800 dark:border-green-700 dark:text-green-300">{{ $range['from'] }}–{{ $range['to'] }}</span>
                                            @endforeach
                                        @else
                                            <span class="text-xs text-gray-500">—</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300">Niet beschikbaar</span>
                                        @if (count($unavailable))
                                            @foreach($unavailable as $range)
                                                <span class="rounded border border-red-300 px-2 py-0.5 text-xs text-red-800 dark:border-red-700 dark:text-red-300">{{ $range['from'] }}–{{ $range['to'] }}</span>
                                            @endforeach
                                        @else
                                            <span class="text-xs text-gray-500">—</span>
                                        @endif
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin::layouts>


