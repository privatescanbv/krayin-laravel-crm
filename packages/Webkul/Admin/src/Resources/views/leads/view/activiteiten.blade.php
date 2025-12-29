@php
    // Activities view
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">
    <!-- Stages Navigation -->
    <!-- @ include ('admin::leads.view.stages') -->
 <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Activiteiten</h3>

            <div class="direction-row flex items-center gap-4">

                {{-- EVENTUEEL ACTIONS --}}

            </div>
        </div>
    </div>
    <!-- Activities -->
    {!! view_render_event('admin.leads.view.activities.before', ['lead' => $lead]) !!}

    <x-admin::activities
        :endpoint="route('admin.leads.activities.index', $lead->id)"
        :email-detach-endpoint="route('admin.leads.emails.detach', $lead->id)"
    >
    </x-admin::activities>

    {!! view_render_event('admin.leads.view.activities.after', ['lead' => $lead]) !!}
</div>

