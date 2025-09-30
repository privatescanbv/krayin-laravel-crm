{!! view_render_event('admin.settings.clinics.view.attributes.before', ['clinic' => $clinic]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800 dark:text-white">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <h4 class="font-semibold dark:text-white">
                @lang('admin::app.settings.clinics.view.attributes.about-clinic')
            </h4>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.settings.clinics.view.attributes.view.before', ['clinic' => $clinic]) !!}
    
            <!-- Attributes Listing -->
            <div class="flex flex-col gap-2">
                <!-- External ID -->
                @if ($clinic->external_id)
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
                @if (is_array($clinic->emails) && count($clinic->emails) > 0)
                    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                        <div class="label dark:text-white">
                            @lang('admin::app.settings.clinics.view.attributes.emails')
                        </div>
                        <div class="font-medium dark:text-white">
                            @foreach ($clinic->emails as $email)
                                <div>{{ $email }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Phones -->
                @if (is_array($clinic->phones) && count($clinic->phones) > 0)
                    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                        <div class="label dark:text-white">
                            @lang('admin::app.settings.clinics.view.attributes.phones')
                        </div>
                        <div class="font-medium dark:text-white">
                            @foreach ($clinic->phones as $phone)
                                <div>{{ $phone }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Address -->
                @if ($clinic->address)
                    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                        <div class="label dark:text-white">
                            @lang('admin::app.settings.clinics.view.attributes.address')
                        </div>
                        <div class="font-medium dark:text-white">
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
            
            {!! view_render_event('admin.settings.clinics.view.attributes.view.after', ['clinic' => $clinic]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.settings.clinics.view.attributes.after', ['clinic' => $clinic]) !!}