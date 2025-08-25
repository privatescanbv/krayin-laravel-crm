<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.contacts.persons.view.title', ['name' => $person->name])
    </x-slot>

    <!-- Content -->
    <div class="flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        {!! view_render_event('admin.contact.persons.view.left.before', ['person' => $person]) !!}

        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Person Information -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <!-- Breadcrumbs and Edit Button -->
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="contacts.persons.view"
                        :entity="$person"
                    />

                    @if (bouncer()->hasPermission('contacts.persons.edit'))
                        <a
                            href="{{ route('admin.contacts.persons.edit', $person->id) }}"
                            class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                            title="Wijzig persoon"
                        ></a>
                    @endif
                </div>

                {!! view_render_event('admin.contact.persons.view.tags.before', ['person' => $person]) !!}

                <!-- Tags -->
                <x-admin::tags
                    :attach-endpoint="route('admin.contacts.persons.tags.attach', $person->id)"
                    :detach-endpoint="route('admin.contacts.persons.tags.detach', $person->id)"
                    :added-tags="$person->tags"
                />

                {!! view_render_event('admin.contact.persons.view.tags.after', ['person' => $person]) !!}


                <!-- Title -->
                <div class="mb-4 flex flex-col gap-0.5">
                    {!! view_render_event('admin.contact.persons.view.title.before', ['person' => $person]) !!}

                    <h3 class="text-lg font-bold dark:text-white">
                        {{ $person->name }}
                    </h3>

                    <p class="dark:text-white">
                        {{ $person->job_title }}
                    </p>

                    {!! view_render_event('admin.contact.persons.view.title.after', ['person' => $person]) !!}
                </div>

                <!-- Activity Actions -->
                <div class="flex flex-wrap gap-2">
                    {!! view_render_event('admin.contact.persons.view.actions.before', ['person' => $person]) !!}

                    <!-- Mail Activity Action -->
                    <x-admin::activities.actions.mail
                        :entity="$person"
                        entity-control-name="person_id"
                    />

                    <!-- File Activity Action -->
                    <x-admin::activities.actions.file
                        :entity="$person"
                        entity-control-name="person_id"
                    />

                    <!-- Note Activity Action -->
                    <x-admin::activities.actions.note
                        :entity="$person"
                        entity-control-name="person_id"
                    />

                    <!-- Activity Action -->
                    <x-admin::activities.actions.activity
                        :entity="$person"
                        entity-control-name="person_id"
                    />

                    {!! view_render_event('admin.contact.persons.view.actions.after', ['person' => $person]) !!}
                </div>
            </div>

            <!-- Person Overview (merged attributes and organization) -->
            @include ('admin::contacts.persons.view.compact-overview')

            <!-- Gekoppelde Anamneses -->
            <div class="border-b border-gray-200 dark:border-gray-800">
                <x-admin::anamnesis.index :anamnesis="$person->anamnesis" />
            </div>

            <!-- Gekoppelde Leads -->
            <div class="border-b border-gray-200 dark:border-gray-800">
                <x-admin::leads :leads="$person->leads" />
            </div>

            <!-- Footer with creation and modification dates -->
            <div class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
                <div class="flex justify-between">
                    <span>Aangemaakt:</span>
                    <span>{{ $person->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Laatst gewijzigd:</span>
                    <span>{{ $person->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {!! view_render_event('admin.contact.persons.view.left.after', ['person' => $person]) !!}

        <!-- Right Panel -->
        <div class="flex w-full flex-row gap-4 rounded-lg">
            <div class="flex-1">
                <x-admin::activities :endpoint="route('admin.contacts.persons.activities.index', $person->id)" />
            </div>
        </div>
    </div>
</x-admin::layouts>
