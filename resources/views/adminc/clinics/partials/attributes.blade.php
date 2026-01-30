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

                <!-- Website URL -->
                @if (!empty($clinic->website_url))
                    <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                        <div class="label dark:text-white">
                            Website
                        </div>
                        <div class="font-medium dark:text-white">
                            <a href="{{ $clinic->website_url }}" target="_blank" class="text-activity-note-text hover:underline dark:text-blue-400">
                                {{ $clinic->website_url }}
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Order Confirmation Note -->
                @if (!empty($clinic->order_confirmation_note))
                    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                        <div class="label dark:text-white">
                            Opmerking orderbevestiging
                        </div>
                        <div class="font-medium dark:text-white">
                            {{ $clinic->order_confirmation_note }}
                        </div>
                    </div>
                @endif

                <!-- Emails -->
                <x-adminc::clinics.partials.contact-fields
                    :label="trans('admin::app.settings.clinics.view.attributes.emails')"
                    :fields="$clinic->emails"
                    type="email"
                />

                <!-- Phones -->
                <x-adminc::clinics.partials.contact-fields
                    :label="trans('admin::app.settings.clinics.view.attributes.phones')"
                    :fields="$clinic->phones"
                    type="phone"
                />

                <!-- Visit Address -->
                @if ($clinic->visitAddress)
                    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                        <div class="label dark:text-white">
                            @lang('admin::app.settings.clinics.addresses.visit-address')
                        </div>
                        <div class="font-medium dark:text-white">
                            @if (!empty($clinic->visitAddress->address_line_1))
                                <div>{{ $clinic->visitAddress->address_line_1 }}</div>
                            @endif
                            @if (!empty($clinic->visitAddress->address_line_2))
                                <div>{{ $clinic->visitAddress->address_line_2 }}</div>
                            @endif
                            @if (!empty($clinic->visitAddress->postal_code) || !empty($clinic->visitAddress->city))
                                <div>
                                    @if (!empty($clinic->visitAddress->postal_code))
                                        {{ $clinic->visitAddress->postal_code }}
                                    @endif
                                    @if (!empty($clinic->visitAddress->city))
                                        {{ $clinic->visitAddress->city }}
                                    @endif
                                </div>
                            @endif
                            @if (!empty($clinic->visitAddress->state))
                                <div>{{ $clinic->visitAddress->state }}</div>
                            @endif
                            @if (!empty($clinic->visitAddress->country))
                                <div>{{ $clinic->visitAddress->country }}</div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Postal Address -->
                @if (! $clinic->is_postal_address_same_as_visit_address && $clinic->postalAddress)
                    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                        <div class="label dark:text-white">
                            @lang('admin::app.settings.clinics.addresses.postal-address')
                        </div>
                        <div class="font-medium dark:text-white">
                            @if (!empty($clinic->postalAddress->address_line_1))
                                <div>{{ $clinic->postalAddress->address_line_1 }}</div>
                            @endif
                            @if (!empty($clinic->postalAddress->address_line_2))
                                <div>{{ $clinic->postalAddress->address_line_2 }}</div>
                            @endif
                            @if (!empty($clinic->postalAddress->postal_code) || !empty($clinic->postalAddress->city))
                                <div>
                                    @if (!empty($clinic->postalAddress->postal_code))
                                        {{ $clinic->postalAddress->postal_code }}
                                    @endif
                                    @if (!empty($clinic->postalAddress->city))
                                        {{ $clinic->postalAddress->city }}
                                    @endif
                                </div>
                            @endif
                            @if (!empty($clinic->postalAddress->state))
                                <div>{{ $clinic->postalAddress->state }}</div>
                            @endif
                            @if (!empty($clinic->postalAddress->country))
                                <div>{{ $clinic->postalAddress->country }}</div>
                            @endif
                        </div>
                    </div>
                @elseif ($clinic->is_postal_address_same_as_visit_address)
                    <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                        <div class="label dark:text-white">
                            @lang('admin::app.settings.clinics.addresses.postal-address')
                        </div>
                        <div class="font-medium dark:text-white">
                            <span class="text-gray-500 dark:text-gray-400">
                                @lang('admin::app.settings.clinics.addresses.postal-same-as-visit')
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </x-slot>
    </x-admin::accordion>
</div>
