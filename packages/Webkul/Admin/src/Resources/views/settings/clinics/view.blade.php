<x-admin::layouts>
    <x-slot:title>
        {{ $clinic->name }}
    </x-slot>

    <!-- Content -->
    <div class="flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Clinic Information -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <!-- Breadcrumbs and Actions -->
                <div class="flex items-center justify-between">
                    <div class="flex justify-start max-lg:hidden">
                        <div class="flex items-center gap-x-3.5">
                            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-300">
                                @lang('admin::app.layouts.settings')
                            </a>
                            <span class="text-gray-400">/</span>
                            <a href="{{ route('admin.settings.clinics.index') }}" class="text-gray-600 dark:text-gray-300">
                                @lang('admin::app.layouts.clinics')
                            </a>
                            <span class="text-gray-400">/</span>
                            <span class="text-gray-800 dark:text-white">{{ $clinic->name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Title -->
                <div class="mb-2 flex flex-col gap-0.5">
                    <h3 class="break-words text-lg font-bold dark:text-white">
                        {{ $clinic->name }}
                    </h3>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-2">
                    @if (bouncer()->hasPermission('settings.clinics.edit'))
                        <a
                            href="{{ route('admin.settings.clinics.edit', $clinic->id) }}"
                            class="secondary-button"
                            title="@lang('admin::app.settings.clinics.view.edit-btn')"
                        >
                            <i class="icon-edit text-xs"></i>
                            @lang('admin::app.settings.clinics.view.edit-btn')
                        </a>
                    @endif
                </div>
            </div>

            <!-- Clinic Attributes -->
            @include ('admin::settings.clinics.view.attributes')

            <!-- Footer with creation and modification dates -->
            <div class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
                @if ($clinic->creator)
                    <div class="flex justify-between">
                        <span>@lang('admin::app.settings.clinics.view.created-by'):</span>
                        <span>{{ $clinic->creator->name }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span>@lang('admin::app.settings.clinics.view.created-at'):</span>
                    <span>{{ $clinic->created_at->format('d-m-Y H:i') }}</span>
                </div>
                @if ($clinic->updater)
                    <div class="flex justify-between">
                        <span>@lang('admin::app.settings.clinics.view.updated-by'):</span>
                        <span>{{ $clinic->updater->name }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span>@lang('admin::app.settings.clinics.view.updated-at'):</span>
                    <span>{{ $clinic->updated_at->format('d-m-Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <!-- Tabs/Sections Navigation -->
            <x-admin::activities
                :endpoint="'#'" 
                :types="[]"
                :extra-types="[
                    ['name' => 'overview', 'label' => trans('admin::app.settings.clinics.view.tabs.overview')],
                    ['name' => 'partner-products', 'label' => trans('admin::app.settings.clinics.view.tabs.partner-products')],
                    ['name' => 'resources', 'label' => trans('admin::app.settings.clinics.view.tabs.resources')],
                    ['name' => 'audit-trail', 'label' => trans('admin::app.settings.clinics.view.tabs.audit-trail')],
                ]"
                :activeType="'overview'"
            >
                <!-- Overview Tab -->
                <x-slot:overview>
                    @include('admin::settings.clinics.view.overview')
                </x-slot>

                <!-- Partner Products Tab -->
                <x-slot:partner-products>
                    @include('admin::settings.clinics.view.partner-products')
                </x-slot>

                <!-- Resources Tab -->
                <x-slot:resources>
                    @include('admin::settings.clinics.view.resources')
                </x-slot>

                <!-- Audit Trail Tab -->
                <x-slot:audit-trail>
                    @include('admin::settings.clinics.view.audit-trail')
                </x-slot>
            </x-admin::activities>
        </div>
    </div>    
</x-admin::layouts>