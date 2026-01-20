@props(['person', 'patientMessageActivity' => null])

<div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between gap-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Patientberichten</h3>
    </div>
</div>

@if ($patientMessageActivity)
    @include('admin::activities.partials.patient-message', ['activity' => $patientMessageActivity])
@else
    <div class="rounded-lg border bg-white p-6 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <p class="mb-4">Nog geen patientberichten voor deze persoon.</p>

        <form method="POST" action="{{ route('admin.contacts.persons.activities.store', $person->id) }}">
            @csrf
            <button type="submit" class="primary-button">
                Start chat
            </button>
        </form>
    </div>
@endif
