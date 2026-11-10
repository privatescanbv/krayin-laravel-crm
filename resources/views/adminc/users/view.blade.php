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
        @if (bouncer()->hasPermission('settings.user.users.edit'))
            <a href="{{ route('admin.settings.users.edit', $user->id) }}" class="group flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-activity-note-bg font-medium text-activity-note-text transition-all hover:border-activity-note-border hover:text-blue-700">
                <span class="icon-edit text-2xl text-activity-note-text transition-all group-hover:text-blue-700 dark:!text-activity-note-text"></span>
                Bewerken
            </a>
        @endif
    </x-slot>

    <x-slot:navigation>
        <x-adminc::components.entity-navigation-menu :activitiesCount="$activitiesCount" :showAnamnesis="false" :showMarketing="false"/>
    </x-slot>

    <div v-if="leadDetailSection === 'algemeen'" class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
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
