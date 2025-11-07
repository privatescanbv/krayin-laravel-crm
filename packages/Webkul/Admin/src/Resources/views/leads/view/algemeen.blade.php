@php
    // General lead information view
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">
    <!-- Stages Navigation -->
    @include ('admin::leads.view.stages')



    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        notes:
        - Wat als persoon meerdere telefoonnummers of e-mailadressen heeft? zo laten en alleen defaults tonen?
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Lead</h3>

            @if (bouncer()->hasPermission('leads.edit'))
                <a
                    href="{{ route('admin.leads.edit', $lead->id) }}"
                    class="text-sm font-medium text-brandColor hover:underline"
                >
                    Bewerk lead
                </a>
            @endif
        </div>
        <x-adminc::leads.compact-overview :lead="$lead"/>
    </div>

    <!-- Person Blocks Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @if($lead->hasContactPerson())
            @include('admin::leads.view.person', ['lead' => $lead, 'person' => $lead->contactPerson, 'isContactPerson' => true])
        @endif

        <!-- Person Blocks - One for each person -->
        @foreach($lead->persons as $person)
            @include('admin::leads.view.person', ['lead' => $lead, 'person' => $person])
        @endforeach
    </div>


</div>

