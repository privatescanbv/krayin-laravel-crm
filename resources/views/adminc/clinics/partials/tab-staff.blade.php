@props(['staff'])

@php
    $artsen = $staff->filter(fn ($u) => $u->resource_id);
    $medewerkers = $staff->reject(fn ($u) => $u->resource_id);
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">
    @foreach ([['Artsen', $artsen, true], ['Medewerkers', $medewerkers, false]] as [$title, $users, $isArts])
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">{{ $title }} ({{ $users->count() }})</h3>

            @if ($users->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Geen {{ strtolower($title) }} gekoppeld aan deze kliniek.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-2 py-2 text-left">Naam</th>
                                <th class="px-2 py-2 text-left">E-mail</th>
                                @if ($isArts)
                                    <th class="px-2 py-2 text-left">Afdeling</th>
                                @endif
                                <th class="px-2 py-2 text-left">Rol</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr class="border-b border-gray-200 dark:border-gray-800">
                                    <td class="px-2 py-2 dark:text-white">{{ $user->name }}</td>
                                    <td class="px-2 py-2 dark:text-white">{{ $user->email }}</td>
                                    @if ($isArts)
                                        <td class="px-2 py-2 dark:text-white">{{ $user->resource?->clinicDepartment?->name ?? '-' }}</td>
                                    @endif
                                    <td class="px-2 py-2 dark:text-white">{{ $user->role?->name ?? '-' }}</td>
                                    <td class="px-2 py-2 dark:text-white">{{ $user->status ? 'Actief' : 'Inactief' }}</td>
                                    <td class="px-2 py-2">
                                        <div class="flex gap-2">
                                            @if ($user->resource_id)
                                                <a
                                                    href="{{ route('admin.settings.resources.show', $user->resource_id) }}"
                                                    class="text-status-active-text hover:text-green-800 dark:text-green-400 dark:hover:text-green-300"
                                                    title="Bekijk resource"
                                                >
                                                    <i class="icon-calendar text-lg"></i>
                                                </a>
                                            @endif
                                            @if (bouncer()->hasPermission('settings.user.users.view'))
                                                <a
                                                    href="{{ route('admin.settings.users.view', $user->id) }}"
                                                    class="text-status-active-text hover:text-green-800 dark:text-green-400 dark:hover:text-green-300"
                                                    title="Bekijk gebruiker"
                                                >
                                                    <i class="icon-eye text-lg"></i>
                                                </a>
                                            @endif
                                            @if (bouncer()->hasPermission('settings.user.users.edit'))
                                                <a
                                                    href="{{ route('admin.settings.users.edit', $user->id) }}"
                                                    class="text-activity-note-text hover:text-activity-task-text dark:text-blue-400 dark:hover:text-blue-300"
                                                    title="Bewerken"
                                                >
                                                    <i class="icon-edit text-lg"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
</div>
