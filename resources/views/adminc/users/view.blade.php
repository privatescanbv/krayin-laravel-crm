<x-admin::layouts>
    <x-slot:title>
        {{ $user->name }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-white">{{ $user->name }}</div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ url()->previous(route('admin.settings.users.index')) }}" class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800">
                    Terug
                </a>
                @if (
                    bouncer()->hasPermission('settings.user.users.impersonate')
                    && \App\Actions\Clinic\SendClinicForgotPasswordMailAction::clinicUsers()->whereKey($user->id)->exists()
                )
                    <form id="impersonate-clinic-user-form" method="POST" action="{{ route('admin.settings.users.impersonate', $user->id) }}" style="display:inline;">
                        @csrf
                        <button type="button" class="secondary-button" title="Login als kliniekgebruiker" onclick="startClinicUserImpersonation()">
                            Open kliniekportaal als gebruiker
                        </button>
                    </form>
                @endif
                @if (bouncer()->hasPermission('settings.user.users.edit'))
                    <a href="{{ route('admin.settings.users.edit', $user->id) }}" class="primary-button">Bewerken</a>
                @endif
            </div>
        </div>

        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                @foreach ([
                    'E-mail'  => $user->email,
                    'Rol'     => $user->role?->name,
                    'Status'  => $user->status ? 'Actief' : 'Inactief',
                    'Kliniek' => $user->clinic?->name ?? $user->resource?->clinicDepartment?->clinic?->name,
                    'Afdeling' => $user->resource?->clinicDepartment?->name,
                    'Groepen' => $user->groups->pluck('name')->join(', '),
                ] as $label => $value)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="font-medium dark:text-white">{{ filled($value) ? $value : '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="module">
            window.startClinicUserImpersonation = function () {
                const form = document.getElementById('impersonate-clinic-user-form');
                if (!form) return;

                form.target = '_blank';
                form.submit();

                // Reload main tab after a moment so the warning banner appears
                setTimeout(() => window.location.reload(), 1200);
            };
        </script>
    @endPushOnce
</x-admin::layouts>
