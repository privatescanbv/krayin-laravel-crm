@props(['person'])


<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Persoon</h3>

            <div class="direction-row flex items-center gap-4">
                @if (bouncer()->hasPermission('persons.edit'))
                    <a href="{{ route('admin.contacts.persons.edit', $person->id) }}"
                       class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                        <span class="icon-edit text-base"></span>
                        <span>Bewerk Persoon</span>
                    </a>
                @endif

                @if (bouncer()->hasPermission('persons.delete'))
                    @if ($person->hasPortalAccount())
                        <span
                            class="secondary-button flex items-center gap-1 border border-red-100 text-status-expired-text opacity-50"
                            title="Deze persoon heeft een patiëntportaalaccount en kan niet worden verwijderd. Trek het account eerst in."
                        >
                            <span class="icon-delete text-base"></span>
                            <span>Verwijderen</span>
                        </span>
                    @else
                    <v-person-delete
                        delete-url="{{ route('admin.contacts.persons.delete', $person->id) }}"
                        redirect-url="{{ route('admin.contacts.persons.index') }}"
                        person-name="{{ $person->name }}"
                    />
                    @endif
                @endif
            </div>
        </div>
    </div>

    <x-adminc::persons.partials.compact-overview :person="$person" />

    <!-- Person Blocks Grid -->
{{--    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-2">--}}
{{--        @include('adminc::persons.person', ['person' => $person])--}}
{{--    </div>--}}
{{--    <x-adminc::persons.partials.overview :person="$person" />--}}

</div>
