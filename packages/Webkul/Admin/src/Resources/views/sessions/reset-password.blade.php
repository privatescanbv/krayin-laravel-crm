<x-admin::layouts.anonymous>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.users.reset-password.title')
    </x-slot>

    <div class="flex h-[100vh] flex-col items-center justify-center gap-10">
        <div class="flex flex-col items-center gap-5">
            <!-- Logo -->
            @if ($logo = core()->getConfigData('general.design.admin_logo.logo_image'))
                <img
                    class="h-10 w-[110px]"
                    src="{{ Storage::url($logo) }}"
                    alt="{{ config('app.name') }}"
                />
            @else
                <img
                    class="w-max"
                    src="{{ vite()->asset('images/logo.svg') }}"
                    alt="{{ config('app.name') }}"
                />
            @endif

            <div class="box-shadow flex min-w-[300px] flex-col rounded-md bg-white dark:bg-gray-900">
                {!! view_render_event('admin.sessions.reset-password.form_controls.before') !!}

                <!-- Login Form -->
                <x-admin::form :action="route('admin.reset_password.store')">
                    <div class="p-4">
                        <p class="text-xl font-bold text-gray-800 dark:text-white">
                            @lang('admin::app.users.reset-password.title')
                        </p>
                    </div>

                    <x-admin::form.control-group.control
                        type="hidden"
                        name="token"
                        :value="$token"
                    />

                    <div class="border-y p-4 dark:border-gray-800">
                        <!-- Email -->
                        <x-adminc::components.field
                            type="email"
                            class="w-[254px] max-w-full"
                            id="email"
                            name="email"
                            :label="trans('admin::app.users.reset-password.email')"
                            rules="required|email"
                            :placeholder="trans('admin::app.users.reset-password.email')"
                        />

                        <!-- Password -->
                        <x-adminc::components.field
                            type="password"
                            class="w-[254px] max-w-full"
                            id="password"
                            name="password"
                            :label="trans('admin::app.users.reset-password.password')"
                            rules="required|min:6"
                            :placeholder="trans('admin::app.users.reset-password.password')"
                            ref="password"
                        />

                        <!-- Confirm Password -->
                        <x-adminc::components.field
                            type="password"
                            class="w-[254px] max-w-full"
                            id="password_confirmation"
                            name="password_confirmation"
                            :label="trans('admin::app.users.reset-password.confirm-password')"
                            rules="confirmed:@password"
                            :placeholder="trans('admin::app.users.reset-password.confirm-password')"
                            ref="password"
                        />
                    </div>

                    <div class="flex items-center justify-between p-4">
                        <!-- Back Button-->
                        <a
                            class="cursor-pointer text-xs font-semibold leading-6 text-brandColor"
                            href="{{ route('admin.session.create') }}"
                        >
                            @lang('admin::app.users.reset-password.back-link-title')
                        </a>

                        <!-- Submit Button -->
                        <button
                            class="primary-button">
                            @lang('admin::app.users.reset-password.submit-btn')
                        </button>
                    </div>
                </x-admin::form>

                {!! view_render_event('admin.sessions.reset-password.form_controls.after') !!}
            </div>
        </div>

        <!-- Powered By -->
        <div class="text-sm font-normal">
            @lang('admin::app.components.layouts.powered-by.description', [
                'krayin' => '<a class="text-brandColor hover:underline " href="https://krayincrm.com/">Krayin</a>',
                'webkul' => '<a class="text-brandColor hover:underline " href="https://webkul.com/">Webkul</a>',
            ]) 
        </div>
    </div>
</x-admin::layouts.anonymous>
