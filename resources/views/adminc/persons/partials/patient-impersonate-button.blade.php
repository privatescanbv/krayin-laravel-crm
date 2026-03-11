@props([
    'person',
    'presentLarge' => false,
    'returnUrl' => null,
])
@php
    $hasPortalAccount = !empty($person->keycloak_user_id);
@endphp


@if ($person->is_active)
    @if (!empty($person->keycloak_user_id))
        @if (bouncer()->hasPermission('contacts.persons.impersonate') && $hasPortalAccount)
            <form
                id="impersonate-form-{{ $person->id }}"
                method="POST"
                action="{{ route('admin.contacts.persons.impersonate', $person->id) }}"
                style="display:inline;">
                @csrf
                @if ($presentLarge)
                    <button
                        type="button"
                        title="Login als patiënt"
                        class="group flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-activity-note-bg font-medium text-activity-note-text transition-all hover:border-activity-note-border hover:text-blue-700"
                        onclick="startPatientImpersonation({{ $person->id }})"
                    >
                        <span class="icon-user text-2xl text-activity-note-text transition-all group-hover:text-blue-700 dark:!text-activity-note-text"></span>
                        Login
                    </button>
                @else
                    <button
                        type="button"
                        class="icon-user rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-activity-note-text hover:text-blue-700"
                        title="Login als patiënt"
                        onclick="startPatientImpersonation({{ $person->id }})"
                    ></button>
                @endif
            </form>
        @endif
    @endif
@endif

@pushOnce('scripts')
    <script type="module">
        window.startPatientImpersonation = function(personId) {
            const form = document.getElementById('impersonate-form-' + personId);
            if (!form) return;

            form.target = '_blank';
            form.submit();

            // Reload main tab after a moment so the warning banner appears
            setTimeout(() => window.location.reload(), 1200);
        };
    </script>
@endPushOnce
