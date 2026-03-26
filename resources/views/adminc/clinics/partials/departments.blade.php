<div class="p-4">
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h4 class="text-lg font-semibold dark:text-white">
                    Afdelingen
                </h4>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Totaal: {{ $clinic->departments->count() }}
                </span>
            </div>
            @if (bouncer()->hasPermission('settings.clinics.create'))
                <a href="{{ route('admin.clinic_departments.create', ['clinic_id' => $clinic->id, 'return_url' => route('admin.clinics.view', $clinic->id).'#afdelingen']) }}" class="primary-button">
                    Afdeling toevoegen
                </a>
            @endif
        </div>

        @if ($clinic->departments->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Naam
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                E-mail
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Omschrijving
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Acties
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clinic->departments as $department)
                            <tr class="border-b border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="p-2 dark:text-white">
                                    {{ $department->name }}
                                </td>
                                <td class="p-2 dark:text-white">
                                    {{ $department->email }}
                                </td>
                                <td class="p-2 dark:text-white">
                                    {{ $department->description ?? '-' }}
                                </td>
                                <td class="p-2">
                                    <div class="flex gap-2">
                                        @if (bouncer()->hasPermission('settings.clinics.edit'))
                                            <a
                                                href="{{ route('admin.clinic_departments.edit', ['id' => $department->id, 'return_url' => route('admin.clinics.view', $clinic->id).'#afdelingen']) }}"
                                                class="text-activity-note-text hover:text-activity-task-text dark:text-blue-400 dark:hover:text-blue-300"
                                                title="Bewerken"
                                            >
                                                <i class="icon-edit text-lg"></i>
                                            </a>
                                        @endif
                                        @if (bouncer()->hasPermission('settings.clinics.delete'))
                                            <form
                                                action="{{ route('admin.clinic_departments.delete', $department->id) }}"
                                                method="POST"
                                                class="inline"
                                                onsubmit="return confirm('Weet je zeker dat je deze afdeling wilt verwijderen?')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                    title="Verwijderen"
                                                >
                                                    <i class="icon-delete text-lg"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-8 text-center">
                <p class="text-gray-600 dark:text-gray-400">
                    Geen afdelingen gevonden.
                </p>
            </div>
        @endif
    </div>
</div>
