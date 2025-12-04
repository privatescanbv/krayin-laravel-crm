@php
    // General lead information view
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">
    <!-- Stages Navigation -->
    <!-- @ include ('admin::leads.view.stages') -->

    <div
        class="rounded-lg border border-neutral-border bg-neutral-muted text-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        notes:
        - Wat als persoon meerdere telefoonnummers of e-mailadressen heeft? zo laten en alleen defaults tonen?
    </div>

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Lead</h3>

            <div class="direction-row flex items-center gap-4">
                @if (bouncer()->hasPermission('leads.edit'))
                    <a href="{{ route('admin.leads.edit', $lead->id) }}"
                        class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text"><span
                            class="icon-edit text-base"></span><span>Bewerk lead</span></></a>
                @endif

                @if (bouncer()->hasPermission('leads.delete'))
                    <v-lead-delete delete-url="{{ route('admin.leads.delete', $lead->id) }}"
                        redirect-url="{{ route('admin.leads.index') }}" lead-name="{{ $lead->name }}"></v-lead-delete>
                @endif
            </div>
        </div>
    </div>
        <x-adminc::leads.compact-overview :lead="$lead" />

    <!-- Person Blocks Grid -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @if ($lead->hasContactPerson())
            @include('admin::leads.view.person', [
                'lead' => $lead,
                'person' => $lead->contactPerson,
                'isContactPerson' => true
            ])
        @endif

        <!-- Person Blocks - One for each person -->
        @foreach ($lead->persons as $person)
            @include('admin::leads.view.person', ['lead' => $lead, 'person' => $person])
        @endforeach
    </div>

</div>
