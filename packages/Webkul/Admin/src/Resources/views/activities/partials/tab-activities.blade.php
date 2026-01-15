@props([
    'entityId',
    'entityType' => 'leads',
])
@php
    $activitiesRoute = "admin.$entityType.activities.index";
    $emailDetachRoute = "admin.$entityType.emails.detach";
@endphp
<div class="flex w-full flex-col gap-4 rounded-lg">
    <!-- Stages Navigation -->
 <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Activiteiten</h3>

            <div class="direction-row flex items-center gap-4">
                {{-- EVENTUEEL ACTIONS --}}
            </div>
        </div>
    </div>
    <!-- Activities -->

    <x-admin::activities
        :endpoint="route($activitiesRoute, $entityId)"
        :email-detach-endpoint="route($emailDetachRoute, $entityId)"
    />

</div>
