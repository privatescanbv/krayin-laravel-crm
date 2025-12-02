<x-admin::layouts>
    <!-- Page title -->
    <x-slot:title>
        @lang('admin::app.account.edit.title')
    </x-slot>

    {!! view_render_event('admin.user.account.form.before', ['user' => $user]) !!}

    <!-- Input Form -->
    <x-admin::form
        :action="route('admin.user.account.update')"
        enctype="multipart/form-data"
        method="PUT"
    >
        @include('adminc.components.validation-errors')
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('admin.user.account.breadcrumbs.before', ['user' => $user]) !!}

                <!-- Breadcrumbs -->
                <x-admin::breadcrumbs
                    name="dashboard.account.edit"
                    :entity="$user"
                />

                {!! view_render_event('admin.user.account.breadcrumbs.after', ['user' => $user]) !!}

                <div class="text-xl font-bold dark:text-white">
                    {!! view_render_event('admin.user.account.title.before', ['user' => $user]) !!}

                    @lang('admin::app.account.edit.title')

                    {!! view_render_event('admin.user.account.title.after', ['user' => $user]) !!}
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <!-- Create button for Roles -->
                <div class="flex items-center gap-x-2.5">
                    {!! view_render_event('admin.user.account.save_btn.before', ['user' => $user]) !!}

                    <button
                        type="submit"
                        class="primary-button"
                    >
                        @lang('admin::app.account.edit.save-btn')
                    </button>

                    {!! view_render_event('admin.user.account.save_btn.after', ['user' => $user]) !!}
                </div>
            </div>
        </div>

        <!-- Full Panel -->
        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            {!! view_render_event('admin.user.account.left.before', ['user' => $user]) !!}

            <!-- Left sub Component -->
            <div class="flex flex-1 flex-col gap-2">
                <!-- General -->
                <div class="box-shadow rounded bg-white p-4 dark:bg-gray-900">
                    <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                        @lang('admin::app.account.edit.general')
                    </p>

                    <!-- Image -->
                    <x-admin::form.control-group>
                        <x-admin::media.images
                            name="image"
                            :uploaded-images="$user->image ? [['id' => 'image', 'url' => $user->image_url]] : []"
                        />
                    </x-admin::form.control-group>

                    <p class="mb-4 text-xs text-gray-600 dark:text-gray-300">
                        @lang('admin::app.account.edit.upload-image-info')
                    </p>

                    <!-- First Name -->
                    <x-adminc::components.field
                        type="text"
                        name="first_name"
                        :label="trans('admin::app.account.edit.first-name')"
                        value="{{ old('first_name') ?: $user->first_name }}"
                        rules="required"
                        :placeholder="trans('admin::app.account.edit.first-name')"
                    />

                    <!-- Last Name -->
                    <x-adminc::components.field
                        type="text"
                        name="last_name"
                        :label="trans('admin::app.account.edit.last-name')"
                        value="{{ old('last_name') ?: $user->last_name }}"
                        rules="required"
                        :placeholder="trans('admin::app.account.edit.last-name')"
                    />

                    <!-- Email -->
                    <x-adminc::components.field
                        type="email"
                        name="email"
                        id="email"
                        :label="trans('admin::app.account.edit.email')"
                        value="{{ old('email') ?: $user->email }}"
                        rules="required"
                        class="!mb-0"
                    />
                </div>
            </div>

            {!! view_render_event('admin.user.account.left.after', ['user' => $user]) !!}

            {!! view_render_event('admin.user.account.right.before', ['user' => $user]) !!}

            <!-- Right sub-component -->
            <div class="flex w-[360px] max-w-full flex-col gap-2 max-md:w-full">
                <x-admin::accordion>
                    <x-slot:header>
                        <p class="p-2.5 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.account.edit.change-password')
                        </p>
                    </x-slot>

                     <!-- Change Account Password -->
                    <x-slot:content>
                        {!! view_render_event('admin.user.current_password.before', ['user' => $user]) !!}

                        <!-- Current Password -->
                        <x-adminc::components.field
                            type="password"
                            name="current_password"
                            :label="trans('admin::app.account.edit.current-password')"
                            rules="required|min:6"
                            :placeholder="trans('admin::app.account.edit.current-password')"
                        />

                        {!! view_render_event('admin.user.current_password.after', ['user' => $user]) !!}

                        {!! view_render_event('admin.user.password.before', ['user' => $user]) !!}

                        <!-- Password -->
                        <x-adminc::components.field
                            type="password"
                            name="password"
                            :label="trans('admin::app.account.edit.password')"
                            rules="min:6"
                            :placeholder="trans('admin::app.account.edit.password')"
                            ref="password"
                        />

                        {!! view_render_event('admin.user.password.after', ['user' => $user]) !!}

                        {!! view_render_event('admin.user.confirm-password.before', ['user' => $user]) !!}

                        <!-- Confirm Password -->
                        <x-adminc::components.field
                            type="password"
                            name="password_confirmation"
                            :label="trans('admin::app.account.edit.confirm-password')"
                            rules="confirmed:@password"
                            :placeholder="trans('admin::app.account.edit.confirm-password')"
                            class="!mb-0"
                        />

                        {!! view_render_event('admin.user.confirm-password.after', ['user' => $user]) !!}
                    </x-slot>
                </x-admin::accordion>
            </div>

            {!! view_render_event('admin.user.account.right.after', ['user' => $user]) !!}
        </div>
    </x-admin::form>

    {!! view_render_event('admin.user.account.form.after') !!}
</x-admin::layouts>
