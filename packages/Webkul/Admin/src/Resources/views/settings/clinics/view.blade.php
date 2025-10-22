<x-admin::layouts>
    <x-slot:title>
        {{ $clinic->name }}
    </x-slot:title>

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
                    <!-- Mail Activity Action -->
                    <x-admin::activities.actions.mail
                        :entity="$clinic"
                        entity-control-name="clinic_id"
                    />

                    <!-- File Activity Action -->
                    <x-admin::activities.actions.file
                        :entity="$clinic"
                        entity-control-name="clinic_id"
                    />

                    <!-- Note Activity Action -->
                    <x-admin::activities.actions.note
                        :entity="$clinic"
                        entity-control-name="clinic_id"
                    />

                    <!-- Activity Action -->
                    <x-admin::activities.actions.activity
                        :entity="$clinic"
                        entity-control-name="clinic_id"
                    />

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

        <!-- Right Panel with Tabs -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <v-clinic-tabs>
                    <!-- Tab Navigation will be rendered by Vue -->
                    
                    <!-- Activities Tab Content -->
                    <template #activities>
                        <x-admin::activities
                            :endpoint="route('admin.settings.clinics.activities.index', $clinic->id)"
                            :activeType="'planned'"
                        />
                    </template>

                    <!-- Overview Tab Content -->
                    <template #overview>
                        @include('admin::settings.clinics.view.overview')
                    </template>

                    <!-- Partner Products Tab Content -->
                    <template #partner-products>
                        @include('admin::admin.clinic-products.index')
                    </template>

                    <!-- Resources Tab Content -->
                    <template #resources>
                        @include('admin::settings.clinics.view.resources')
                    </template>

                    <!-- Audit Trail Tab Content -->
                    <template #audit-trail>
                        @include('admin::settings.clinics.view.audit-trail')
                    </template>
                </v-clinic-tabs>
            </div>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-clinic-tabs-template">
            <div>
                <!-- Tabs Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-800">
                    <div class="flex gap-4 px-4">
                        <button
                            @click="activeTab = 'activities'"
                            :class="activeTab === 'activities' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white'"
                            class="py-3 text-sm font-medium transition"
                        >
                            @lang('admin::app.settings.clinics.view.tabs.activities')
                        </button>
                        <button
                            @click="activeTab = 'overview'"
                            :class="activeTab === 'overview' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white'"
                            class="py-3 text-sm font-medium transition"
                        >
                            @lang('admin::app.settings.clinics.view.tabs.overview')
                        </button>
                        <button
                            @click="activeTab = 'partner-products'"
                            :class="activeTab === 'partner-products' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white'"
                            class="py-3 text-sm font-medium transition"
                        >
                            @lang('admin::app.settings.clinics.view.tabs.partner-products')
                        </button>
                        <button
                            @click="activeTab = 'resources'"
                            :class="activeTab === 'resources' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white'"
                            class="py-3 text-sm font-medium transition"
                        >
                            @lang('admin::app.settings.clinics.view.tabs.resources')
                        </button>
                        <button
                            @click="activeTab = 'audit-trail'"
                            :class="activeTab === 'audit-trail' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white'"
                            class="py-3 text-sm font-medium transition"
                        >
                            @lang('admin::app.settings.clinics.view.tabs.audit-trail')
                        </button>
                    </div>
                </div>

                <!-- Tab Content Slots -->
                <div>
                    <div v-show="activeTab === 'activities'">
                        <slot name="activities"></slot>
                    </div>

                    <div v-show="activeTab === 'overview'">
                        <slot name="overview"></slot>
                    </div>

                    <div v-show="activeTab === 'partner-products'">
                        <slot name="partner-products"></slot>
                    </div>

                    <div v-show="activeTab === 'resources'">
                        <slot name="resources"></slot>
                    </div>

                    <div v-show="activeTab === 'audit-trail'">
                        <slot name="audit-trail"></slot>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-clinic-tabs', {
                template: '#v-clinic-tabs-template',

                data() {
                    return {
                        activeTab: 'activities'
                    };
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>