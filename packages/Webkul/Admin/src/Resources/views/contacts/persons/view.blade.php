@php use Webkul\Email\Models\Email; @endphp
<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.contacts.persons.view.title', ['name' => $person->name])
    </x-slot>

    <!-- Content -->
    <div class="flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        {!! view_render_event('admin.contact.persons.view.left.before', ['person' => $person]) !!}

        <div
            class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Person Information -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <!-- Breadcrumbs and Edit Button -->
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="contacts.persons.view"
                        :entity="$person"
                    />
                </div>

                {!! view_render_event('admin.contact.persons.view.tags.before', ['person' => $person]) !!}

                <!-- Duplicate Detection -->
                @if (($duplicateCount ?? 0) > 0)
                    <div
                        class="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-3 dark:border-orange-800 dark:bg-orange-900/20">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="icon-warning text-orange-600"></span>
                                <span class="text-sm font-medium text-activity-note-text dark:text-orange-200">
                                    Potentiële duplicaten gevonden ({{ $duplicateCount }} similar persons{{ $duplicateCount > 1 ? 's' : '' }})
                                </span>
                            </div>
                            <a
                                href="{{ route('admin.contacts.persons.duplicates.index', $person->id) }}"
                                class="rounded bg-orange-600 px-3 py-1 text-xs text-white hover:bg-orange-700"
                            >
                                Duplicaten samenvoegen
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Tags -->
                <x-admin::tags
                    :attach-endpoint="route('admin.contacts.persons.tags.attach', $person->id)"
                    :detach-endpoint="route('admin.contacts.persons.tags.detach', $person->id)"
                    :added-tags="$person->tags"
                />

                {!! view_render_event('admin.contact.persons.view.tags.after', ['person' => $person]) !!}

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

                    @if (bouncer()->hasPermission('leads.create'))
                        <a
                            href="{{ route('admin.leads.create') }}?person_id={{ $person->id }}"
                            class="primary-button"
                            title="Nieuwe lead voor deze persoon"
                        >
                            <i class="icon-plus text-xs"></i>
                            Nieuwe lead
                        </a>
                    @endif

                    @if (bouncer()->hasPermission('contacts.persons.edit'))
                        @if (empty($person->keycloak_user_id))
                            <form
                                class="inline-flex"
                                method="POST"
                                action="{{ route('admin.contacts.persons.portal.create', $person->id) }}"
                                onsubmit="return confirm('Portal account aanmaken voor {{ $person->name }}?')"
                            >
                                @csrf
                                <button type="submit" class="secondary-button">
                                    <i class="icon-login text-xs"></i>
                                    Maak patiëntportaal account aan
                                </button>
                            </form>
                        @else
                            <form
                                class="inline-flex"
                                method="POST"
                                action="{{ route('admin.contacts.persons.portal.delete', $person->id) }}"
                                onsubmit="return confirm('Portal account verwijderen voor {{ $person->name }}?')"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="secondary-button border border-error text-status-expired-text hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950 flex items-center gap-1">
                                    <i class="icon-trash text-xs"></i>
                                    Patiëntportaal account intrekken
                                </button>
                            </form>
                        @endif
                    @endif

                    {!! view_render_event('admin.contact.persons.view.actions.after', ['person' => $person]) !!}
                </div>
            </div>

{{--            @include('admin::contacts.persons.common.card', ['person' => $person, 'show_actions' => false])--}}
            <x-adminc::persons.card :person="$person" show_actions="false"/>

            <x-adminc::persons.compact-overview :person="$person"/>

            <!-- Gekoppelde Anamneses -->
            <div class="border-b border-gray-200 dark:border-gray-800">
                <x-adminc::anamnesis.index :anamnesis="$person->anamnesis"/>
            </div>

            <!-- Gekoppelde Leads -->
            <div class="border-b border-gray-200 dark:border-gray-800">
                <x-admin::leads :leads="$sortedLeads ?? $person->leads"/>
            </div>

            <!-- Footer with creation and modification dates -->
            <div
                class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
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
                <x-admin::activities
                    :endpoint="route('admin.contacts.persons.activities.index', $person->id)"
                    :activeType="'planned'"
                />
            </div>
        </div>
    </div>
</x-admin::layouts>
