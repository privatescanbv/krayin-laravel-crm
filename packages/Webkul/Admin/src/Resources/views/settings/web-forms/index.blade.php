<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.webforms.index.title')
    </x-slot>

    <v-webform>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <!-- Bredcrumbs -->
                    <x-admin::breadcrumbs name="settings.web_forms" />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.settings.webforms.index.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Create button for person -->
                    <div class="flex items-center gap-x-2.5">
                        @if (bouncer()->hasPermission('admin.settings.web_forms.create'))
                            <button
                                type="button"
                                class="primary-button"
                            >
                                @lang('admin::app.settings.webforms.index.create-btn')
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- DataGrid Shimmer -->
            <x-admin::shimmer.settings.web-forms />
        </div>
    </v-webform>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-webform-template"
        >
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="flex flex-col gap-2">
                        <!-- Bredcrumbs -->
                        <x-admin::breadcrumbs name="settings.web_forms" />

                        <div class="text-xl font-bold dark:text-white">
                            @lang('admin::app.settings.webforms.index.title')
                        </div>
                    </div>

                    <div class="flex items-center gap-x-2.5">
                        <!-- Create button for person -->
                      @if (bouncer()->hasPermission('admin.settings.web_forms.create'))
                            <a
                                href="javascript:void(0);"
                                class="primary-button opacity-50 cursor-not-allowed pointer-events-none"
                                aria-disabled="true"
                                tabindex="-1"
                            >
                                @lang('admin::app.settings.webforms.index.create-btn')
                            </a>
                        @endif
                    </div>
                </div>

Disabled

            </div>
        </script>

        <script type="module">
            app.component('v-webform', {
                template: '#v-webform-template',
                data() {
                    return {};
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
