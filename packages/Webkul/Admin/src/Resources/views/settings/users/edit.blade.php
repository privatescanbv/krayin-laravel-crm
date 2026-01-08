<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.users.edit-title')
    </x-slot>

    <x-admin::form
        :action="route('admin.settings.users.update', $user->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.users.edit" :entity="$user" />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.settings.users.edit-title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a
                        href="{{ route('admin.settings.users.index') }}"
                        class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                    >
                        @lang('admin::app.settings.users.index.cancel')
                    </a>

                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.users.index.create.save-btn')
                    </button>
                </div>
            </div>

            @include('admin::settings.users._form', [
                'user' => $user,
                'roles' => $roles,
                'groups' => $groups,
                'settingsMap' => $settingsMap ?? [],
            ])
        </div>
    </x-admin::form>
</x-admin::layouts>

