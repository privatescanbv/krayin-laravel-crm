@php
    // Activities view
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">
    <!-- Stages Navigation -->
    @include ('admin::leads.view.stages')

    <!-- Activities -->
    {!! view_render_event('admin.leads.view.activities.before', ['lead' => $lead]) !!}

    <x-admin::activities
        :endpoint="route('admin.leads.activities.index', $lead->id)"
        :email-detach-endpoint="route('admin.leads.emails.detach', $lead->id)"
        :activeType="'planned'"
        :extra-types="[
            ['name' => 'description', 'label' => trans('admin::app.leads.view.tabs.description')],
        ]"
    >
        <!-- Description -->
        <x-slot:description>
            <div class="p-4 dark:text-white">
                {{ $lead->description }}
            </div>
        </x-slot>
    </x-admin::activities>

    {!! view_render_event('admin.leads.view.activities.after', ['lead' => $lead]) !!}
</div>

