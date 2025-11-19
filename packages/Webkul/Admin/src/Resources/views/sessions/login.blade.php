<x-admin::layouts.anonymous>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.users.login.title')
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
                {!! view_render_event('admin.sessions.login.form_controls.before') !!}

                <!-- Login Form -->
                <x-admin::form :action="route('admin.session.store')">
                    <p class="p-4 text-xl font-bold text-gray-800 dark:text-white">
                        @lang('admin::app.users.login.title')
                    </p>

                    <div class="border-y p-4 dark:border-gray-800">
                        <!-- Email -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.control
                                type="email"
                                class="w-[254px] max-w-full"
                                id="email"
                                name="email"
                                rules="required|email"
                                :label="trans('admin::app.users.login.email')"
                                :placeholder="trans('admin::app.users.login.email')"
                            />
                            <x-admin::form.control-group.label class="required">
                                @lang('admin::app.users.login.email')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.error control-name="email" />

                        </x-admin::form.control-group>

                        <!-- Password -->
                        <x-admin::form.control-group class="relative w-full">
                            <x-admin::form.control-group.control
                                type="password"
                                class="w-[254px] max-w-full ltr:pr-10 rtl:pl-10"
                                id="password"
                                name="password"
                                rules="required|min:6"
                                :label="trans('admin::app.users.login.password')"
                                :placeholder="trans('admin::app.users.login.password')"
                            />

                            <span
                                class="icon-eye-hide absolute top-3 cursor-pointer right-6 text-2xl ltr:right-6 rtl:left-6"
                                onclick="switchVisibility()"
                                id="visibilityIcon"
                                role="presentation"
                                tabindex="0"
                            >
                            </span>
                            <x-admin::form.control-group.label class="required">
                                @lang('admin::app.users.login.password')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.error control-name="password" />

                        </x-admin::form.control-group>
                    </div>

                    <div class="flex items-center justify-between p-4">
                        <!-- Forgot Password Link -->
                        <a
                            class="cursor-pointer text-xs font-semibold leading-6 text-brandColor"
                            href="{{ route('admin.forgot_password.create') }}"
                        >
                            @lang('admin::app.users.login.forget-password-link')
                        </a>

                        <!-- Submit Button -->
                        <button
                            class="primary-button"
                            aria-label="{{ trans('admin::app.users.login.submit-btn')}}"
                        >
                            @lang('admin::app.users.login.submit-btn')
                        </button>
                    </div>
                </x-admin::form>

                @if(config('services.keycloak.client_id'))
                    <div class="border-t p-4 dark:border-gray-800">
                        <div class="mb-3 flex items-center">
                            <div class="flex-1 border-t border-gray-300 dark:border-gray-700"></div>
                            <span class="px-3 text-xs text-gray-500 dark:text-gray-400">of</span>
                            <div class="flex-1 border-t border-gray-300 dark:border-gray-700"></div>
                        </div>
                        <a
                            href="{{ route('admin.keycloak.redirect') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 2.4c5.302 0 9.6 4.298 9.6 9.6S17.302 21.6 12 21.6 2.4 17.302 2.4 12 6.698 2.4 12 2.4zm0 1.2c-4.64 0-8.4 3.76-8.4 8.4S7.36 20.4 12 20.4s8.4-3.76 8.4-8.4S16.64 3.6 12 3.6zm0 1.8c3.717 0 6.6 2.883 6.6 6.6S15.717 18.6 12 18.6 5.4 15.717 5.4 12 8.283 5.4 12 5.4zm0 1.2c-2.98 0-5.4 2.42-5.4 5.4S9.02 17.4 12 17.4s5.4-2.42 5.4-5.4-2.42-5.4-5.4-5.4z"/>
                            </svg>
                            Inloggen met SSO
                        </a>
                    </div>
                @endif

                {!! view_render_event('admin.sessions.login.form_controls.after') !!}
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

    @push('scripts')
        <script>
            function switchVisibility() {
                let passwordField = document.getElementById("password");
                let visibilityIcon = document.getElementById("visibilityIcon");

                passwordField.type = passwordField.type === "password" ? "text" : "password";
                visibilityIcon.classList.toggle("icon-eye");
            }
        </script>
        
        {{-- 
            Keycloak logout is now handled via direct redirect in SessionController@destroy
            Backchannel logout endpoint handles server-side logout if configured in Keycloak
            No need for iframe logout anymore (CSP blocks it anyway)
        --}}
    @endpush
</x-admin::layouts.anonymous>
