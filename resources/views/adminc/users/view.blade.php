@php use App\Actions\Clinic\SendClinicForgotPasswordMailAction; @endphp
<x-adminc::components.entity-view-layout :title="$user->name" :entity="$user">
    <x-slot:breadcrumb>
        <x-admin::breadcrumbs name="settings.users.view" :entity="$user"/>
    </x-slot>

    <x-slot:info>
        <div class="text-xl font-bold dark:text-white">{{ $user->name }}</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
    </x-slot>

    <x-slot:actions>
        @if (bouncer()->hasPermission('settings.user.users.impersonate') && SendClinicForgotPasswordMailAction::clinicUsers()->whereKey($user->id)->exists())
            <form id="impersonate-clinic-user-form" method="POST" action="{{ route('admin.settings.users.impersonate', $user->id) }}" style="display:inline;">
                @csrf
                <button type="button" title="Login als kliniekgebruiker" class="group flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-activity-note-bg font-medium text-activity-note-text transition-all hover:border-activity-note-border hover:text-blue-700" onclick="startClinicUserImpersonation()">
                    <span class="icon-user text-2xl text-activity-note-text transition-all group-hover:text-blue-700 dark:!text-activity-note-text"></span>
                    Login
                </button>
            </form>
        @endif
    </x-slot>

    <x-slot:navigation>
        <x-adminc::components.entity-navigation-menu :activitiesCount="$activitiesCount" :showAnamnesis="false" :showMarketing="false"/>
    </x-slot>

    <div v-if="leadDetailSection === 'algemeen'" class="flex w-full flex-col gap-4 rounded-lg">
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Gebruiker</h3>

                <div class="direction-row flex items-center gap-4">
                    @if (bouncer()->hasPermission('settings.user.users.edit'))
                        <a href="{{ route('admin.settings.users.edit', $user->id) }}"
                           class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                            <span class="icon-edit text-base"></span>
                            <span>Bewerk Gebruiker</span>
                        </a>
                    @endif

                    @if (bouncer()->hasPermission('settings.user.users.delete') && auth()->guard('user')->id() !== $user->id)
                        <x-adminc::components.entity-delete-button
                            :delete-url="route('admin.settings.users.delete', $user->id)"
                            :redirect-url="route('admin.settings.users.index')"
                            :name="$user->name"
                            label="Gebruiker"
                        />
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <span class="icon-menu text-xl text-gray-600 dark:text-gray-400"></span>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">gebruiker gegevens</h3>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span class="icon-calendar text-base"></span>
                    <span>Laatst bijgewerkt: {{ $user->updated_at?->format('d M Y') }}</span>
                </div>
            </div>

            <dl class="grid grid-cols-1 gap-6 p-4 text-sm md:grid-cols-3">
                @foreach ([
                    'E-mail'   => $user->email,
                    'Rol'      => $user->role?->name,
                    'Status'   => $user->status ? 'Actief' : 'Inactief',
                    'Kliniek'  => $user->clinic?->name ?? $user->resource?->clinicDepartment?->clinic?->name,
                    'Afdeling' => $user->resource?->clinicDepartment?->name,
                    'Groepen'  => $user->groups->pluck('name')->join(', '),
                ] as $label => $value)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="font-medium dark:text-white">{{ filled($value) ? $value : '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div v-else-if="leadDetailSection === 'activiteiten'" class="flex w-full flex-col gap-4 rounded-lg">
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Activiteiten</h3>
        </div>

        <x-admin::activities
            :endpoint="route('admin.settings.users.activities.index', $user->id)"
            active-type="system"
            :types="[['name' => 'system', 'label' => 'Logboek']]"
        />
    </div>

    <x-slot:scripts>
        @pushOnce('scripts', 'clinic-user-impersonation')
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
    </x-slot>
</x-adminc::components.entity-view-layout>
