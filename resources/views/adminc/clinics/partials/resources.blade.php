<div class="p-4">
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h4 class="text-lg font-semibold dark:text-white">
                    @lang('admin::app.settings.clinics.view.resources.title')
                </h4>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    @lang('admin::app.settings.clinics.view.resources.total'): {{ $clinic->resources->count() }}
                </span>
            </div>
            @if (bouncer()->hasPermission('settings.resources.create'))
                <a href="{{ route('admin.settings.resources.create', ['clinic_id' => $clinic->id, 'return_to' => 'clinic_view']) }}" class="primary-button">
                    @lang('admin::app.settings.clinics.view.resources.add-btn')
                </a>
            @endif
        </div>

        @if ($clinic->resources->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.resources.table.name')
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.resources.table.resource-type')
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.resources.table.external-id')
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.resources.table.actions')
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clinic->resources as $resource)
                            <tr class="border-b border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="p-2 dark:text-white">
                                    {{ $resource->name }}
                                </td>
                                <td class="p-2 dark:text-white">
                                    {{ $resource->resourceType->name ?? '-' }}
                                </td>
                                <td class="p-2 dark:text-white">
                                    {{ $resource->external_id ?? '-' }}
                                </td>
                                <td class="p-2">
                                    <div class="flex gap-2">
                                        @if (bouncer()->hasPermission('settings.resources.edit'))
                                            <a
                                                href="{{ route('admin.settings.resources.edit', $resource->id) }}"
                                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                                title="@lang('admin::app.settings.clinics.view.resources.table.edit')"
                                            >
                                                <i class="icon-edit text-lg"></i>
                                            </a>
                                        @endif
                                        <a
                                            href="{{ route('admin.settings.resources.show', $resource->id) }}"
                                            class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300"
                                            title="@lang('admin::app.settings.clinics.view.resources.table.view')"
                                        >
                                            <i class="icon-eye text-lg"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-8 text-center">
                <p class="text-gray-600 dark:text-gray-400">
                    @lang('admin::app.settings.clinics.view.resources.no-resources')
                </p>
            </div>
        @endif
    </div>
</div>
