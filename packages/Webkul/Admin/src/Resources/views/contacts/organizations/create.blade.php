
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.contacts.organizations.create.title')
    </x-slot>

    {!! view_render_event('admin.organizations.create.form.before') !!}

    <x-admin::form
        :action="route('admin.contacts.organizations.store')"
        method="POST"
    >
        @include('adminc.components.validation-errors')

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.organizations.create.breadcrumbs.before') !!}

                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs name="contacts.organizations.create" />

                    {!! view_render_event('admin.organizations.create.breadcrumbs.before') !!}

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.contacts.organizations.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.organizations.create.save_buttons.before') !!}

                        <!-- Create button for person -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.contacts.organizations.create.save-btn')
                        </button>

                        {!! view_render_event('admin.organizations.create.save_buttons.before') !!}
                    </div>
                </div>
            </div>

            <!-- Address Section -->
            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        @lang('admin::app.contacts.organizations.create.address')
                    </h3>
                </div>

                <x-adminc::components.address />

                {!! view_render_event('admin.contacts.organizations.edit.form_controls.after') !!}
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.organizations.create.form.after') !!}
</x-admin::layouts>
