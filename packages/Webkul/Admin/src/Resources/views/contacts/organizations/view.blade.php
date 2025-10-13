<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.contacts.organizations.view.title')
    </x-slot>

    {!! view_render_event('admin.organizations.view.form.before') !!}

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('admin.organizations.view.breadcrumbs.before', ['organization' => $organization]) !!}

                <x-admin::breadcrumbs
                    name="contacts.organizations.view"
                    :params="['id' => $organization->id]"
                />

                {!! view_render_event('admin.organizations.view.breadcrumbs.after', ['organization' => $organization]) !!}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $organization->name }}
                    </h3>
                </div>

                @if($organization->address)
                    <div>
                        <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-2">
                            @lang('admin::app.contacts.organizations.view.address')
                        </h4>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $organization->address->street }} {{ $organization->address->house_number }}{{ $organization->address->house_number_suffix }}
                            <br>
                            {{ $organization->address->postal_code }} {{ $organization->address->city }}
                            <br>
                            {{ $organization->address->state }}, {{ $organization->address->country }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {!! view_render_event('admin.organizations.view.form.after') !!}
</x-admin::layouts>