@php
    // General lead information view
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Lead</h3>

            <div class="direction-row flex items-center gap-4">
                @if (bouncer()->hasPermission('leads.edit'))
                    <a href="{{ route('admin.leads.edit', $lead->id) }}"
                        class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                        <span class="icon-edit text-base"></span><span>Bewerk lead</span>
                    </a>
                @endif

                @if (bouncer()->hasPermission('leads.delete'))
                    <v-lead-delete delete-url="{{ route('admin.leads.delete', $lead->id) }}"
                        redirect-url="{{ route('admin.leads.index') }}" :lead-name='@json($lead->name)'></v-lead-delete>
                @endif
            </div>
        </div>
    </div>

    <!-- Stages Navigation -->
    @include ('admin::leads.view.stages')

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <div class="direction-row flex items-center break-all">

                <read-more
                    :text='@json($lead->description ?? "")'
                    :lines="5"
                />
            </div>
        </div>
    </div>
    <x-adminc::leads.compact-overview :lead="$lead" />

    <!-- Person Blocks Grid -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-2">
        @if ($lead->hasContactPerson())
            @include('adminc::persons.person', [
                'lead' => $lead,
                'person' => $lead->contactPerson,
                'isContactPerson' => true
            ])
        @endif

        <!-- Person Blocks - One for each person -->
        @foreach ($lead->persons as $person)
            @include('adminc::persons.person', ['lead' => $lead, 'person' => $person])
        @endforeach

        <!-- Insurance Block -->
        @if($lead->hasOrganization())
            @include('adminc::organisations.general_info', ['organisation' => $lead->organization])
        @endif

   </div>

</div>
