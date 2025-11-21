{!! view_render_event('admin.contacts.persons.view.compact_overview.before', ['person' => $person]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                <h4>Gegevens</h4>

                @if (bouncer()->hasPermission('persons.edit'))
                    <a
                        href="{{ route('admin.contacts.persons.edit', $person->id) }}"
                        class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950"
                    ></a>
                @endif
            </div>
        </x-slot>
        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.contacts.persons.view.attributes.form_controls.before', ['person' => $person]) !!}

            <div class="flex flex-col text-sm">
                @if ($person->organization)
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Organisatie</div>
                    <div>
                        <a
                            href="{{ route('admin.contacts.organizations.view', $person->organization->id) }}"
                            target="_blank"
                            class="text-sm font-medium text-brandColor hover:underline"
                        >
                            {{ $person->organization->name }}
                            <span class="icon-external-link text-xs ml-1"></span>
                        </a>
                    </div>
                </div>
                @endif

                <!-- Address -->
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Adres</div>
                    <div class="text-sm text-gray-900 dark:text-gray-100">
                        @if ($person->address && $person->address->full_address)
                            {{ $person->address->full_address }}
                            <a
                                href="https://maps.google.com/?q={{ urlencode($person->address->full_address) }}"
                                target="_blank"
                                class="ml-2 text-activity-note-text hover:text-activity-task-text dark:text-blue-400 dark:hover:text-blue-300"
                                title="Bekijk op Google Maps"
                            >

                            </a>
                        @else
                            <span class="text-gray-500 dark:text-gray-400 italic">Geen adres</span>
                        @endif
                    </div>
                </div>

            <!-- Custom Attributes Section -->
            <div class="mt-6">
                <x-admin::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                    ref="modalForm"
                >
                    <form @submit="handleSubmit($event, () => {})">
                        {!! view_render_event('admin.contacts.persons.view.attributes.form_controls.attributes_view.before', ['person' => $person]) !!}

                        <x-admin::attributes.view
                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                'entity_type' => 'persons',
                                ['code', 'NOTIN', ['name', 'jon_title']]
                            ])"
                            :entity="$person"
                            :url="route('admin.contacts.persons.update', $person->id)"
                            :allow-edit="true"
                        />

                        {!! view_render_event('admin.contacts.persons.view.attributes.form_controls.attributes_view.after', ['person' => $person]) !!}
                    </form>
                </x-admin::form>
            </div>

            <!-- Suite CRM link -->
            @if (!empty($person->sugar_link))
                <div class="mb-4 pt-[10px]">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Sugar Link</div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        <a href="{{ $person->sugar_link }}" target="_blank">{{ $person->external_id }}</a>
                    </div>
                </div>
            @endif

            {!! view_render_event('admin.contacts.persons.view.attributes.form_controls.after', ['person' => $person]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.contacts.persons.view.compact_overview.after', ['person' => $person]) !!}
