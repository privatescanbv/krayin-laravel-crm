<x-admin::layouts>
    <!--Page title -->
    <x-slot:title>
        @lang('admin::app.contacts.persons.create.title')
    </x-slot>

    {!! view_render_event('admin.persons.create.form.before') !!}

    <!--Create Page Form -->
    <x-admin::form
        :action="route('admin.contacts.persons.store')"
        enctype="multipart/form-data"
    >
        <div class="flex flex-col gap-4">
            <!-- Header -->
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.persons.create.breadcrumbs.before') !!}

                    <!-- Breadcrumb -->
                    <x-admin::breadcrumbs name="contacts.persons.create" />

                    {!! view_render_event('admin.persons.create.breadcrumbs.after') !!}

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.contacts.persons.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.persons.create.create_button.before') !!}

                        <!-- Create button for Person -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.contacts.persons.create.save-btn')
                        </button>

                        {!! view_render_event('admin.persons.create.create_button.after') !!}
                    </div>
                </div>
            </div>

            <!-- Personal Fields Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Persoonsgegevens
                    </h3>
                </div>

                @include('admin::leads.common.personal-fields', ['entity' => null])
            </div>

            <!-- Emails Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        @lang('admin::app.leads.common.emails.title')
                    </h3>
                </div>

                <x-adminc::components.emails name="emails" :value="old('emails', [])"/>
            </div>

            <!-- Phones Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Telefoonnummers
                    </h3>
                </div>
                <x-adminc::components.phones name="phones" :value="old('phones', [])"/>
            </div>

            <!-- Address Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        @lang('admin::app.contacts.persons.create.address')
                    </h3>
                </div>

                <x-adminc::components.address />
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.persons.create.form.after') !!}
</x-admin::layouts>
