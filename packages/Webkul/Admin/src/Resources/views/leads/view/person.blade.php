{!! view_render_event('admin.leads.view.person.before', ['lead' => $lead]) !!}

@if ($lead?->person)
    <div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
        <x-admin::accordion class="select-none !border-none">
            <x-slot:header class="!p-0">
                <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                    <h4 >@lang('admin::app.leads.view.persons.title')</h4>

                    <div class="flex items-center gap-1">
                        @if (bouncer()->hasPermission('contacts.persons.edit'))
                            <a
                                href="{{ route('admin.contacts.persons.edit', $lead->person->id) }}"
                                class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                title="Wijzig persoon"
                            ></a>
                        @endif

                        <a
                            href="{{ route('admin.contacts.persons.edit_with_lead', [$lead->person->id, $lead->id]) }}"
                            class="rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950 text-green-600 hover:text-green-700"
                            title="Synchroniseer persoon met lead gegevens"
                            target="_blank"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </x-slot>

            <x-slot:content class="mt-4 !px-0 !pb-0">
                <div class="flex gap-2">
                    {!! view_render_event('admin.leads.view.person.avatar.before', ['lead' => $lead]) !!}

                    <!-- Person Initials -->
                    <x-admin::avatar :name="$lead->person->name" />

                    {!! view_render_event('admin.leads.view.person.avatar.after', ['lead' => $lead]) !!}

                    <!-- Person Details -->
                    <div class="flex flex-col gap-1">
                        {!! view_render_event('admin.leads.view.person.name.before', ['lead' => $lead]) !!}

                        @if (bouncer()->hasPermission('contacts.persons.edit'))
                            <a
                                href="{{ route('admin.contacts.persons.edit', $lead->person->id) }}"
                                class="font-semibold text-brandColor hover:underline"
                                title="Wijzig persoon"
                            >
                                {{ $lead->person->name }}
                            </a>
                        @else
                            <a
                                href="{{ route('admin.contacts.persons.view', $lead->person->id) }}"
                                class="font-semibold text-brandColor"
                                target="_blank"
                            >
                                {{ $lead->person->name }}
                            </a>
                        @endif

                        {!! view_render_event('admin.leads.view.person.name.after', ['lead' => $lead]) !!}

                        {!! view_render_event('admin.leads.view.person.email.before', ['lead' => $lead]) !!}

                        @foreach ($lead->person->emails as $email)
                            <div class="flex gap-1">
                                <a
                                    class="text-brandColor"
                                    href="mailto:{{ $email['value'] }}"
                                >
                                    {{ $email['value'] }}
                                </a>

                                <span class="text-gray-500 dark:text-gray-300">
                                    ({{ $email['label'] }})
                                </span>
                            </div>
                        @endforeach

                        {!! view_render_event('admin.leads.view.person.email.after', ['lead' => $lead]) !!}

                        {!! view_render_event('admin.leads.view.person.contact_numbers.before', ['lead' => $lead]) !!}

                        @foreach ($lead->person->contact_numbers as $contactNumber)
                            <div class="flex gap-1">
                                <a
                                    class="text-brandColor"
                                    href="callto:{{ $contactNumber['value'] }}"
                                >
                                    {{ $contactNumber['value'] }}
                                </a>

                                <span class="text-gray-500 dark:text-gray-300">
                                    ({{ $contactNumber['label'] }})
                                </span>
                            </div>
                        @endforeach

                        {!! view_render_event('admin.leads.view.person.contact_numbers.after', ['lead' => $lead]) !!}
                    </div>
                </div>
            </x-slot>
        </x-admin::accordion>
    </div>
@endif
{!! view_render_event('admin.leads.view.person.after', ['lead' => $lead]) !!}
