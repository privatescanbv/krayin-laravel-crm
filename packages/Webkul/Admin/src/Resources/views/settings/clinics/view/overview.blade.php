<div class="p-4">
    <div class="flex flex-col gap-4 dark:text-white">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h4 class="mb-4 text-lg font-semibold dark:text-white">
                @lang('admin::app.settings.clinics.view.overview.general-info')
            </h4>

            <div class="flex flex-col gap-3">
                <div class="grid grid-cols-[200px_1fr] gap-2">
                    <span class="font-medium text-gray-600 dark:text-gray-400">
                        @lang('admin::app.settings.clinics.view.overview.name'):
                    </span>
                    <span class="dark:text-white">{{ $clinic->name }}</span>
                </div>

                @if ($clinic->external_id)
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.external-id'):
                        </span>
                        <span class="dark:text-white">{{ $clinic->external_id }}</span>
                    </div>
                @endif

                @if (is_array($clinic->emails) && count($clinic->emails) > 0)
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.emails'):
                        </span>
                        <div>
                            @foreach ($clinic->emails as $email)
                                <div class="dark:text-white">{{ $email }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (is_array($clinic->phones) && count($clinic->phones) > 0)
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.phones'):
                        </span>
                        <div>
                            @foreach ($clinic->phones as $phone)
                                <div class="dark:text-white">{{ $phone }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($clinic->address)
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.address'):
                        </span>
                        <div class="dark:text-white">
                            @if ($clinic->address->address_line_1)
                                <div>{{ $clinic->address->address_line_1 }}</div>
                            @endif
                            @if ($clinic->address->address_line_2)
                                <div>{{ $clinic->address->address_line_2 }}</div>
                            @endif
                            @if ($clinic->address->postal_code || $clinic->address->city)
                                <div>
                                    @if ($clinic->address->postal_code)
                                        {{ $clinic->address->postal_code }}
                                    @endif
                                    @if ($clinic->address->city)
                                        {{ $clinic->address->city }}
                                    @endif
                                </div>
                            @endif
                            @if ($clinic->address->state)
                                <div>{{ $clinic->address->state }}</div>
                            @endif
                            @if ($clinic->address->country)
                                <div>{{ $clinic->address->country }}</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <!-- Partner Products Count -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.total-partner-products')
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{ $clinic->partnerProducts->count() }}
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                        <i class="icon-product text-2xl text-blue-600 dark:text-blue-400"></i>
                    </div>
                </div>
            </div>

            <!-- Resources Count -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.total-resources')
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{ $clinic->resources->count() }}
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                        <i class="icon-setting text-2xl text-green-600 dark:text-green-400"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>