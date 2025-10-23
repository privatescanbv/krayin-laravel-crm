
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.contacts.persons.edit.title')
    </x-slot>

    {!! view_render_event('admin.persons.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.contacts.persons.update', $person->id)"
        method="PUT"
        enctype="multipart/form-data"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.persons.edit.breadcrumbs.before') !!}

                    <x-admin::breadcrumbs
                        name="contacts.persons.edit"
                        :entity="$person"
                    />

                    {!! view_render_event('admin.persons.edit.breadcrumbs.after') !!}

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.contacts.persons.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!--  Save button for Person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.persons.edit.save_button.before') !!}

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.contacts.persons.edit.save-btn')
                        </button>

                        {!! view_render_event('admin.persons.edit.save_button.after') !!}
                    </div>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.contacts.persons.edit.form_controls.before') !!}

                <x-admin::attributes
                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                        'entity_type' => 'persons',
                    ])"
                    :custom-validations="[
                        'job_title' => [
                            'max:100',
                        ],
                    ]"
                    :entity="$person"
                />

                {!! view_render_event('admin.contacts.persons.edit.form_controls.after') !!}
            </div>

            <!-- Personal Fields Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.contacts.persons.edit.personal_fields.before') !!}

                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Persoonsgegevens
                    </h3>
                </div>

                @include('admin::leads.common.personal-fields', ['entity' => $person])

                {!! view_render_event('admin.contacts.persons.edit.personal_fields.after') !!}
            </div>

            <!-- Emails Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        @lang('admin::app.leads.common.emails.title')
                    </h3>
                </div>
                <x-adminc::components.emails name="emails" :value="old('emails', $person->emails ?? [])"/>
            </div>

            <!-- Phones Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Telefoonnummers
                    </h3>
                </div>
                <x-adminc::components.phones name="phones" :value="old('phones', $person->phones ?? [])"/>
            </div>

            {!! view_render_event('admin.contacts.persons.edit.address.before', ['lead' => $person]) !!}

            <!-- Address Section -->
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="w-1/2 max-md:w-full">
                    <!-- Address Component -->
                    @include('admin::components.address', ['entity' => $person])
                </div>
            </div>

            {!! view_render_event('admin.contacts.persons.edit.address.after', ['lead' => $person]) !!}
        </div>
    </x-admin::form>

    {!! view_render_event('admin.persons.edit.form.after') !!}
</x-admin::layouts>
