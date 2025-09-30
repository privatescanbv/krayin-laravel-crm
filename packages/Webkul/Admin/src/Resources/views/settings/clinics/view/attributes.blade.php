<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800 dark:text-white">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <h4 class="font-semibold dark:text-white">
                @lang('admin::app.settings.clinics.view.attributes.about-clinic')
            </h4>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            <!-- Attributes Listing -->
            <div class="flex flex-col gap-2">
                <!-- External ID -->
                @if (!empty($clinic->external_id))
                    <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                        <div class="label dark:text-white">
                            @lang('admin::app.settings.clinics.view.attributes.external-id')
                        </div>
                        <div class="font-medium dark:text-white">
                            {{ $clinic->external_id }}
                        </div>
                    </div>
                @endif

                <!-- Name -->
                <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                    <div class="label dark:text-white">
                        @lang('admin::app.settings.clinics.view.attributes.name')
                    </div>
                    <div class="font-medium dark:text-white">
                        {{ $clinic->name }}
                    </div>
                </div>

                <!-- Emails -->
                <x-admin::clinic.contact-fields
                    :label="trans('admin::app.settings.clinics.view.attributes.emails')"
                    :fields="$clinic->emails"
                    type="email"
                />

                <!-- Phones -->
                <x-admin::clinic.contact-fields
                    :label="trans('admin::app.settings.clinics.view.attributes.phones')"
                    :fields="$clinic->phones"
                    type="phone"
                />

                <!-- Address -->
                @if ($clinic->address)
                    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                        <div class="label dark:text-white">
                            @lang('admin::app.settings.clinics.view.attributes.address')
                        </div>
                        <div class="font-medium dark:text-white">
                            @if (!empty($clinic->address->address_line_1))
                                <div>{{ $clinic->address->address_line_1 }}</div>
                            @endif
                            @if (!empty($clinic->address->address_line_2))
                                <div>{{ $clinic->address->address_line_2 }}</div>
                            @endif
                            @if (!empty($clinic->address->postal_code) || !empty($clinic->address->city))
                                <div>
                                    @if (!empty($clinic->address->postal_code))
                                        {{ $clinic->address->postal_code }}
                                    @endif
                                    @if (!empty($clinic->address->city))
                                        {{ $clinic->address->city }}
                                    @endif
                                </div>
                            @endif
                            @if (!empty($clinic->address->state))
                                <div>{{ $clinic->address->state }}</div>
                            @endif
                            @if (!empty($clinic->address->country))
                                <div>{{ $clinic->address->country }}</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-slot>
    </x-admin::accordion>
</div>